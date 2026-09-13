<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\Company;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Models\Site;
use App\Models\SiteExternalReference;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\AuditService;
use App\Services\LibreNMSImportService;
use App\Services\SiteMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderObservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function createUserWithScope(Company $company): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');
        $user->givePermissionTo('librenms.import');
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        return $user->fresh();
    }

    protected function makeService(): LibreNMSImportService
    {
        return new LibreNMSImportService(
            app(SiteMappingService::class),
            app(AuditService::class)
        );
    }

    protected function makeHistory(Integration $integration, int $userId): ImportHistory
    {
        return ImportHistory::create([
            'source' => ImportHistory::SOURCE_LIBRENMS,
            'type' => ImportHistory::TYPE_DEVICE,
            'integration_id' => $integration->id,
            'status' => ImportHistory::STATUS_PENDING,
            'started_by' => $userId,
            'total_records' => 1,
        ]);
    }

    protected function mapDeviceToSite(Site $site, string $deviceId, Integration $integration): void
    {
        SiteExternalReference::create([
            'site_id' => $site->id,
            'provider' => 'librenms',
            'external_type' => 'device',
            'external_id' => $deviceId,
            'metadata' => ['integration_id' => $integration->id],
        ]);
    }

    protected function freshProvider(Integration $integration, array $device): void
    {
        $integration->update(['configuration' => ['api_url' => 'https://nms.test']]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://nms.test/api/v0/devices' => Http::response(['devices' => [$device]])]);
    }

    public function test_provider_observations_exposed_in_api_resource()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = $this->createUserWithScope($company);

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'OLT',
            'serial_number' => 'SN-OBS-API-001',
            'status' => 'OPERATIONAL',
            'specifications' => [
                'source' => 'librenms',
                'external_id' => '123',
                'provider_status' => 'UP',
                'observed_hostname' => 'test-host.example.com',
                'observed_os' => 'linux',
                'observed_hardware' => 'RB4011',
                'observed_version' => '7.8',
                'observed_uptime' => 99999,
                'ip_address' => '10.0.0.1',
                'serial_number' => 'SN-OBS-API-001',
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
                'last_observed_at' => '2026-09-12T10:00:00Z',
                'last_synced' => '2026-09-12T10:05:00Z',
            ],
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}");
        $response->assertStatus(200);

        $obs = $response->json('data.provider_observations');
        $this->assertNotNull($obs);
        $this->assertEquals('librenms', $obs['provider']);
        $this->assertEquals('123', $obs['external_id']);
        $this->assertEquals('UP', $obs['provider_status']);
        $this->assertEquals('test-host.example.com', $obs['observed_hostname']);
        $this->assertEquals('linux', $obs['observed_os']);
        $this->assertEquals('RB4011', $obs['observed_hardware']);
        $this->assertEquals('7.8', $obs['observed_version']);
        $this->assertEquals(99999, $obs['observed_uptime']);
        $this->assertEquals('10.0.0.1', $obs['ip_address']);
        $this->assertEquals('2026-09-12T10:00:00Z', $obs['last_observed_at']);
        $this->assertEquals('2026-09-12T10:05:00Z', $obs['last_synced']);
    }

    public function test_non_imported_asset_has_null_provider_observations()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = $this->createUserWithScope($company);

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'POWER',
            'type' => 'Battery',
            'serial_number' => 'SN-NO-OBS-001',
            'status' => 'OPERATIONAL',
            'specifications' => ['custom_field' => 'value'],
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}");
        $response->assertStatus(200);
        $this->assertNull($response->json('data.provider_observations'));
    }

    public function test_site_derived_organization_from_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = $this->createUserWithScope($company);

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => null,
            'category' => 'NETWORK',
            'type' => 'OLT',
            'serial_number' => 'SN-DERIVED-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}");
        $response->assertStatus(200);
        $response->assertJsonPath('data.site.company.id', $company->id);
    }

    public function test_forged_site_outside_scope_rejected()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $site2 = Site::factory()->create(['company_id' => $company2->id]);

        $user = User::factory()->create(['company_id' => $company1->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company1->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site2->id,
            'category' => 'NETWORK',
            'type' => 'OLT',
            'quantity' => 1,
            'serial_number' => 'SN-FORGED-SITE-001',
            'status' => 'OPERATIONAL',
        ]);

        $this->assertContains($response->status(), [403, 422]);
    }

    public function test_asset_status_unaffected_by_provider_observation()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = $this->createUserWithScope($company);

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'OLT',
            'serial_number' => 'SN-STATUS-001',
            'status' => 'OPERATIONAL',
            'condition' => 'GOOD',
            'specifications' => [
                'source' => 'librenms',
                'external_id' => '456',
                'provider_status' => 'DOWN',
                'last_observed_at' => '2026-09-12T12:00:00Z',
            ],
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}");
        $response->assertStatus(200);

        $response->assertJsonPath('data.status', 'OPERATIONAL');
        $response->assertJsonPath('data.condition', 'GOOD');
        $response->assertJsonPath('data.provider_observations.provider_status', 'DOWN');
    }

    public function test_import_service_stores_observations_on_create()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = $this->createUserWithScope($company);
        $integration = Integration::factory()->create(['provider' => 'librenms', 'company_id' => $company->id]);

        $this->mapDeviceToSite($site, '999', $integration);

        $device = [
            'external_id' => '999',
            'device_id' => '999',
            'hostname' => 'test-router.example.com',
            'ip' => '10.0.0.1',
            'os' => 'linux',
            'hardware' => 'RB4011',
            'serial' => 'SN-TEST-999',
            'status' => 'UP',
            'version' => '7.8',
            'uptime' => 123456,
        ];

        $history = $this->makeHistory($integration, $user->id);
        $this->freshProvider($integration, $device);
        $results = $this->makeService()->execute($integration, $user, [$device], $history);

        $this->assertNotEquals(0, $results['created'] + $results['updated'] + $results['skipped'] + $results['failed'],
            'Results: '.json_encode($results));

        // Import should create at least one asset
        $this->assertEquals(1, $results['created'], 'Expected 1 created, got: '.json_encode($results));

        $asset = Asset::whereJsonContains('specifications->external_id', '999')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('UP', $asset->specifications['provider_status']);
        $this->assertEquals('linux', $asset->specifications['observed_os']);
        $this->assertEquals('RB4011', $asset->specifications['observed_hardware']);
        $this->assertEquals('7.8', $asset->specifications['observed_version']);
        $this->assertEquals(123456, $asset->specifications['observed_uptime']);
        $this->assertNotNull($asset->specifications['last_observed_at']);
        $this->assertEquals('OPERATIONAL', $asset->status);
        $this->assertEquals('GOOD', $asset->condition);
    }

    public function test_import_service_stores_observations_on_resync()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = $this->createUserWithScope($company);
        $integration = Integration::factory()->create(['provider' => 'librenms', 'company_id' => $company->id]);

        $this->mapDeviceToSite($site, '500', $integration);

        $service = $this->makeService();
        $device = [
            'external_id' => '500',
            'device_id' => '500',
            'hostname' => 'router-a.example.com',
            'ip' => '10.0.0.50',
            'status' => 'UP',
        ];

        $h1 = $this->makeHistory($integration, $user->id);
        $this->freshProvider($integration, $device);
        $service->execute($integration, $user, [$device], $h1);

        $asset = Asset::whereJsonContains('specifications->external_id', '500')->first();
        $this->assertNotNull($asset, 'Asset must be created on first import');
        $this->assertEquals('UP', $asset->specifications['provider_status']);

        $device['status'] = 'DOWN';
        $device['version'] = '8.0';
        $h2 = $this->makeHistory($integration, $user->id);
        $this->freshProvider($integration, $device);
        $r2 = $service->execute($integration, $user, [$device], $h2);

        $asset->refresh();
        $this->assertEquals(1, $r2['updated'], 'Should update existing asset');
        $this->assertEquals('DOWN', $asset->specifications['provider_status']);
        $this->assertEquals('8.0', $asset->specifications['observed_version']);
        $this->assertEquals('OPERATIONAL', $asset->status, 'Authoritative status must not change');
        $this->assertEquals('GOOD', $asset->condition, 'Condition must not change');
    }

    public function test_resync_does_not_duplicate_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = $this->createUserWithScope($company);
        $integration = Integration::factory()->create(['provider' => 'librenms', 'company_id' => $company->id]);

        $this->mapDeviceToSite($site, '777', $integration);

        $service = $this->makeService();
        $device = [
            'external_id' => '777',
            'device_id' => '777',
            'hostname' => 'dup-test.example.com',
            'ip' => '10.0.0.77',
            'status' => 'UP',
        ];

        $h1 = $this->makeHistory($integration, $user->id);
        $this->freshProvider($integration, $device);
        $service->execute($integration, $user, [$device], $h1);

        $asset = Asset::whereJsonContains('specifications->external_id', '777')->first();
        $this->assertNotNull($asset, 'Asset must exist after first import');

        $device['status'] = 'DOWN';
        $h2 = $this->makeHistory($integration, $user->id);
        $this->freshProvider($integration, $device);
        $r2 = $service->execute($integration, $user, [$device], $h2);

        $count = Asset::whereJsonContains('specifications->external_id', '777')->count();
        $this->assertEquals(1, $count, 'No duplicate on re-import');
        $this->assertEquals(1, $r2['updated']);

        $refCount = AssetExternalReference::where('provider', 'librenms')
            ->where('external_id', '777')->count();
        $this->assertEquals(1, $refCount);
    }

    public function test_authoritative_identity_not_overwritten_by_reimport()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = $this->createUserWithScope($company);
        $integration = Integration::factory()->create(['provider' => 'librenms', 'company_id' => $company->id]);

        $this->mapDeviceToSite($site, '888', $integration);

        $service = $this->makeService();
        $device = [
            'external_id' => '888',
            'device_id' => '888',
            'hostname' => 'original-name.example.com',
            'ip' => '10.0.0.88',
            'serial' => 'SN-ORIG-888',
            'status' => 'UP',
        ];

        $h1 = $this->makeHistory($integration, $user->id);
        $this->freshProvider($integration, $device);
        $service->execute($integration, $user, [$device], $h1);

        $asset = Asset::whereJsonContains('specifications->external_id', '888')->first();
        $this->assertNotNull($asset, 'Asset must exist after first import');

        $originalTag = $asset->asset_tag;
        $originalSerial = $asset->serial_number;

        $device['hostname'] = 'new-name.example.com';
        $device['serial'] = 'SN-NEW-888';
        $device['status'] = 'DOWN';
        $h2 = $this->makeHistory($integration, $user->id);
        $this->freshProvider($integration, $device);
        $service->execute($integration, $user, [$device], $h2);

        $asset->refresh();
        $this->assertEquals($originalTag, $asset->asset_tag, 'asset_tag must not change');
        $this->assertEquals($originalSerial, $asset->serial_number, 'serial_number must not change');
        $this->assertEquals('DOWN', $asset->specifications['provider_status']);
        $this->assertEquals('original-name.example.com', $asset->device_name);
    }
}
