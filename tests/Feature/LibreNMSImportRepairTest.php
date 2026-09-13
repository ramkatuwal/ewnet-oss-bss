<?php

namespace Tests\Feature;

use App\Http\Resources\V1\AssetResource;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDeviceType;
use App\Models\AssetExternalReference;
use App\Models\Company;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\Site;
use App\Models\User;
use App\Services\LibreNMSImportService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LibreNMSImportRepairTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Integration $integration;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::preventStrayRequests();
        $company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $company->id]);
        $this->user->givePermissionTo('librenms.import');
        $this->site = Site::factory()->create(['company_id' => $company->id]);
        $this->integration = Integration::factory()->create([
            'provider' => 'librenms', 'company_id' => $company->id,
            'configuration' => ['api_url' => 'https://nms.test'],
        ]);
        $credential = new IntegrationCredential([
            'integration_id' => $this->integration->id, 'credential_type' => 'api_token',
            'is_active' => true, 'label' => 'test',
        ]);
        $credential->setSecretValue('test-provider-token');
        $credential->save();
    }

    private function device(string $id = '42', array $extra = []): array
    {
        return array_replace([
            'device_id' => $id, 'display' => '  Provider Name  ', 'sysName' => 'system-name',
            'hostname' => '192.0.2.1', 'ip' => '192.0.2.1', 'serial' => 'SERIAL-'.$id,
            'os' => 'routeros', 'hardware' => 'CCR', 'type' => 'network', 'status' => '1',
            'location' => $this->site->name,
        ], $extra);
    }

    private function runImport(array $selection, ?Integration $integration = null): array
    {
        $integration ??= $this->integration;
        $history = ImportHistory::create([
            'source' => 'librenms', 'type' => 'device', 'integration_id' => $integration->id,
            'status' => 'running', 'started_by' => $this->user->id, 'total_records' => count($selection),
        ]);

        return app(LibreNMSImportService::class)->execute($integration, $this->user, $selection, $history);
    }

    public function test_batch_resolves_only_ids_and_preserves_provider_fields_in_api(): void
    {
        Http::fake(['https://nms.test/api/v0/devices' => Http::response(['devices' => [$this->device(), $this->device('43')]])]);
        $result = $this->runImport([
            ['external_id' => '42', 'display' => 'FORGED', 'hostname' => 'forged', 'os' => 'forged',
                'hardware' => 'forged', 'serial' => 'forged', 'ip' => '203.0.113.99',
                'location' => 'forged', 'site_id' => 999999, 'asset_id' => 999999, 'integration_id' => 999999],
            ['external_id' => '43'], ['external_id' => 'missing', 'device_id' => '42'], [],
        ]);
        $this->assertSame(2, $result['created']);
        $this->assertSame(2, $result['failed']);
        $this->assertCount(2, $result['errors']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === 'https://nms.test/api/v0/devices'
            && $r->hasHeader('X-Auth-Token', 'test-provider-token'));
        $asset = AssetExternalReference::where('external_id', '42')->firstOrFail()->asset;
        $this->assertSame('Provider Name', $asset->device_name);
        $this->assertNull($asset->manufacturer);
        $this->assertSame('CCR', $asset->model);
        $this->assertSame('OTHER', $asset->type);
        $this->assertSame('192.0.2.1', $asset->management_ip);
        $obs = (new AssetResource($asset))->resolve()['provider_observations'];
        $this->assertSame('  Provider Name  ', $obs['observed_display']);
        $this->assertSame('system-name', $obs['observed_sys_name']);
        $this->assertSame('192.0.2.1', $obs['observed_hostname']);
        $this->assertSame('network', $obs['provider_type']);
        $this->assertSame('routeros', $obs['observed_os']);
        $this->assertSame('SERIAL-42', $obs['serial_number']);
        $this->assertSame($this->integration->id, $obs['integration_id']);
        $this->assertSame('UP', $obs['provider_status']);
        $this->assertCount(2, ImportHistory::latest('id')->first()->metadata['device_errors']);
    }

    public function test_resync_reconciles_ip_and_serial_observations_not_authoritative_identity(): void
    {
        Http::fake(['https://nms.test/api/v0/devices' => Http::sequence()
            ->push(['devices' => [$this->device()]])
            ->push(['devices' => [$this->device('42', ['ip' => '192.0.2.2', 'serial' => 'NEW', 'status' => '0', 'hardware' => 'NEW'])]])]);
        $this->runImport([['external_id' => '42']]);
        $asset = AssetExternalReference::where('external_id', '42')->firstOrFail()->asset;
        $asset->update(['model' => null, 'manufacturer' => null]);
        $result = $this->runImport([['external_id' => '42'], ['external_id' => '42']]);
        $this->assertSame(2, $result['updated']);
        $asset->refresh();
        $this->assertSame('192.0.2.2', $asset->management_ip);
        $this->assertSame('SERIAL-42', $asset->serial_number);
        $this->assertSame('NEW', $asset->specifications['serial_number']);
        $this->assertSame('192.0.2.2', $asset->specifications['ip_address']);
        $this->assertSame('DOWN', $asset->specifications['provider_status']);
        $this->assertNull($asset->manufacturer);
        $this->assertNull($asset->model);
        $this->assertSame(1, AssetExternalReference::where('external_id', '42')->count());
        Http::assertSentCount(2);
    }

    public function test_identical_ids_and_attributes_in_two_integrations_do_not_reassign_or_link(): void
    {
        $other = $this->integration->replicate();
        $other->name = 'Other integration';
        $other->save();
        Http::fake(['https://nms.test/api/v0/devices' => Http::response(['devices' => [$this->device('42', ['serial' => null])]])]);
        $this->assertSame(1, $this->runImport([['external_id' => '42']])['created']);
        $first = AssetExternalReference::where('integration_id', $this->integration->id)->firstOrFail();
        $this->assertSame(1, $this->runImport([['external_id' => '42']], $other)['created']);
        $second = AssetExternalReference::where('integration_id', $other->id)->firstOrFail();
        $this->assertNotSame($first->asset_id, $second->asset_id);
        $this->assertSame($this->integration->id, $first->fresh()->integration_id);
        $this->assertSame(1, $this->runImport([['external_id' => '42']], $other)['updated']);
    }

    public function test_serial_conflict_is_not_global_linking_and_does_not_abort_remaining_batch(): void
    {
        $existing = Asset::factory()->create(['serial_number' => 'SERIAL-42']);
        Http::fake(['https://nms.test/api/v0/devices' => Http::response(['devices' => [$this->device(), $this->device('43')]])]);
        $result = $this->runImport([['external_id' => '42'], ['external_id' => '43']]);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, $result['created']);
        $this->assertFalse(AssetExternalReference::where('asset_id', $existing->id)->exists());
    }

    public function test_fallback_must_be_active_and_belong_to_network(): void
    {
        AssetDeviceType::whereHas('category', fn ($q) => $q->where('code', 'NETWORK'))
            ->whereIn('code', ['OTHER', 'UNKNOWN'])->update(['is_active' => false]);
        Http::fake(['https://nms.test/api/v0/devices' => Http::response(['devices' => [$this->device()]])]);
        $result = $this->runImport([['external_id' => '42']]);
        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('NETWORK OTHER or UNKNOWN', $result['errors'][0]['message']);
        AssetDeviceType::create(['category_id' => AssetCategory::where('code', 'NETWORK')->value('id'),
            'code' => 'UNKNOWN', 'name' => 'Unknown', 'is_active' => true]);
        $this->assertSame(1, $this->runImport([['external_id' => '42']])['created']);
    }

    public function test_preview_and_execute_do_not_map_cross_company_or_ambiguous_locations(): void
    {
        $this->site->update(['company_id' => Company::factory()->create()->id]);
        Http::fake(['https://nms.test/api/v0/devices' => Http::response(['devices' => [$this->device()]])]);
        $preview = app(LibreNMSImportService::class)->preview($this->integration, $this->user);
        $this->assertNull($preview['analysis'][0]['site_id']);
        $this->assertSame('skip_unmapped', $preview['analysis'][0]['action']);
        $this->assertSame(1, $this->runImport([['external_id' => '42', 'site_id' => $this->site->id]])['skipped']);
    }

    public function test_both_routes_reject_missing_id_and_unauthorized_integration_before_fetch(): void
    {
        Http::fake();
        foreach (["/api/v1/integrations/librenms/{$this->integration->id}/import", "/api/v1/integrations/{$this->integration->id}/import"] as $url) {
            $this->actingAs($this->user)->postJson($url, ['devices' => [['hostname' => 'spoof']]])->assertUnprocessable();
            $otherUser = User::factory()->create();
            $otherUser->givePermissionTo('librenms.import');
            $this->actingAs($otherUser)->postJson($url, ['devices' => [['external_id' => '42']]])->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_provider_failure_never_falls_back_to_browser(): void
    {
        Http::fake(['https://nms.test/api/v0/devices' => Http::response([], 503)]);
        $this->actingAs($this->user)->postJson("/api/v1/integrations/librenms/{$this->integration->id}/import", [
            'devices' => [['external_id' => '42'] + $this->device()],
        ])->assertStatus(500);
        $this->assertSame(0, AssetExternalReference::where('integration_id', $this->integration->id)->count());
        $this->assertSame('failed', ImportHistory::latest('id')->first()->status);
        Http::assertSentCount(1);
    }

    public function test_both_execute_routes_ignore_spoofed_attributes_and_are_idempotent(): void
    {
        Http::fake(['https://nms.test/api/v0/devices' => Http::response(['devices' => [$this->device()]])]);
        foreach (["/api/v1/integrations/librenms/{$this->integration->id}/import", "/api/v1/integrations/{$this->integration->id}/import"] as $index => $url) {
            $this->actingAs($this->user)->postJson($url, ['devices' => [[
                'external_id' => '42', 'name' => 'forged', 'display' => 'forged', 'os' => 'forged',
                'hardware' => 'forged', 'serial' => 'forged', 'site_id' => 999999,
            ]]])->assertOk()->assertJsonPath('data.'.($index === 0 ? 'created' : 'updated'), 1);
        }
        $asset = AssetExternalReference::where('integration_id', $this->integration->id)->sole()->asset;
        $this->assertSame('Provider Name', $asset->device_name);
        $this->assertSame('routeros', $asset->specifications['observed_os']);
        Http::assertSentCount(2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'librenms.devices.import', 'actor_id' => $this->user->id]);
    }

    public function test_invalid_or_empty_provider_response_never_uses_browser_record(): void
    {
        Http::fake(['https://nms.test/api/v0/devices' => Http::sequence()
            ->push(['devices' => []])
            ->push(['unexpected' => []])]);
        $selection = [['external_id' => '42'] + $this->device()];
        $result = $this->runImport($selection);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, $result['created']);
        $this->expectException(\RuntimeException::class);
        $this->runImport($selection);
    }

    public function test_name_precedence_trims_and_rejects_ip_only_hostname(): void
    {
        foreach ([
            [['display' => ' D ', 'sysName' => 'S'], 'D'],
            [['display' => ' ', 'sysName' => ' S '], 'S'],
            [['display' => ' ', 'sysName' => ' ', 'hostname' => ' host '], 'host'],
            [['hostname' => ' 192.0.2.1 '], null],
            [['hostname' => '2001:db8::1'], null],
            [[], null],
        ] as [$device, $name]) {
            $this->assertSame($name, LibreNMSImportService::deviceName($device));
        }
    }

    public function test_integer_and_numeric_string_statuses_are_normalized_in_preview_and_storage(): void
    {
        $devices = [];
        foreach ([1, '1', 0, '0', 7] as $index => $status) {
            $devices[] = $this->device((string) $index, ['status' => $status]);
        }
        Http::fake(['https://nms.test/api/v0/devices' => Http::response(['devices' => $devices])]);
        $preview = app(LibreNMSImportService::class)->preview($this->integration, $this->user);
        $this->assertSame(['UP', 'UP', 'DOWN', 'DOWN', 'UNKNOWN'], array_column($preview['analysis'], 'status'));
        $this->assertSame(5, $this->runImport(array_map(fn ($d) => ['external_id' => $d['device_id']], $devices))['created']);
        foreach (['UP', 'UP', 'DOWN', 'DOWN', 'UNKNOWN'] as $index => $status) {
            $asset = AssetExternalReference::where('external_id', (string) $index)->firstOrFail()->asset;
            $this->assertSame($status, $asset->specifications['provider_status']);
        }
    }

    public function test_legacy_unscoped_reference_is_not_reassigned_and_ambiguous_site_is_skipped(): void
    {
        $legacy = Asset::factory()->create(['serial_number' => null]);
        $ref = AssetExternalReference::create(['asset_id' => $legacy->id, 'provider' => 'librenms',
            'external_type' => 'device', 'external_id' => '42']);
        Http::fake(['https://nms.test/api/v0/devices' => Http::response(['devices' => [$this->device('42', ['serial' => null])]])]);
        $this->assertSame(1, $this->runImport([['external_id' => '42']])['created']);
        $this->assertNull($ref->fresh()->integration_id);
        $this->assertSame($legacy->id, $ref->fresh()->asset_id);

        Site::factory()->create(['company_id' => $this->integration->company_id, 'name' => $this->site->name]);
        $this->assertSame(1, $this->runImport([['external_id' => '42']])['skipped']);
    }

    public function test_duplicate_scoped_reference_is_rejected_by_database(): void
    {
        Http::fake(['https://nms.test/api/v0/devices' => Http::response(['devices' => [$this->device()]])]);
        $this->runImport([['external_id' => '42']]);
        $reference = AssetExternalReference::where('integration_id', $this->integration->id)->firstOrFail();
        $this->expectException(QueryException::class);
        DB::transaction(fn () => $reference->replicate()->save());
    }

    public function test_uniqueness_migration_rolls_back_and_reapplies_on_test_database(): void
    {
        $this->assertSame('ewnet_test', DB::selectOne('select current_database() as name')->name);
        $migration = require database_path('migrations/2026_09_13_100001_scope_librenms_asset_reference_uniqueness.php');
        $migration->down();
        $migration->up();
        $this->assertNotEmpty(DB::select("select indexname from pg_indexes where indexname = 'asset_refs_librenms_integration_unique'"));
    }
}
