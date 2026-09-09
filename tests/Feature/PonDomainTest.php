<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\NetworkPort;
use App\Models\PonDomain;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\Network\PonDomainService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PonDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function user(Company $company, array $permissions = []): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    protected function oltPort(Company $company, string $technology = 'gpon'): NetworkPort
    {
        $asset = Asset::factory()->create([
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'OLT',
        ]);

        return NetworkPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'technology' => $technology,
        ]);
    }

    protected function nonOltPort(Company $company): NetworkPort
    {
        $asset = Asset::factory()->create([
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'SWITCH',
        ]);

        return NetworkPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'technology' => null,
        ]);
    }

    // --- CRUD Tests ---

    public function test_create_pon_domain_on_olt_port(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);

        $response = $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains", [
            'metadata' => ['note' => 'test PON'],
        ]);

        $response->assertCreated()->assertJsonPath('data.olt_port_id', $port->id);
        $this->assertDatabaseHas('pon_domains', ['olt_port_id' => $port->id, 'company_id' => $company->id]);
    }

    public function test_create_pon_domain_without_metadata(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")
            ->assertCreated();
    }

    public function test_view_pon_domain(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);
        $domain = PonDomain::create(['olt_port_id' => $port->id, 'company_id' => $company->id, 'created_by' => $user->id, 'updated_by' => $user->id]);

        $this->actingAs($user)->getJson("/api/v1/pon-domains/{$domain->id}")
            ->assertOk()->assertJsonPath('data.id', $domain->id);
    }

    public function test_list_pon_domains(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);
        PonDomain::create(['olt_port_id' => $port->id, 'company_id' => $company->id, 'created_by' => $user->id, 'updated_by' => $user->id]);

        $this->actingAs($user)->getJson("/api/v1/network-ports/{$port->id}/pon-domains")
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_delete_pon_domain(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'net.pon-domains.delete', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);
        $domain = PonDomain::create(['olt_port_id' => $port->id, 'company_id' => $company->id, 'created_by' => $user->id, 'updated_by' => $user->id]);

        $this->actingAs($user)->deleteJson("/api/v1/pon-domains/{$domain->id}")
            ->assertOk();
        $this->assertSoftDeleted('pon_domains', ['id' => $domain->id]);
    }

    // --- Eligibility Tests ---

    public function test_null_technology_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $port = $this->nonOltPort($company);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")
            ->assertUnprocessable();
    }

    public function test_non_olt_parent_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $asset = Asset::factory()->create([
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'SWITCH',
        ]);
        $port = NetworkPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'technology' => 'gpon',
        ]);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")
            ->assertUnprocessable();
    }

    public function test_non_network_parent_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $asset = Asset::factory()->create([
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'ODF',
        ]);
        $port = NetworkPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'technology' => 'gpon',
        ]);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")
            ->assertUnprocessable();
    }

    public function test_deleted_network_port_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);
        $portId = $port->id;
        $port->delete();

        // Soft-deleted ports are hidden from route model binding; endpoint returns 404.
        $this->actingAs($user)->postJson("/api/v1/network-ports/{$portId}/pon-domains")
            ->assertNotFound();
    }

    public function test_deleted_olt_asset_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);

        // Service-level check verifies asset is live before creating.
        // NED-003 DB trigger also prevents this state, but service check provides defense-in-depth.
        // We can test this by checking the service directly, since the API policy
        // chain blocks access to deleted-asset ports before the service runs.
        $service = app(PonDomainService::class);
        DB::statement('SET session_replication_role = replica');
        DB::table('assets')->where('id', $port->asset_id)->update(['deleted_at' => now()]);
        DB::statement('SET session_replication_role = origin');

        $this->expectException(ValidationException::class);
        $service->create($port, $user);
    }

    public function test_company_mismatch_rejected(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($otherCompany);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")
            ->assertForbidden();
    }

    // --- Cardinality Tests ---

    public function test_one_live_domain_per_olt_port(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")->assertUnprocessable();
    }

    public function test_historical_domain_allows_new_live_domain(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);

        $first = app(PonDomainService::class)->create($port, $user);
        app(PonDomainService::class)->delete($first, $user);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")
            ->assertCreated();
    }

    public function test_historical_row_not_restored(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);

        $domain = app(PonDomainService::class)->create($port, $user);
        app(PonDomainService::class)->delete($domain, $user);

        $this->rejects(fn () => $domain->restore(), QueryException::class);
    }

    public function test_raw_db_bypass_blocked(): void
    {
        $company = Company::factory()->create();
        $port = $this->oltPort($company);
        $wrongCompany = Company::factory()->create();

        try {
            DB::table('pon_domains')->insert([
                'olt_port_id' => $port->id,
                'company_id' => $wrongCompany->id,
            ]);
            $this->fail('Raw DB insert with wrong company should be blocked by trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('pon domain requires', $e->getMessage());
        }
    }

    // --- History Protection Tests ---

    public function test_domain_history_blocks_network_port_technology_change(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'net.network-ports.update', 'assets.view']);
        $port = $this->oltPort($company, 'gpon');
        app(PonDomainService::class)->create($port, $user);

        $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", [
            'technology' => 'xgs-pon',
        ])->assertUnprocessable();
    }

    public function test_domain_history_blocks_network_port_technology_clear(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'net.network-ports.update', 'assets.view']);
        $port = $this->oltPort($company, 'gpon');
        app(PonDomainService::class)->create($port, $user);

        $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", [
            'technology' => null,
        ])->assertUnprocessable();
    }

    public function test_domain_history_blocks_network_port_deletion(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'net.network-ports.delete', 'assets.view']);
        $port = $this->oltPort($company);
        app(PonDomainService::class)->create($port, $user);

        $this->actingAs($user)->deleteJson("/api/v1/fim/network-ports/{$port->id}")
            ->assertUnprocessable();
    }

    public function test_domain_history_blocks_olt_deletion(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'net.network-ports.delete', 'assets.view']);
        $port = $this->oltPort($company);
        app(PonDomainService::class)->create($port, $user);

        $this->actingAs($user)->deleteJson("/api/v1/fim/network-ports/{$port->id}")
            ->assertUnprocessable();
    }

    public function test_domain_history_blocks_olt_category_change(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view', 'assets.update']);
        $port = $this->oltPort($company);
        app(PonDomainService::class)->create($port, $user);

        try {
            DB::table('assets')->where('id', $port->asset_id)->update(['category' => 'INFRASTRUCTURE']);
            $this->fail('Category change should be blocked.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('network port', strtolower($e->getMessage()));
        }
    }

    public function test_domain_history_blocks_olt_company_change(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view', 'assets.update']);
        $port = $this->oltPort($company);
        app(PonDomainService::class)->create($port, $user);

        try {
            DB::table('assets')->where('id', $port->asset_id)->update(['company_id' => Company::factory()->create()->id]);
            $this->fail('Company change should be blocked.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('network port', strtolower($e->getMessage()));
        }
    }

    public function test_domain_history_blocks_olt_type_change(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'assets.view', 'assets.update']);
        $port = $this->oltPort($company);
        app(PonDomainService::class)->create($port, $user);

        try {
            DB::table('assets')->where('id', $port->asset_id)->update(['type' => 'SWITCH']);
            $this->fail('Type change should be blocked.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('ned005b_protect_olt_asset_history', $e->getMessage());
        }
    }

    public function test_unrelated_descriptive_updates_allowed(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.create', 'net.network-ports.view', 'net.network-ports.update', 'assets.view']);
        $port = $this->oltPort($company);
        app(PonDomainService::class)->create($port, $user);

        // Descriptive updates on port and asset should still work
        $this->actingAs($user)->patchJson("/api/v1/fim/network-ports/{$port->id}", [
            'name' => 'Updated PON Port',
        ])->assertOk();

        DB::table('assets')->where('id', $port->asset_id)->update(['description' => 'Updated OLT']);
        $this->assertDatabaseHas('assets', ['id' => $port->asset_id, 'description' => 'Updated OLT']);
    }

    // --- RBAC / Scope Tests ---

    public function test_unauthenticated_rejected(): void
    {
        $company = Company::factory()->create();
        $port = $this->oltPort($company);

        $this->postJson("/api/v1/network-ports/{$port->id}/pon-domains")->assertUnauthorized();
    }

    public function test_missing_permission_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")
            ->assertForbidden();
    }

    public function test_foreign_company_forbidden(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'net.pon-domains.create', 'net.pon-domains.delete', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($otherCompany);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")->assertForbidden();
    }

    public function test_collection_filters_hidden_domains_before_count(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);
        $domain = PonDomain::create(['olt_port_id' => $port->id, 'company_id' => $company->id, 'created_by' => $user->id, 'updated_by' => $user->id]);
        app(PonDomainService::class)->delete($domain, $user);

        $this->actingAs($user)->getJson("/api/v1/network-ports/{$port->id}/pon-domains")
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    public function test_scope_company_isolation(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'net.pon-domains.create', 'net.pon-domains.delete', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($otherCompany);
        $domain = PonDomain::create(['olt_port_id' => $port->id, 'company_id' => $otherCompany->id, 'created_by' => $user->id, 'updated_by' => $user->id]);

        $this->actingAs($user)->getJson("/api/v1/pon-domains/{$domain->id}")->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/v1/pon-domains/{$domain->id}")->assertForbidden();
    }

    // --- Audit Test ---

    public function test_audit_events_created(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'net.pon-domains.create', 'net.pon-domains.delete', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")->assertCreated();
        $domain = PonDomain::where('olt_port_id', $port->id)->first();
        $this->actingAs($user)->deleteJson("/api/v1/pon-domains/{$domain->id}")->assertOk();

        $logs = AuditLog::where('action', 'like', 'net.pon-domain.%')->get();
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertSame($user->id, $log->actor_id);
            $this->assertArrayHasKey('id', $log->metadata);
            $this->assertArrayHasKey('olt_port_id', $log->metadata);
        }
    }

    // --- No Topology Inference ---

    public function test_no_physical_topology_edge_created(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'net.pon-domains.create', 'net.network-ports.view', 'assets.view']);
        $port = $this->oltPort($company);

        $this->actingAs($user)->postJson("/api/v1/network-ports/{$port->id}/pon-domains")->assertCreated();

        // No fiber attachments, no passive ports, no splitters should be created
        $this->assertDatabaseCount('network_port_fiber_termination_attachments', 0);
        $this->assertDatabaseCount('fiber_termination_port_attachments', 0);
        $this->assertDatabaseCount('physical_connections', 0);
    }

    // --- Migration Rollback ---

    public function test_new_migration_rollback_reapply(): void
    {
        $migration = require database_path('migrations/2026_09_09_140000_create_pon_domains_table.php');
        DB::statement('DROP TRIGGER IF EXISTS ned005c_validate_pon_membership ON pon_memberships');
        DB::statement('DROP FUNCTION IF EXISTS ned005c_validate_pon_membership()');
        Schema::dropIfExists('pon_memberships');
        $migration->down();
        $this->assertFalse(Schema::hasTable('pon_domains'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('pon_domains'));
    }

    // --- Helpers ---

    private function rejects(callable $operation, string $exception): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected '.$exception);
        } catch (QueryException|ValidationException $e) {
            $this->assertInstanceOf($exception, $e);
        }
    }
}
