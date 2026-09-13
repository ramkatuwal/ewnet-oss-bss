<?php

namespace Tests\Feature\Observations;

use App\Jobs\RunObservationSync;
use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\Company;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\NetworkPort;
use App\Models\ObservedVlan;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Models\Vlan;
use App\Services\Observations\ObservationSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ObservationSyncTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private User $viewer;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->company = Company::factory()->create();
        $this->site = Site::factory()->create(['company_id' => $this->company->id]);

        $this->admin = User::factory()->create(['company_id' => $this->company->id]);
        $this->admin->givePermissionTo('integrations.sync', 'assets.observations.sync', 'assets.view');
        $this->scope($this->admin);

        $this->viewer = User::factory()->create(['company_id' => $this->company->id]);
        $this->viewer->givePermissionTo('assets.view');
        $this->scope($this->viewer);
    }

    private function scope(User $user): void
    {
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $this->company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();
    }

    private function integration(): Integration
    {
        $integration = Integration::factory()->forCompany($this->company->id)->create([
            'provider' => 'librenms',
            'enabled' => true,
            'configuration' => ['api_url' => 'https://nms.test'],
        ]);

        $cred = new IntegrationCredential([
            'integration_id' => $integration->id,
            'credential_type' => 'api_token',
            'label' => 'Primary',
            'is_active' => true,
        ]);
        $cred->setSecretValue('token-abc');
        $cred->save();

        return $integration;
    }

    private function assetForDevice(int $deviceId, int $integrationId): Asset
    {
        $asset = Asset::factory()->create([
            'site_id' => $this->site->id,
            'company_id' => $this->company->id,
            'category' => 'NETWORK',
            'type' => 'Router',
        ]);

        AssetExternalReference::create([
            'asset_id' => $asset->id,
            'provider' => 'librenms',
            'external_type' => 'device',
            'external_id' => $deviceId,
            'integration_id' => $integrationId,
            'metadata' => ['imported_at' => now()],
        ]);

        return $asset;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $ports
     */
    private function fakeLibreNMS(?array $ports = null): void
    {
        // Http::fake() appends stubs (first match wins), so swap in a clean
        // client factory first to make per-test provider payloads deterministic.
        Http::swap(new Factory);

        Http::fake([
            'https://nms.test/api/v0/devices' => Http::response([
                'status' => 'ok',
                'devices' => [
                    ['device_id' => 327, 'hostname' => 'amargadi', 'display' => 'AMARGADI'],
                ],
            ]),
            'https://nms.test/api/v0/devices/327/ports*' => Http::response([
                'status' => 'ok',
                'ports' => $ports ?? [
                    [
                        'port_id' => 100, 'device_id' => 327, 'ifIndex' => 1, 'ifName' => 'ether1',
                        'ifDescr' => 'ether1', 'ifAlias' => 'ether1', 'ifType' => 'ethernetCsmacd',
                        'ifSpeed' => 1000000000, 'ifAdminStatus' => 'up', 'ifOperStatus' => 'up',
                        'ifPhysAddress' => '488f5a0ff1c6', 'ifVlan' => '1', 'ifMtu' => 1500,
                    ],
                    [
                        'port_id' => 101, 'device_id' => 327, 'ifIndex' => 2, 'ifName' => 'ether2-powerbox',
                        'ifDescr' => 'ether2-powerbox', 'ifAlias' => 'ether2-powerbox', 'ifType' => 'ethernetCsmacd',
                        'ifSpeed' => 100000000, 'ifAdminStatus' => 'up', 'ifOperStatus' => 'down',
                        'ifPhysAddress' => '488f5a0ff1c7', 'ifVlan' => '2', 'ifMtu' => 1500,
                    ],
                    [
                        'port_id' => 102, 'device_id' => 327, 'ifIndex' => 3, 'ifName' => 'vlan_20_mgt',
                        'ifDescr' => 'vlan_20_mgt', 'ifAlias' => 'mgt', 'ifType' => 'other',
                        'ifSpeed' => null, 'ifAdminStatus' => 'up', 'ifOperStatus' => 'up',
                        'ifPhysAddress' => null, 'ifVlan' => null, 'ifMtu' => 1500,
                    ],
                ],
            ]),
            'https://nms.test/api/v0/devices/327/vlans*' => Http::response([
                'status' => 'ok',
                'count' => 2,
                'vlans' => [
                    ['vlan_num' => 20, 'vlan_name' => 'mgt', 'vlan_type' => 'static'],
                    ['vlan_num' => 850, 'vlan_name' => 'skt', 'vlan_type' => 'static'],
                ],
            ]),
            'https://nms.test/api/v0/ports/*/ip*' => Http::response([
                'status' => 'ok',
                'count' => 1,
                'addresses' => [
                    ['ip' => '103.154.13.10', 'cidr' => '24'],
                ],
            ]),
        ]);
    }

    public function test_run_creates_interfaces_with_mac_vlan_and_observations(): void
    {
        $this->fakeLibreNMS();

        $integration = $this->integration();
        $asset = $this->assetForDevice(327, $integration->id);

        $result = app(ObservationSyncService::class)->run($integration, 'all');

        $this->assertSame(1, $result['devices_seen']);
        $this->assertSame(3, $result['interfaces_created']);
        $this->assertSame(0, $result['interfaces_failed']);

        $interfaces = $asset->interfaces()->get()->keyBy('name');

        $ether1 = $interfaces['ether1'];
        $this->assertSame('observed', $ether1->observation_status);
        $this->assertSame('48:8F:5A:0F:F1:C6', $ether1->mac_address);
        $this->assertSame('up', $ether1->status);
        $this->assertSame('librenms', $ether1->provider);
        $this->assertSame('port', $ether1->external_type);
        $this->assertSame('100', $ether1->external_id);
        $this->assertSame(1000000000, $ether1->speed);
        $this->assertNotNull($ether1->last_seen_at);

        // MAC is provider-neutral and normalized even when LibreNMS omits it.
        $this->assertNull($interfaces['vlan_20_mgt']->mac_address);

        // Observed VLANs: 2 device-level + access vlans 1 and 2 from ports.
        $vlans = ObservedVlan::where('asset_id', $asset->id)->get()->keyBy('vid');
        $this->assertCount(4, $vlans);

        $this->assertSame('mgt', $vlans[20]->name);
        $this->assertSame('device', $vlans[20]->external_type);

        $this->assertSame('access', $vlans[1]->vlan_type);
        $this->assertSame('observed', $vlans[1]->observation_status);
    }

    public function test_run_reconciles_interfaces_to_authoritative_network_ports(): void
    {
        $this->fakeLibreNMS();

        $integration = $this->integration();
        $asset = $this->assetForDevice(327, $integration->id);

        $networkPort = NetworkPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $this->company->id,
            'port_key' => 'ether1',
            'name' => 'ether1',
        ]);

        $result = app(ObservationSyncService::class)->run($integration, 'interfaces');

        $ether1 = $asset->interfaces()->where('name', 'ether1')->first();
        $this->assertSame($networkPort->id, $ether1->reconciled_network_port_id);
        $this->assertSame(1, $result['ports_reconciled']);

        // Unreconciled interface stays honest.
        $ether2 = $asset->interfaces()->where('name', 'ether2-powerbox')->first();
        $this->assertNull($ether2->reconciled_network_port_id);
    }

    public function test_run_is_idempotent_and_propagates_stale(): void
    {
        $this->fakeLibreNMS();

        $integration = $this->integration();
        $asset = $this->assetForDevice(327, $integration->id);

        Carbon::setTestNow('2026-09-13 10:00:00');
        app(ObservationSyncService::class)->run($integration, 'all');
        $first = $asset->interfaces()->count();
        $this->assertSame(3, $first);

        // Second run must not duplicate anything.
        Carbon::setTestNow('2026-09-13 10:05:00');
        $result = app(ObservationSyncService::class)->run($integration, 'all');
        $this->assertSame(0, $result['interfaces_created']);
        $this->assertGreaterThanOrEqual(3, $result['interfaces_updated']);
        $this->assertSame(3, $asset->interfaces()->count());

        // A provider that stops reporting a port marks it stale — never deletes.
        $this->fakeLibreNMS([
            [
                'port_id' => 100, 'device_id' => 327, 'ifIndex' => 1, 'ifName' => 'ether1',
                'ifDescr' => 'ether1', 'ifAlias' => 'ether1', 'ifType' => 'ethernetCsmacd',
                'ifSpeed' => 1000000000, 'ifAdminStatus' => 'up', 'ifOperStatus' => 'up',
                'ifPhysAddress' => '488f5a0ff1c6', 'ifVlan' => '1', 'ifMtu' => 1500,
            ],
        ]);

        Carbon::setTestNow('2026-09-13 10:10:00');
        $result = app(ObservationSyncService::class)->run($integration, 'all');

        $this->assertSame(2, $result['stale_interfaces']);
        $stale = $asset->interfaces()->where('name', 'ether2-powerbox')->first();
        $this->assertSame('stale', $stale->observation_status);
        $this->assertSame('stale', $asset->interfaces()->where('name', 'vlan_20_mgt')->first()->observation_status);
        $this->assertSame('observed', $asset->interfaces()->where('name', 'ether1')->first()->observation_status);
        Carbon::setTestNow();
    }

    public function test_run_reconciles_observed_vlans_to_authoritative_vlans(): void
    {
        $this->fakeLibreNMS();

        $integration = $this->integration();
        $asset = $this->assetForDevice(327, $integration->id);

        $vlan = Vlan::create([
            'company_id' => $this->company->id,
            'vid' => 20,
            'name' => 'Management',
            'reserved' => false,
        ]);

        app(ObservationSyncService::class)->run($integration, 'vlans');

        $observed = ObservedVlan::where('asset_id', $asset->id)->where('vid', 20)->first();
        $this->assertSame($vlan->id, $observed->reconciled_vlan_id);

        // No authoritative row exists for vid 850 → stays unreconciled.
        $this->assertNull(ObservedVlan::where('asset_id', $asset->id)->where('vid', 850)->first()->reconciled_vlan_id);
    }

    public function test_run_creates_observed_addresses_on_enabled_integration(): void
    {
        $this->fakeLibreNMS();

        $integration = $this->integration();
        $integration->update(['configuration' => array_merge($integration->configuration, ['observe_ip_addresses' => true])]);

        $asset = $this->assetForDevice(327, $integration->id);

        app(ObservationSyncService::class)->run($integration, 'interfaces');

        $ether1 = $asset->interfaces()->where('name', 'ether1')->first();
        $address = $ether1->ipAddresses()->where('ip_address', '103.154.13.10')->first();

        $this->assertNotNull($address);
        $this->assertSame(24, $address->prefix_length);
        $this->assertSame('observed', $address->observation_status);
        $this->assertSame('librenms', $address->provider);
    }

    public function test_lock_blocks_duplicate_concurrent_sync(): void
    {
        $this->fakeLibreNMS();

        $integration = $this->integration();
        $asset = $this->assetForDevice(327, $integration->id);

        // First run keeps the lock while executing (array store is process-local
        // and serialized, so we simulate an already-running sync directly).
        Cache::lock("observation-sync:{$integration->id}:all", 1800)->get();

        $result = app(ObservationSyncService::class)->run($integration, 'all');

        $this->assertTrue($result['locked']);
        $this->assertSame(0, $asset->interfaces()->count());
    }

    public function test_observation_sync_endpoint_dispatches_and_validates(): void
    {
        $this->fakeLibreNMS();
        Queue::fake([RunObservationSync::class]);

        $integration = $this->integration();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/integrations/{$integration->id}/observation-sync", ['category' => 'interfaces']);

        $response->assertCreated();
        $response->assertJsonPath('data.operation', 'interfaces');
        $response->assertJsonPath('data.status', 'pending');

        Queue::assertPushed(RunObservationSync::class);

        $this->assertSame(1, $integration->syncs()->where('operation', 'interfaces')->count());

        // Invalid category rejected.
        $this->actingAs($this->admin)
            ->postJson("/api/v1/integrations/{$integration->id}/observation-sync", ['category' => 'bogus'])
            ->assertStatus(422);

        // Viewers without integrations.sync are forbidden.
        $this->actingAs($this->viewer)
            ->postJson("/api/v1/integrations/{$integration->id}/observation-sync", ['category' => 'vlans'])
            ->assertForbidden();
    }

    public function test_observed_vlans_endpoint_returns_reconciled_data(): void
    {
        $this->fakeLibreNMS();

        $integration = $this->integration();
        $asset = $this->assetForDevice(327, $integration->id);

        Vlan::create([
            'company_id' => $this->company->id,
            'vid' => 20,
            'name' => 'Management',
            'reserved' => false,
        ]);

        app(ObservationSyncService::class)->run($integration, 'all');

        $response = $this->actingAs($this->viewer)
            ->getJson("/api/v1/assets/{$asset->id}/observed-vlans")
            ->assertOk();

        $vlans = collect($response->json('data'));

        $this->assertCount(4, $vlans);
        $mgt = $vlans->firstWhere('vid', 20);
        $this->assertSame('Management', $mgt['reconciled_vlan']['name'] ?? null);

        // Interfaces endpoint exposes freshness + reconciliation.
        $this->actingAs($this->viewer)
            ->getJson("/api/v1/assets/{$asset->id}/interfaces")
            ->assertOk();
    }

    public function test_observation_reconciliation_requires_permission_and_stays_within_asset_scope(): void
    {
        $this->fakeLibreNMS();
        $integration = $this->integration();
        $asset = $this->assetForDevice(327, $integration->id);
        app(ObservationSyncService::class)->run($integration, 'all');

        $interface = $asset->interfaces()->firstOrFail();
        $port = NetworkPort::create([
            'asset_id' => $asset->id,
            'company_id' => $this->company->id,
            'port_key' => 'reconcile-test-port',
            'name' => 'reconcile-test-port',
        ]);

        $this->actingAs($this->viewer)
            ->putJson("/api/v1/assets/{$asset->id}/interfaces/{$interface->id}/reconcile", ['network_port_id' => $port->id])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->putJson("/api/v1/assets/{$asset->id}/interfaces/{$interface->id}/reconcile", ['network_port_id' => $port->id])
            ->assertOk()
            ->assertJsonPath('data.reconciled_network_port_id', $port->id);

        $this->actingAs($this->admin)
            ->deleteJson("/api/v1/assets/{$asset->id}/interfaces/{$interface->id}/reconcile")
            ->assertOk()
            ->assertJsonPath('data.reconciled_network_port_id', null);
    }

    public function test_uisp_observation_source_creates_interface_observations(): void
    {
        $company = $this->company;

        $integration = Integration::factory()->forCompany($company->id)->create([
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

        $asset = Asset::factory()->create([
            'site_id' => $this->site->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'Router',
        ]);

        AssetExternalReference::create([
            'asset_id' => $asset->id,
            'provider' => 'uisp',
            'external_type' => 'device',
            'external_id' => 'device-1',
            'integration_id' => $integration->id,
        ]);

        Http::fake([
            'https://uisp.test/api/devices' => Http::response([
                ['id' => 'device-1', 'identification' => ['id' => 'device-1']],
            ]),
            'https://uisp.test/api/devices/device-1/interfaces' => Http::response([
                [
                    'id' => 'iface-1',
                    'identification' => ['id' => 'iface-1'],
                    'attributes' => ['name' => 'eth0', 'mac' => '001122334455', 'mtu' => 1500],
                ],
                [
                    'id' => 'iface-2',
                    'identification' => ['id' => 'iface-2'],
                    'attributes' => ['name' => 'wlan0', 'mac' => '00:1A:2B:3C:4D:5E'],
                ],
            ]),
        ]);

        // UISP HTTP transport is disabled in production; the test environment
        // behaves like development/staging, so the plain HTTP URL is accepted.
        $result = app(ObservationSyncService::class)->run($integration, 'interfaces');

        $this->assertSame(1, $result['devices_seen']);
        $this->assertSame(2, $result['interfaces_created']);

        $interfaces = $asset->interfaces()->get()->keyBy('name');
        $this->assertSame('00:11:22:33:44:55', $interfaces['eth0']->mac_address);
        $this->assertSame('00:1A:2B:3C:4D:5E', $interfaces['wlan0']->mac_address);
        $this->assertSame('uisp', $interfaces['eth0']->provider);
        $this->assertSame('interface', $interfaces['eth0']->external_type);

        // UISP does not expose VLAN observations.
        $this->assertSame(0, ObservedVlan::where('asset_id', $asset->id)->count());
    }
}
