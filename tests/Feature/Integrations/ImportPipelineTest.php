<?php

namespace Tests\Feature\Integrations;

use App\Models\Asset;
use App\Models\Company;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\Site;
use App\Models\SiteExternalReference;
use App\Models\User;
use App\Services\Integrations\Uisp\UispImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    private array $company;

    private array $admin;

    private User $viewUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->company['A'] = Company::factory()->create();
        $this->company['B'] = Company::factory()->create();

        $this->admin['uisp'] = User::factory()->create(['company_id' => $this->company['A']->id]);
        $this->admin['uisp']->givePermissionTo('integration.uisp.import');

        $this->admin['librenms'] = User::factory()->create(['company_id' => $this->company['A']->id]);
        $this->admin['librenms']->givePermissionTo('librenms.import');

        $this->viewUser = User::factory()->create(['company_id' => $this->company['A']->id]);
        $this->viewUser->givePermissionTo('integrations.view');
    }

    private function uispIntegration(): Integration
    {
        $integration = Integration::factory()->forCompany($this->company['A']->id)->create([
            'provider' => 'uisp',
            'enabled' => true,
            'configuration' => ['api_url' => 'https://uisp.test/api'],
        ]);

        $cred = new IntegrationCredential([
            'integration_id' => $integration->id,
            'credential_type' => 'api_token',
            'label' => 'Primary',
            'is_active' => true,
        ]);
        $cred->setSecretValue('uisp-token-abc');
        $cred->save();

        return $integration;
    }

    private function librenmsIntegration(): Integration
    {
        return Integration::factory()->forCompany($this->company['A']->id)->create([
            'provider' => 'librenms',
            'enabled' => true,
            'configuration' => ['api_url' => 'https://nms.test'],
        ]);
    }

    public function test_uisp_preview_returns_sites_and_devices(): void
    {
        Http::fake([
            'https://uisp.test/api/sites' => Http::response([
                ['id' => 'site-1', 'name' => 'Site One'],
            ]),
            'https://uisp.test/api/devices' => Http::response([
                ['id' => 'device-1', 'identification' => ['name' => 'Dev One']],
            ]),
        ]);

        $integration = $this->uispIntegration();

        $response = $this->actingAs($this->admin['uisp'])
            ->postJson("/api/v1/integrations/{$integration->id}/uisp/import/preview");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.sites.total', 1);
        $response->assertJsonPath('data.devices.total', 1);
        $response->assertJsonPath('data.sites.analysis.0.action', 'create');
    }

    public function test_uisp_execute_creates_site_and_device_with_company_inheritance(): void
    {
        Http::fake();

        $integration = $this->uispIntegration();

        $response = $this->actingAs($this->admin['uisp'])
            ->postJson("/api/v1/integrations/{$integration->id}/uisp/import/execute", [
                'sites' => [
                    ['external_id' => 'site-9', 'name' => 'Ward Site'],
                ],
                'devices' => [
                    [
                        'external_id' => 'device-9',
                        'name' => 'AP-9',
                        'site_external_id' => 'site-9',
                        'interfaces' => [],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('sites', [
            'name' => 'Ward Site',
            'company_id' => $this->company['A']->id,
        ]);
        $this->assertDatabaseHas('site_external_references', [
            'provider' => 'uisp',
            'external_type' => 'site',
            'external_id' => 'site-9',
        ]);
        $this->assertDatabaseHas('assets', [
            'asset_tag' => 'UISP-device-9',
            'category' => 'NETWORK',
        ]);

        $this->assertDatabaseHas('import_history', [
            'source' => 'uisp',
            'integration_id' => $integration->id,
            'status' => 'completed',
        ]);
    }

    public function test_uisp_site_and_device_respect_action_from_analysis(): void
    {
        Http::fake();

        $integration = $this->uispIntegration();

        $response = $this->actingAs($this->admin['uisp'])
            ->postJson("/api/v1/integrations/{$integration->id}/uisp/import/execute", [
                'sites' => [
                    ['external_id' => 'site-2', 'name' => 'Second Site'],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sites', ['name' => 'Second Site']);
        $history = ImportHistory::where('integration_id', $integration->id)->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertSame(1, $history->created_records);
    }

    public function test_librenms_preview_returns_analysis_for_device_resource(): void
    {
        Http::fake([
            'https://nms.test/api/v0/devices' => Http::response([
                'devices' => [
                    [
                        'device_id' => '1001',
                        'hostname' => 'edge-rtr-01',
                        'sysName' => 'KR-EDGE-01',
                        'location' => 'Kathmandu POP',
                        'ip' => '10.0.0.1',
                        'os' => 'ios',
                        'type' => 'router',
                        'hardware' => 'ASR9001',
                    ],
                ],
            ]),
        ]);

        $integration = $this->librenmsIntegration();

        Site::create([
            'site_code' => 'KTM-POP',
            'name' => 'Kathmandu POP',
            'type' => 'pop',
            'status' => 'active',
            'company_id' => $this->company['A']->id,
        ]);

        $response = $this->actingAs($this->viewUser)
            ->postJson("/api/v1/integrations/{$integration->id}/import/preview", [
                'resource_type' => 'device',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.analysis.0.hostname', 'edge-rtr-01');
        $response->assertJsonPath('data.analysis.0.site_name', 'Kathmandu POP');
    }

    public function test_librenms_import_creates_assets_and_history(): void
    {
        Http::fake();

        $integration = $this->librenmsIntegration();

        $site = Site::create([
            'site_code' => 'KTM-POP',
            'name' => 'Kathmandu POP',
            'type' => 'pop',
            'status' => 'active',
            'company_id' => $this->company['A']->id,
        ]);

        SiteExternalReference::create([
            'site_id' => $site->id,
            'provider' => 'librenms',
            'external_type' => 'device',
            'external_id' => '1001',
        ]);

        $response = $this->actingAs($this->admin['librenms'])
            ->postJson("/api/v1/integrations/{$integration->id}/import", [
                'devices' => [
                    [
                        'device_id' => '1001',
                        'external_id' => '1001',
                        'hostname' => 'edge-rtr-01',
                        'sysName' => 'KR-EDGE-01',
                        'os' => 'ios',
                        'type' => 'router',
                        'hardware' => 'ASR9001',
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('assets', [
            'asset_tag' => 'LNM-1001',
            'site_id' => $site->id,
        ]);
        $this->assertDatabaseHas('import_history', [
            'source' => 'librenms',
            'integration_id' => $integration->id,
            'type' => 'device',
            'status' => 'completed',
        ]);
    }

    public function test_librenms_site_import_sets_company_on_created_site(): void
    {
        Http::fake([
            'https://nms.test/api/v0/devices' => Http::response([
                'devices' => [
                    ['device_id' => '5', 'hostname' => 'solar-01', 'location' => 'Pokhara Solar'],
                ],
            ]),
        ]);

        $integration = $this->librenmsIntegration();

        $response = $this->actingAs($this->admin['librenms'])
            ->postJson("/api/v1/integrations/librenms/{$integration->id}/sites/import", [
                'sites' => [
                    ['external_id' => 'Pokhara Solar', 'name' => 'Pokhara Solar'],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sites', [
            'name' => 'Pokhara Solar',
            'company_id' => $this->company['A']->id,
        ]);
        $this->assertDatabaseHas('import_history', [
            'source' => 'librenms',
            'type' => 'site',
            'status' => 'completed',
        ]);
    }

    public function test_user_from_other_company_cannot_execute_import(): void
    {
        Http::fake();

        $integration = $this->uispIntegration();

        $otherCompanyUser = User::factory()->create(['company_id' => $this->company['B']->id]);
        $otherCompanyUser->givePermissionTo('integration.uisp.import');

        $response = $this->actingAs($otherCompanyUser)
            ->postJson("/api/v1/integrations/{$integration->id}/uisp/import/execute", [
                'devices' => [['external_id' => 'device-x', 'name' => 'X']],
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_execute_sanitizes_internal_errors(): void
    {
        $integration = $this->uispIntegration();

        $this->app->bind(UispImportService::class, function ($app) {
            $mock = $this->createMock(UispImportService::class);
            $mock->expects($this->once())
                ->method('execute')
                ->willThrowException(new \RuntimeException('root-password-leak'));

            return $mock;
        });

        $response = $this->actingAs($this->admin['uisp'])
            ->postJson("/api/v1/integrations/{$integration->id}/uisp/import/execute", [
                'devices' => [['external_id' => 'device-x', 'name' => 'X']],
            ]);

        $response->assertStatus(500);
        $response->assertJsonMissing(['error' => 'root-password-leak']);
        $response->assertJsonMissing(['error' => 'RuntimeException']);
        $this->assertDatabaseHas('import_history', [
            'integration_id' => $integration->id,
            'status' => 'failed',
        ]);
    }

    public function test_imported_asset_references_company_site(): void
    {
        Http::fake();

        $integration = $this->uispIntegration();

        $site = Site::create([
            'site_code' => 'ACME-POP',
            'name' => 'ACME POP',
            'type' => 'pop',
            'status' => 'active',
            'company_id' => $this->company['A']->id,
        ]);

        $this->actingAs($this->admin['uisp'])
            ->postJson("/api/v1/integrations/{$integration->id}/uisp/import/execute", [
                'devices' => [
                    [
                        'external_id' => 'device-77',
                        'name' => 'CPE-77',
                        'site_id' => $site->id,
                        'interfaces' => [],
                    ],
                ],
            ])->assertStatus(200);

        $asset = Asset::where('asset_tag', 'UISP-device-7')->first();
        $this->assertNotNull($asset);
        $this->assertSame($site->id, $asset->site_id);
    }

    public function test_uisp_device_creates_missing_parent_site_from_flattened_site_info(): void
    {
        Http::fake();

        $integration = $this->uispIntegration();

        $response = $this->actingAs($this->admin['uisp'])
            ->postJson("/api/v1/integrations/{$integration->id}/uisp/import/execute", [
                'devices' => [
                    [
                        'external_id' => 'device-88',
                        'name' => 'CPE-88',
                        'site_external_id' => 'site-88',
                        'site_name' => 'Satellite POP',
                        'interfaces' => [],
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('sites', [
            'name' => 'Satellite POP',
            'company_id' => $this->company['A']->id,
        ]);
        $this->assertDatabaseHas('site_external_references', [
            'provider' => 'uisp',
            'external_type' => 'site',
            'external_id' => 'site-88',
        ]);

        $site = Site::where('name', 'Satellite POP')->first();
        $asset = Asset::where('asset_tag', 'UISP-device-8')->first();
        $this->assertNotNull($asset);
        $this->assertSame($site->id, $asset->site_id);
    }

    public function test_uisp_device_without_any_site_identifier_is_skipped_and_logged(): void
    {
        Http::fake();

        $integration = $this->uispIntegration();

        $response = $this->actingAs($this->admin['uisp'])
            ->postJson("/api/v1/integrations/{$integration->id}/uisp/import/execute", [
                'devices' => [
                    ['external_id' => 'device-99', 'name' => 'Unanchored-99', 'interfaces' => []],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('assets', 0);
        $this->assertDatabaseMissing('sites', ['name' => 'UISP Default Site']);

        $history = ImportHistory::where('integration_id', $integration->id)->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertSame('completed', $history->status);
        $this->assertSame(1, (int) $history->skipped_records);
    }

    public function test_uisp_device_preview_analysis_exposes_flattened_site_name_and_id(): void
    {
        Http::fake([
            'https://uisp.test/api/sites' => Http::response([
                ['id' => 'site-7', 'name' => 'Site Seven'],
            ]),
            'https://uisp.test/api/devices' => Http::response([
                [
                    'id' => 'device-7',
                    'identification' => [
                        'name' => 'CPE-7',
                        'site' => ['id' => 'site-7', 'name' => 'Site Seven'],
                    ],
                ],
            ]),
        ]);

        $integration = $this->uispIntegration();

        $response = $this->actingAs($this->viewUser)
            ->postJson("/api/v1/integrations/{$integration->id}/import/preview", [
                'resource_type' => 'device',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.devices.analysis.0.site_name', 'Site Seven');
        $response->assertJsonPath('data.devices.analysis.0.site_external_id', 'site-7');
        $response->assertJsonPath('data.devices.analysis.0.name', 'CPE-7');
    }

    public function test_uisp_device_with_only_site_external_id_resolves_parent_site(): void
    {
        Http::fake();

        $integration = $this->uispIntegration();

        $response = $this->actingAs($this->admin['uisp'])
            ->postJson("/api/v1/integrations/{$integration->id}/uisp/import/execute", [
                'devices' => [
                    [
                        'external_id' => 'device-101',
                        'name' => 'CPE-101',
                        'site_external_id' => 'site-101',
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('site_external_references', [
            'provider' => 'uisp',
            'external_type' => 'site',
            'external_id' => 'site-101',
        ]);

        $site = Site::whereHas('externalReferences', fn ($q) => $q->where('external_id', 'site-101'))->first();
        $this->assertNotNull($site);
        $this->assertSame($this->company['A']->id, $site->company_id);

        $asset = Asset::where('asset_tag', 'UISP-device-1')->first();
        $this->assertNotNull($asset);
        $this->assertSame($site->id, $asset->site_id);
    }

    public function test_uisp_asset_tag_collision_gets_unique_suffix(): void
    {
        Http::fake();

        $integration = $this->uispIntegration();
        $site = Site::create([
            'site_code' => 'COLL-POP',
            'name' => 'Collision POP',
            'type' => 'pop',
            'status' => 'active',
            'company_id' => $this->company['A']->id,
        ]);

        $this->actingAs($this->admin['uisp'])
            ->postJson("/api/v1/integrations/{$integration->id}/uisp/import/execute", [
                'devices' => [
                    ['external_id' => 'sharedtag-A', 'name' => 'CPE-A', 'site_id' => $site->id],
                    ['external_id' => 'sharedtag-B', 'name' => 'CPE-B', 'site_id' => $site->id],
                ],
            ])->assertStatus(200);

        $this->assertDatabaseHas('assets', ['asset_tag' => 'UISP-sharedta']);
        $this->assertDatabaseHas('assets', ['asset_tag' => 'UISP-sharedta-1']);
        $this->assertSame(2, Asset::where('site_id', $site->id)->count());
    }

    public function test_canonical_import_records_counts_for_uisp(): void
    {
        Http::fake();

        $integration = $this->uispIntegration();

        $response = $this->actingAs($this->admin['uisp'])
            ->postJson("/api/v1/integrations/{$integration->id}/import", [
                'sites' => [
                    ['external_id' => 'site-canon', 'name' => 'Canonical POP'],
                ],
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('sites', ['name' => 'Canonical POP']);

        $history = ImportHistory::where('integration_id', $integration->id)->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertSame('completed', $history->status);
        $this->assertSame(1, (int) $history->created_records);
    }
}
