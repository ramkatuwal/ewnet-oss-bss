<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetInterface;
use App\Models\Branch;
use App\Models\Company;
use App\Models\IpAddress;
use App\Models\NetworkPort;
use App\Models\NetworkPortSwitchingConfig;
use App\Models\NetworkPortVlanMembership;
use App\Models\PonDomain;
use App\Models\PonMembership;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Models\Vlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_can_create_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-TEST-001',
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(201);
        $this->assertMatchesRegularExpression('/^AST-[0-9]{6}$/', $response->json('data.asset_tag'));
        $response->assertJsonPath('data.company_id', $company->id);
        $this->assertDatabaseHas('assets', ['id' => $response->json('data.id'), 'asset_tag' => $response->json('data.asset_tag'), 'company_id' => $company->id]);
    }

    public function test_asset_tag_is_always_generated_ignoring_client_input()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'asset_tag' => 'CLIENT-FORGED-TAG',
            'category' => 'NETWORK',
            'type' => 'Router',
            'quantity' => 1,
            'serial_number' => 'SN-FORGED-001',
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(201);
        $this->assertNotSame('CLIENT-FORGED-TAG', $response->json('data.asset_tag'));
        $this->assertMatchesRegularExpression('/^AST-[0-9]{6}$/', $response->json('data.asset_tag'));
        $this->assertDatabaseHas('assets', ['id' => $response->json('data.id'), 'asset_tag' => $response->json('data.asset_tag')]);
        $this->assertDatabaseMissing('assets', ['asset_tag' => 'CLIENT-FORGED-TAG']);
    }

    public function test_generated_asset_tags_are_unique_and_sequential()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $tags = [];
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->actingAs($user)->postJson('/api/v1/assets', [
                'site_id' => $site->id,
                'category' => 'NETWORK',
                'type' => 'Switch',
                'quantity' => 1,
                'serial_number' => 'SN-SEQ-00'.$i,
                'status' => 'OPERATIONAL',
            ]);
            $response->assertStatus(201);
            $this->assertMatchesRegularExpression('/^AST-[0-9]{6}$/', $response->json('data.asset_tag'));
            $tags[] = $response->json('data.asset_tag');
        }

        $this->assertCount(3, array_unique($tags));
        sort($tags);
        $numeric = array_map(fn (string $t) => (int) substr($t, 4), $tags);
        $this->assertEquals([$numeric[0], $numeric[0] + 1, $numeric[0] + 2], $numeric);
    }

    public function test_cannot_create_asset_without_permission()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        // User does NOT have assets.create permission
        $user->givePermissionTo('sites.view');

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'asset_tag' => 'TEST',
            'category' => 'OTHER',
            'type' => 'Test',
            'quantity' => 1,
            'status' => 'OPERATIONAL',
        ]);
        $response->assertStatus(403);
    }

    public function test_duplicate_client_tag_never_collides_since_tag_is_server_generated()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $payload = [
            'site_id' => $site->id,
            'asset_tag' => 'EW-DUP-001',
            'category' => 'NETWORK',
            'type' => 'Router',
            'quantity' => 2,
            'status' => 'OPERATIONAL',
        ];

        $r1 = $this->actingAs($user)->postJson('/api/v1/assets', $payload);
        $r2 = $this->actingAs($user)->postJson('/api/v1/assets', $payload);

        $r1->assertStatus(201);
        $r2->assertStatus(201);
        $this->assertNotSame($r1->json('data.asset_tag'), $r2->json('data.asset_tag'));
    }

    public function test_can_update_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.update');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'asset_tag' => 'EW-UPDATE-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-UPDATE-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/assets/{$asset->id}", [
            'status' => 'MAINTENANCE',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'MAINTENANCE']);
    }

    public function test_can_delete_asset()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.delete');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'asset_tag' => 'EW-DELETE-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-DELETE-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/assets/{$asset->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('assets', ['id' => $asset->id]);
    }

    public function test_cannot_access_asset_outside_scope()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $site1 = Site::factory()->create(['company_id' => $company1->id]);
        $site2 = Site::factory()->create(['company_id' => $company2->id]);

        $user = User::factory()->create(['company_id' => $company1->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company1->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site2->id,
            'asset_tag' => 'EW-OUTSIDE-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => 'SN-OUTSIDE-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}");

        $response->assertStatus(403);
    }

    public function test_can_view_assets_list()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        Asset::factory()->count(3)->create([
            'site_id' => $site->id,
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'serial_number' => function () {
                return 'SN-LIST-'.fake()->unique()->numberBetween(100, 999);
            },
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/assets');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_cannot_create_asset_serial_required_for_power_quantity_1()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'asset_tag' => 'EW-SERIAL-REQ-001',
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 1,
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['serial_number']);
    }

    public function test_can_create_asset_serial_not_required_for_power_quantity_gt_1()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'category' => 'POWER',
            'type' => 'Battery',
            'quantity' => 5,
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(201);
        $this->assertMatchesRegularExpression('/^AST-[0-9]{6}$/', $response->json('data.asset_tag'));
    }

    public function test_cannot_create_asset_serial_required_for_network_quantity_1()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.create');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $response = $this->actingAs($user)->postJson('/api/v1/assets', [
            'site_id' => $site->id,
            'asset_tag' => 'EW-SERIAL-REQ-NET-001',
            'category' => 'NETWORK',
            'type' => 'Router',
            'quantity' => 1,
            'status' => 'OPERATIONAL',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['serial_number']);
    }

    public function test_can_view_network_asset_primary_ip_and_mac()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'category' => 'NETWORK',
            'type' => 'Router',
            'quantity' => 1,
            'serial_number' => 'SN-NET-IP-001',
            'status' => 'OPERATIONAL',
        ]);

        $interface = AssetInterface::create([
            'asset_id' => $asset->id,
            'name' => 'eth0',
            'mac_address' => '00:1A:2B:3C:4D:5E',
            'is_management' => true,
        ]);
        IpAddress::create([
            'asset_interface_id' => $interface->id,
            'ip_address' => '192.168.1.10',
            'prefix_length' => 24,
            'is_management' => true,
            'is_primary' => true,
        ]);

        // Secondary interface without a MAC-owned management IP
        $secondary = AssetInterface::create([
            'asset_id' => $asset->id,
            'name' => 'eth1',
            'mac_address' => '0A:BB:CC:DD:EE:FF',
        ]);
        IpAddress::create([
            'asset_interface_id' => $secondary->id,
            'ip_address' => '10.0.0.1',
            'prefix_length' => 24,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/assets');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.ip_address', '192.168.1.10/24');
        $response->assertJsonPath('data.0.mac_address', '00:1A:2B:3C:4D:5E');
    }

    public function test_list_filters_by_company_region_branch_manufacturer_and_condition()
    {
        $company = Company::factory()->create();
        $region = Region::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['region_id' => $region->id]);
        $site = Site::factory()->create([
            'company_id' => $company->id,
            'region_id' => $region->id,
            'branch_id' => $branch->id,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        Asset::factory()->count(2)->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'Router',
            'manufacturer' => 'MikroTik',
            'condition' => 'GOOD',
            'serial_number' => function () {
                return 'SN-ORG-'.fake()->unique()->numberBetween(1000, 9999);
            },
            'status' => 'OPERATIONAL',
        ]);

        $this->actingAs($user)->getJson('/api/v1/assets?company_id='.$company->id)->assertStatus(200)->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson('/api/v1/assets?region_id='.$region->id)->assertStatus(200)->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson('/api/v1/assets?branch_id='.$branch->id)->assertStatus(200)->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson('/api/v1/assets?manufacturer=MikroTik')->assertStatus(200)->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson('/api/v1/assets?condition=GOOD')->assertStatus(200)->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson('/api/v1/assets?manufacturer=Ubiquiti')->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_list_company_filter_resolves_through_site_for_legacy_assets()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $siteA = Site::factory()->create(['company_id' => $companyA->id]);
        $siteB = Site::factory()->create(['company_id' => $companyB->id]);

        // Legacy/imported assets carry no company_id column value; the org
        // relationship is derived through the site.
        Asset::factory()->create([
            'site_id' => $siteA->id,
            'company_id' => null,
            'asset_tag' => 'LEG-A',
            'category' => 'NETWORK',
            'type' => 'Router',
            'status' => 'OPERATIONAL',
        ]);
        Asset::factory()->create([
            'site_id' => $siteB->id,
            'company_id' => null,
            'asset_tag' => 'LEG-B',
            'category' => 'NETWORK',
            'type' => 'Router',
            'status' => 'OPERATIONAL',
        ]);

        $user = User::factory()->create(['company_id' => $companyA->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $companyA->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $this->actingAs($user)
            ->getJson('/api/v1/assets?company_id='.$companyA->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.asset_tag', 'LEG-A');
    }

    public function test_list_region_filter_cannot_leak_other_company_regions()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $region2 = Region::factory()->create(['company_id' => $company2->id]);
        $site2 = Site::factory()->create(['company_id' => $company2->id, 'region_id' => $region2->id]);

        $user = User::factory()->create(['company_id' => $company1->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company1->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        Asset::factory()->create([
            'site_id' => $site2->id,
            'company_id' => $company2->id,
            'category' => 'NETWORK',
            'type' => 'Switch',
            'serial_number' => 'SN-LEAK-001',
            'status' => 'OPERATIONAL',
        ]);

        $this->actingAs($user)->getJson('/api/v1/assets?region_id='.$region2->id)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_show_returns_operational_detail_sections()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'OLT',
            'serial_number' => 'SN-DETAIL-001',
            'status' => 'OPERATIONAL',
        ]);

        $interface = AssetInterface::create([
            'asset_id' => $asset->id,
            'name' => 'ge-0/0/0',
            'mac_address' => '00:00:00:00:00:01',
        ]);
        IpAddress::create([
            'asset_interface_id' => $interface->id,
            'ip_address' => '10.10.10.1',
            'prefix_length' => 24,
            'is_primary' => true,
        ]);
        $port = NetworkPort::create([
            'asset_id' => $asset->id,
            'port_key' => 'ge-0/0/1',
            'technology' => 'gpon',
            'company_id' => $company->id,
        ]);
        $ponDomain = PonDomain::create(['olt_port_id' => $port->id, 'company_id' => $company->id]);
        $onu = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'ONU',
            'serial_number' => 'SN-ONU-001',
        ]);
        PonMembership::create([
            'pon_domain_id' => $ponDomain->id,
            'onu_asset_id' => $onu->id,
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'asset_tag',
                'company_id',
                'interfaces',
                'ip_addresses',
                'network_ports',
                'pon_memberships',
                'routing_instances',
                'passive_optical_ports',
                'splitter_profile',
            ],
        ]);
        $response->assertJsonCount(1, 'data.interfaces');
        $response->assertJsonCount(1, 'data.ip_addresses');
        $response->assertJsonCount(1, 'data.network_ports');
        // PON memberships belong to ONU assets (onu_asset_id); an OLT detail
        // exposes its PON context through network_ports[].pon_domain instead.
        $response->assertJsonCount(0, 'data.pon_memberships');
        $response->assertJsonPath('data.ip_addresses.0.ip_address', '10.10.10.1');
        $response->assertJsonPath('data.network_ports.0.pon_domain.id', $ponDomain->id);
    }

    public function test_list_is_lean_and_omits_operational_sub_resources()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'Switch',
            'serial_number' => 'SN-LEAN-001',
            'status' => 'OPERATIONAL',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/assets');
        $response->assertStatus(200);
        $response->assertJsonMissingPath('data.0.interfaces');
        $response->assertJsonMissingPath('data.0.pon_memberships');
    }

    public function test_operational_endpoints_expose_interfaces_and_ip_addresses()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'Router',
            'serial_number' => 'SN-IF-001',
            'status' => 'OPERATIONAL',
        ]);

        $interface = AssetInterface::create([
            'asset_id' => $asset->id,
            'name' => 'eth0',
            'mac_address' => '0A:AA:BB:CC:DD:01',
            'is_management' => true,
        ]);
        IpAddress::create([
            'asset_interface_id' => $interface->id,
            'ip_address' => '172.16.0.1',
            'prefix_length' => 24,
            'is_primary' => true,
        ]);

        $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/interfaces")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'eth0');

        $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/ip-addresses")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ip_address', '172.16.0.1');
    }

    public function test_vlan_memberships_endpoint_aggregates_port_switching()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'Switch',
            'serial_number' => 'SN-VLAN-001',
            'status' => 'OPERATIONAL',
        ]);

        $port = NetworkPort::create(['asset_id' => $asset->id, 'port_key' => 'ge-0/0/0', 'company_id' => $company->id]);
        $config = NetworkPortSwitchingConfig::create(['network_port_id' => $port->id, 'company_id' => $company->id, 'mode' => 'access']);
        $vlan = Vlan::create(['company_id' => $company->id, 'vid' => 100, 'name' => 'Customers']);
        NetworkPortVlanMembership::create([
            'network_port_switching_config_id' => $config->id,
            'vlan_id' => $vlan->id,
            'company_id' => $company->id,
            'tagging' => 'untagged',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/vlan-memberships");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.port_key', 'ge-0/0/0');
        $response->assertJsonPath('data.0.mode', 'access');
        $response->assertJsonPath('data.0.tagging', 'untagged');
        $response->assertJsonPath('data.0.vlan.vid', 100);
        $response->assertJsonPath('data.0.vlan.name', 'Customers');
    }

    public function test_operational_endpoints_respect_asset_scope()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $site2 = Site::factory()->create(['company_id' => $company2->id]);

        $user = User::factory()->create(['company_id' => $company1->id]);
        $user->givePermissionTo('assets.view');
        $user->givePermissionTo('sites.view');

        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company1->id,
            'granted_by' => $user->id,
        ]);
        $user->refresh();

        $asset = Asset::factory()->create([
            'site_id' => $site2->id,
            'company_id' => $company2->id,
            'category' => 'NETWORK',
            'type' => 'Router',
            'serial_number' => 'SN-SCOPE-001',
            'status' => 'OPERATIONAL',
        ]);

        $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/interfaces")->assertStatus(403);
        $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/ip-addresses")->assertStatus(403);
        $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/pon-memberships")->assertStatus(403);
        $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/vlan-memberships")->assertStatus(403);
    }
}
