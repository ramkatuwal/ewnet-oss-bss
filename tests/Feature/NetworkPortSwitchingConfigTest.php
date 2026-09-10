<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\NetworkPort;
use App\Models\NetworkPortSwitchingConfig;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Models\Vlan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NetworkPortSwitchingConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    protected function port(Company $company, array $asset = [], array $port = []): NetworkPort
    {
        $asset = Asset::factory()->create([
            'company_id' => $company->id,
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'category' => 'NETWORK',
            'type' => 'SWITCH',
            ...$asset,
        ]);

        return NetworkPort::factory()->create(['asset_id' => $asset->id, 'company_id' => $company->id, ...$port]);
    }

    protected function vlan(Company $company, int $vid): Vlan
    {
        return Vlan::create(['company_id' => $company->id, 'vid' => $vid, 'name' => "VLAN {$vid}"]);
    }

    protected function payload(string $mode, array $memberships, ?array $metadata = null): array
    {
        return array_filter(['mode' => $mode, 'memberships' => $memberships, 'metadata' => $metadata], fn ($value) => $value !== null);
    }

    protected function permissions(): array
    {
        return ['assets.view', 'net.network-ports.view', 'net.vlans.view', 'net.port-switching-configs.view', 'net.port-switching-configs.configure', 'net.port-switching-configs.delete'];
    }

    public function test_access_requires_exactly_one_untagged_vlan(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, $this->permissions());
        $port = $this->port($company);
        $vlan = $this->vlan($company, 100);

        $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('access', [['vlan_id' => $vlan->id, 'tagging' => 'untagged']]))
            ->assertOk()->assertJsonPath('data.mode', 'access')->assertJsonPath('data.memberships.0.vlan_id', $vlan->id);
        $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('access', []))->assertUnprocessable();
        $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('access', [['vlan_id' => $vlan->id, 'tagging' => 'tagged']]))->assertUnprocessable();
    }

    public function test_trunk_allows_empty_or_tagged_memberships_and_at_most_one_untagged_membership(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, $this->permissions());
        $port = $this->port($company);
        $a = $this->vlan($company, 100);
        $b = $this->vlan($company, 200);

        $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('trunk', []))->assertOk()->assertJsonPath('data.memberships', []);
        $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('trunk', [
            ['vlan_id' => $a->id, 'tagging' => 'untagged'], ['vlan_id' => $b->id, 'tagging' => 'tagged'],
        ]))->assertOk();
        $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('trunk', [
            ['vlan_id' => $a->id, 'tagging' => 'untagged'], ['vlan_id' => $b->id, 'tagging' => 'untagged'],
        ]))->assertUnprocessable();
    }

    public function test_put_is_idempotent_regardless_of_membership_order_and_real_changes_create_history(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, $this->permissions());
        $port = $this->port($company);
        $a = $this->vlan($company, 100);
        $b = $this->vlan($company, 200);
        $payload = $this->payload('trunk', [['vlan_id' => $a->id, 'tagging' => 'tagged'], ['vlan_id' => $b->id, 'tagging' => 'tagged']]);

        $first = $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $payload)->assertOk()->json('data.id');
        $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('trunk', array_reverse($payload['memberships'])))->assertOk()->assertJsonPath('data.id', $first);
        $this->assertDatabaseCount('network_port_switching_configs', 1);
        $this->assertSame(1, AuditLog::where('action', 'net.port-switching-configured')->count());
        $second = $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('trunk', [['vlan_id' => $a->id, 'tagging' => 'untagged']]))->assertOk()->json('data.id');
        $this->assertNotSame($first, $second);
        $this->assertSoftDeleted('network_port_switching_configs', ['id' => $first]);
        $this->assertDatabaseHas('network_port_switching_configs', ['id' => $second, 'deleted_at' => null]);
        $this->assertSame(1, AuditLog::where('action', 'net.port-switching-config-replaced')->count());
    }

    public function test_retirement_soft_deletes_memberships_then_configuration_and_blocks_restore_or_hard_delete(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, $this->permissions());
        $port = $this->port($company);
        $vlan = $this->vlan($company, 100);
        $id = $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('access', [['vlan_id' => $vlan->id, 'tagging' => 'untagged']]))->json('data.id');

        $this->actingAs($user)->deleteJson("/api/v1/network-ports/{$port->id}/switching-configuration")->assertOk();
        $this->assertSoftDeleted('network_port_switching_configs', ['id' => $id]);
        $this->assertDatabaseMissing('network_port_vlan_memberships', ['network_port_switching_config_id' => $id, 'deleted_at' => null]);
        $this->expectException(QueryException::class);
        DB::table('network_port_switching_configs')->where('id', $id)->update(['deleted_at' => null]);
    }

    public function test_raw_config_retirement_cannot_leave_live_memberships_and_history_protects_port_identity(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, $this->permissions());
        $port = $this->port($company);
        $vlan = $this->vlan($company, 100);
        $id = $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('access', [['vlan_id' => $vlan->id, 'tagging' => 'untagged']]))->json('data.id');

        try {
            DB::transaction(function () use ($id) {
                DB::table('network_port_switching_configs')->where('id', $id)->update(['deleted_at' => now()]);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('A retired configuration retained a live membership.');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
        $this->actingAs($user)->deleteJson("/api/v1/network-ports/{$port->id}/switching-configuration")->assertOk();
        $this->expectException(QueryException::class);
        $port->delete();
    }

    public function test_raw_database_constraints_reject_invalid_access_and_trunk_state_at_commit(): void
    {
        $company = Company::factory()->create();
        $port = $this->port($company);
        $a = $this->vlan($company, 100);
        $b = $this->vlan($company, 200);

        try {
            DB::transaction(function () use ($port, $company) {
                DB::table('network_port_switching_configs')->insert(['network_port_id' => $port->id, 'company_id' => $company->id, 'mode' => 'access', 'created_at' => now(), 'updated_at' => now()]);
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Access configuration without membership committed.');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
        try {
            DB::transaction(function () use ($port, $company, $a, $b) {
                $id = DB::table('network_port_switching_configs')->insertGetId(['network_port_id' => $port->id, 'company_id' => $company->id, 'mode' => 'trunk', 'created_at' => now(), 'updated_at' => now()]);
                foreach ([$a, $b] as $vlan) {
                    DB::table('network_port_vlan_memberships')->insert(['network_port_switching_config_id' => $id, 'vlan_id' => $vlan->id, 'company_id' => $company->id, 'tagging' => 'untagged', 'created_at' => now(), 'updated_at' => now()]);
                }
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Trunk configuration with two untagged memberships committed.');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
    }

    public function test_cross_company_deleted_and_non_network_boundaries_are_rejected(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->user($company, $this->permissions());
        $port = $this->port($company);
        $foreign = $this->vlan($other, 100);
        $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('access', [['vlan_id' => $foreign->id, 'tagging' => 'untagged']]))->assertUnprocessable();
        $bad = $this->port($company, ['category' => 'INFRASTRUCTURE']);
        $local = $this->vlan($company, 200);
        $this->actingAs($user)->putJson("/api/v1/network-ports/{$bad->id}/switching-configuration", $this->payload('access', [['vlan_id' => $local->id, 'tagging' => 'untagged']]))->assertUnprocessable();
    }

    public function test_vlan_retirement_is_blocked_while_live_and_port_direction_reserved_and_pon_do_not_change_eligibility(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, $this->permissions());
        $port = $this->port($company, ['type' => 'OLT'], ['technology' => 'gpon', 'port_direction' => 'access']);
        $vlan = $this->vlan($company, 100);
        $vlan->update(['reserved' => true]);

        $this->actingAs($user)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('trunk', [['vlan_id' => $vlan->id, 'tagging' => 'tagged']]))->assertOk();
        $this->expectException(QueryException::class);
        $vlan->delete();
    }

    public function test_scope_and_read_filtering_prevent_hidden_vlan_membership_leakage(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['assets.view', 'net.network-ports.view', 'net.port-switching-configs.view']);
        $port = $this->port($company);
        $vlan = $this->vlan($company, 100);
        DB::transaction(function () use ($port, $company, $vlan) {
            $config = NetworkPortSwitchingConfig::create(['network_port_id' => $port->id, 'company_id' => $company->id, 'mode' => 'access']);
            DB::table('network_port_vlan_memberships')->insert(['network_port_switching_config_id' => $config->id, 'vlan_id' => $vlan->id, 'company_id' => $company->id, 'tagging' => 'untagged', 'created_at' => now(), 'updated_at' => now()]);
        });

        $this->actingAs($user)->getJson("/api/v1/network-ports/{$port->id}/switching-configuration")
            ->assertOk()->assertJsonPath('data.memberships', []);
    }

    public function test_retirement_audit_metadata_excludes_hidden_vlan_memberships(): void
    {
        $company = Company::factory()->create();
        $configurer = $this->user($company, $this->permissions());
        $retirer = $this->user($company, ['assets.view', 'net.network-ports.view', 'net.port-switching-configs.delete']);
        $port = $this->port($company);
        $vlan = $this->vlan($company, 100);

        $this->actingAs($configurer)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('access', [['vlan_id' => $vlan->id, 'tagging' => 'untagged']]))->assertOk();
        $this->actingAs($retirer)->deleteJson("/api/v1/network-ports/{$port->id}/switching-configuration")->assertOk();

        $audit = AuditLog::where('action', 'net.port-switching-config-retired')->latest('id')->firstOrFail();
        $this->assertSame([], $audit->metadata['memberships']);
        $this->assertNotContains($vlan->id, $audit->metadata['memberships']);
    }

    public function test_audit_log_retrieval_filters_switching_memberships_by_viewer_vlan_visibility(): void
    {
        $company = Company::factory()->create();
        $configurer = $this->user($company, [...$this->permissions(), 'system.debug.view']);
        $auditViewer = $this->user($company, ['assets.view', 'net.network-ports.view', 'net.port-switching-configs.delete', 'system.debug.view']);
        $port = $this->port($company);
        $first = $this->vlan($company, 100);
        $second = $this->vlan($company, 200);

        $this->actingAs($configurer)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('trunk', [
            ['vlan_id' => $first->id, 'tagging' => 'tagged'],
            ['vlan_id' => $second->id, 'tagging' => 'tagged'],
        ]))->assertOk();
        $this->actingAs($configurer)->putJson("/api/v1/network-ports/{$port->id}/switching-configuration", $this->payload('access', [
            ['vlan_id' => $first->id, 'tagging' => 'untagged'],
        ]))->assertOk();

        foreach (['net.port-switching-configured', 'net.port-switching-config-replaced'] as $action) {
            $this->actingAs($auditViewer)->getJson("/api/v1/security/audit-logs?action={$action}")
                ->assertOk()->assertJsonPath('data.0.metadata.memberships', []);
        }
        $this->actingAs($auditViewer)->deleteJson("/api/v1/network-ports/{$port->id}/switching-configuration")->assertOk();
        $this->actingAs($auditViewer)->getJson('/api/v1/security/audit-logs?action=net.port-switching-config-retired')
            ->assertOk()->assertJsonPath('data.0.metadata.memberships', []);
        $this->actingAs($configurer)->getJson('/api/v1/security/audit-logs?action=net.port-switching-configured')
            ->assertOk()->assertJsonPath('data.0.metadata.memberships.0.vlan_id', $first->id)
            ->assertJsonPath('data.0.metadata.memberships.1.vlan_id', $second->id);
    }
}
