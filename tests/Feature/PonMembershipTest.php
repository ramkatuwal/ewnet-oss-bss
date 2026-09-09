<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\NetworkPort;
use App\Models\PonDomain;
use App\Models\PonMembership;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\Network\PonDomainService;
use App\Services\Network\PonMembershipService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PonMembershipTest extends TestCase
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

    protected function ponDomain(Company $company): PonDomain
    {
        $asset = Asset::factory()->create([
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'OLT',
        ]);
        $port = NetworkPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'technology' => 'gpon',
        ]);

        return PonDomain::create([
            'olt_port_id' => $port->id,
            'company_id' => $company->id,
            'created_by' => User::factory()->create(['company_id' => $company->id])->id,
            'updated_by' => User::factory()->create(['company_id' => $company->id])->id,
        ]);
    }

    protected function onuAsset(Company $company): Asset
    {
        return Asset::factory()->create([
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'ONU',
        ]);
    }

    private function rejects(callable $operation, string $exception): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected '.$exception);
        } catch (QueryException|ValidationException $e) {
            $this->assertInstanceOf($exception, $e);
        }
    }

    private function basePerms(): array
    {
        return ['net.pon-domains.view', 'net.network-ports.view', 'assets.view'];
    }

    // --- CRUD Tests ---

    public function test_create_membership(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view', 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        $response = $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain->id}/memberships", [
            'onu_asset_id' => $onu->id,
            'onu_id' => 'ONU-001',
            'metadata' => ['note' => 'test'],
        ]);

        $response->assertCreated()->assertJsonPath('data.onu_asset_id', $onu->id);
        $this->assertDatabaseHas('pon_memberships', ['onu_asset_id' => $onu->id, 'onu_id' => 'ONU-001']);
    }

    public function test_create_membership_without_onu_id(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view', 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain->id}/memberships", [
            'onu_asset_id' => $onu->id,
        ])->assertCreated();
    }

    public function test_view_membership(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);
        $membership = PonMembership::create([
            'pon_domain_id' => $domain->id,
            'onu_asset_id' => $onu->id,
            'onu_id' => 'ONU-001',
            'company_id' => $company->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)->getJson("/api/v1/pon-memberships/{$membership->id}")
            ->assertOk()->assertJsonPath('data.id', $membership->id);
    }

    public function test_list_memberships(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);
        PonMembership::create([
            'pon_domain_id' => $domain->id,
            'onu_asset_id' => $onu->id,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)->getJson("/api/v1/pon-domains/{$domain->id}/memberships")
            ->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_delete_membership(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view', 'net.pon-memberships.delete']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);
        $membership = PonMembership::create([
            'pon_domain_id' => $domain->id,
            'onu_asset_id' => $onu->id,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)->deleteJson("/api/v1/pon-memberships/{$membership->id}")
            ->assertOk();
        $this->assertSoftDeleted('pon_memberships', ['id' => $membership->id]);
    }

    // --- Eligibility Tests ---

    public function test_non_onu_asset_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $switch = Asset::factory()->create([
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'company_id' => $company->id,
            'category' => 'NETWORK',
            'type' => 'SWITCH',
        ]);

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain->id}/memberships", [
            'onu_asset_id' => $switch->id,
        ])->assertUnprocessable();
    }

    public function test_non_network_asset_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $infra = Asset::factory()->create([
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'ODF',
        ]);

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain->id}/memberships", [
            'onu_asset_id' => $infra->id,
        ])->assertUnprocessable();
    }

    public function test_deleted_onu_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);
        $onuId = $onu->id;

        $service = app(PonMembershipService::class);
        DB::statement('SET session_replication_role = replica');
        DB::table('assets')->where('id', $onuId)->update(['deleted_at' => now()]);
        DB::statement('SET session_replication_role = origin');

        $this->expectException(ValidationException::class);
        $service->create($domain, $onuId, $user);
    }

    public function test_deleted_pon_domain_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);
        app(PonDomainService::class)->delete($domain, $user);

        $service = app(PonMembershipService::class);
        $this->expectException(ValidationException::class);
        $service->create($domain, $onu->id, $user);
    }

    public function test_cross_company_rejected(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($otherCompany);

        $service = app(PonMembershipService::class);
        $this->expectException(ValidationException::class);
        $service->create($domain, $onu->id, $user);
    }

    // --- Cardinality Tests ---

    public function test_one_live_membership_per_onu(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view', 'net.pon-memberships.create']);
        $domain1 = $this->ponDomain($company);
        $domain2 = PonDomain::create([
            'olt_port_id' => NetworkPort::factory()->create([
                'asset_id' => Asset::factory()->create([
                    'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
                    'company_id' => $company->id,
                    'category' => 'NETWORK',
                    'type' => 'OLT',
                ])->id,
                'company_id' => $company->id,
                'technology' => 'gpon',
            ])->id,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $onu = $this->onuAsset($company);

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain1->id}/memberships", [
            'onu_asset_id' => $onu->id,
        ])->assertCreated();

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain2->id}/memberships", [
            'onu_asset_id' => $onu->id,
        ])->assertUnprocessable();
    }

    public function test_same_onu_can_reassign_after_retirement(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view', 'net.pon-memberships.create', 'net.pon-memberships.delete']);
        $domain1 = $this->ponDomain($company);
        $domain2 = PonDomain::create([
            'olt_port_id' => NetworkPort::factory()->create([
                'asset_id' => Asset::factory()->create([
                    'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
                    'company_id' => $company->id,
                    'category' => 'NETWORK',
                    'type' => 'OLT',
                ])->id,
                'company_id' => $company->id,
                'technology' => 'gpon',
            ])->id,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $onu = $this->onuAsset($company);

        $svc = app(PonMembershipService::class);
        $m1 = $svc->create($domain1, $onu->id, $user, 'ONU-001');
        $svc->delete($m1, $user);

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain2->id}/memberships", [
            'onu_asset_id' => $onu->id,
            'onu_id' => 'ONU-002',
        ])->assertCreated();
    }

    public function test_new_reassignment_creates_new_id(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create', 'net.pon-memberships.delete']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        $svc = app(PonMembershipService::class);
        $m1 = $svc->create($domain, $onu->id, $user, 'OLD-ID');
        $svc->delete($m1, $user);
        $m2 = $svc->create($domain, $onu->id, $user, 'NEW-ID');

        $this->assertNotEquals($m1->id, $m2->id);
        $this->assertEquals('NEW-ID', $m2->onu_id);
    }

    public function test_historical_row_not_restored(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create', 'net.pon-memberships.delete']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        $svc = app(PonMembershipService::class);
        $m = $svc->create($domain, $onu->id, $user);
        $svc->delete($m, $user);

        $this->rejects(fn () => $m->restore(), QueryException::class);
    }

    // --- onu_id Cardinality ---

    public function test_same_onu_id_on_same_pon_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu1 = $this->onuAsset($company);
        $onu2 = $this->onuAsset($company);

        $svc = app(PonMembershipService::class);
        $svc->create($domain, $onu1->id, $user, 'SHARED-ID');

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain->id}/memberships", [
            'onu_asset_id' => $onu2->id,
            'onu_id' => 'SHARED-ID',
        ])->assertUnprocessable();
    }

    public function test_same_onu_id_on_different_pon_allowed(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain1 = $this->ponDomain($company);
        $domain2 = PonDomain::create([
            'olt_port_id' => NetworkPort::factory()->create([
                'asset_id' => Asset::factory()->create([
                    'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
                    'company_id' => $company->id,
                    'category' => 'NETWORK',
                    'type' => 'OLT',
                ])->id,
                'company_id' => $company->id,
                'technology' => 'gpon',
            ])->id,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $onu1 = $this->onuAsset($company);
        $onu2 = $this->onuAsset($company);

        $svc = app(PonMembershipService::class);
        $svc->create($domain1, $onu1->id, $user, 'SHARED-ID');

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain2->id}/memberships", [
            'onu_asset_id' => $onu2->id,
            'onu_id' => 'SHARED-ID',
        ])->assertCreated();
    }

    public function test_null_onu_id_allowed_multiple(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu1 = $this->onuAsset($company);
        $onu2 = $this->onuAsset($company);

        $svc = app(PonMembershipService::class);
        $svc->create($domain, $onu1->id, $user, null);
        $svc->create($domain, $onu2->id, $user, null);

        $this->assertDatabaseCount('pon_memberships', 2);
    }

    // --- Raw DB Bypass ---

    public function test_raw_db_insert_blocked(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($otherCompany);

        try {
            DB::table('pon_memberships')->insert([
                'pon_domain_id' => $domain->id,
                'onu_asset_id' => $onu->id,
                'company_id' => $otherCompany->id,
            ]);
            $this->fail('Raw DB insert with wrong company should be blocked by trigger.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('membership', strtolower($e->getMessage()));
        }
    }

    public function test_raw_db_wrong_company_blocked(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($otherCompany);

        try {
            DB::table('pon_memberships')->insert([
                'pon_domain_id' => $domain->id,
                'onu_asset_id' => $onu->id,
                'company_id' => $otherCompany->id,
            ]);
            $this->fail('Should be blocked.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('membership', strtolower($e->getMessage()));
        }
    }

    // --- Concurrency Tests ---

    public function test_concurrent_same_onu_creation(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create', 'net.pon-memberships.view']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        $svc = app(PonMembershipService::class);
        $failures = 0;
        $attempts = 5;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $svc->create($domain, $onu->id, $user, "ONU-{$i}");
            } catch (ValidationException $e) {
                $failures++;
            }
        }

        $this->assertGreaterThanOrEqual(1, $failures);
        $this->assertDatabaseCount('pon_memberships', 1);
    }

    public function test_concurrent_same_pon_onu_id(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create', 'net.pon-memberships.view']);
        $domain = $this->ponDomain($company);
        $onu1 = $this->onuAsset($company);
        $onu2 = $this->onuAsset($company);

        $svc = app(PonMembershipService::class);
        $failures = 0;

        try {
            $svc->create($domain, $onu1->id, $user, 'ONU-ID');
        } catch (ValidationException $e) {
            $failures++;
        }
        try {
            $svc->create($domain, $onu2->id, $user, 'ONU-ID');
        } catch (ValidationException $e) {
            $failures++;
        }

        $this->assertEquals(1, $failures);
    }

    public function test_creation_vs_pon_domain_retirement(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        app(PonDomainService::class)->delete($domain, $user);

        $service = app(PonMembershipService::class);
        $this->expectException(ValidationException::class);
        $service->create($domain, $onu->id, $user);
    }

    public function test_creation_vs_onu_invalidation(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);
        $onuId = $onu->id;

        DB::statement('SET session_replication_role = replica');
        DB::table('assets')->where('id', $onuId)->update(['deleted_at' => now()]);
        DB::statement('SET session_replication_role = origin');

        $service = app(PonMembershipService::class);
        $this->expectException(ValidationException::class);
        $service->create($domain, $onuId, $user);
    }

    // --- PonDomain Retirement Protection ---

    public function test_live_memberships_block_pon_domain_retirement(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create', 'net.pon-domains.delete']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        app(PonMembershipService::class)->create($domain, $onu->id, $user);

        $this->actingAs($user)->deleteJson("/api/v1/pon-domains/{$domain->id}")
            ->assertUnprocessable();
    }

    public function test_retired_memberships_permit_pon_domain_retirement(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create', 'net.pon-memberships.delete', 'net.pon-domains.delete']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        $svc = app(PonMembershipService::class);
        $m = $svc->create($domain, $onu->id, $user);
        $svc->delete($m, $user);

        $this->actingAs($user)->deleteJson("/api/v1/pon-domains/{$domain->id}")
            ->assertOk();
    }

    // --- ONU History Protection ---

    public function test_membership_history_blocks_onu_category_change(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        app(PonMembershipService::class)->create($domain, $onu->id, $user);

        try {
            DB::table('assets')->where('id', $onu->id)->update(['category' => 'INFRASTRUCTURE']);
            $this->fail('Category change should be blocked.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('onu asset', strtolower($e->getMessage()));
        }
    }

    public function test_membership_history_blocks_onu_type_change(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        app(PonMembershipService::class)->create($domain, $onu->id, $user);

        try {
            DB::table('assets')->where('id', $onu->id)->update(['type' => 'SWITCH']);
            $this->fail('Type change should be blocked.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('onu asset', strtolower($e->getMessage()));
        }
    }

    public function test_membership_history_blocks_onu_company_change(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        app(PonMembershipService::class)->create($domain, $onu->id, $user);

        try {
            DB::table('assets')->where('id', $onu->id)->update(['company_id' => Company::factory()->create()->id]);
            $this->fail('Company change should be blocked.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('onu asset', strtolower($e->getMessage()));
        }
    }

    public function test_membership_history_blocks_onu_deletion(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        app(PonMembershipService::class)->create($domain, $onu->id, $user);

        try {
            DB::table('assets')->where('id', $onu->id)->update(['deleted_at' => now()]);
            $this->fail('Deletion should be blocked.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('onu asset', strtolower($e->getMessage()));
        }
    }

    public function test_unrelated_onu_descriptive_updates_allowed(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        app(PonMembershipService::class)->create($domain, $onu->id, $user);

        DB::table('assets')->where('id', $onu->id)->update(['description' => 'Updated ONU']);
        $this->assertDatabaseHas('assets', ['id' => $onu->id, 'description' => 'Updated ONU']);
    }

    // --- RBAC / Scope Tests ---

    public function test_unauthenticated_rejected(): void
    {
        $company = Company::factory()->create();
        $domain = $this->ponDomain($company);

        $this->postJson("/api/v1/pon-domains/{$domain->id}/memberships")->assertUnauthorized();
    }

    public function test_missing_permission_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.pon-domains.view', 'assets.view']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain->id}/memberships", [
            'onu_asset_id' => $onu->id,
        ])->assertForbidden();
    }

    public function test_foreign_company_forbidden(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view', 'net.pon-memberships.create', 'net.pon-memberships.delete']);
        $domain = $this->ponDomain($otherCompany);
        $onu = $this->onuAsset($otherCompany);

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain->id}/memberships", [
            'onu_asset_id' => $onu->id,
        ])->assertForbidden();
    }

    public function test_collection_filters_hidden_memberships_before_count(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);
        $m = PonMembership::create([
            'pon_domain_id' => $domain->id,
            'onu_asset_id' => $onu->id,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        app(PonMembershipService::class)->delete($m, $user);

        $this->actingAs($user)->getJson("/api/v1/pon-domains/{$domain->id}/memberships")
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }

    // --- Audit Tests ---

    public function test_audit_events_created(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view', 'net.pon-memberships.create', 'net.pon-memberships.delete']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain->id}/memberships", [
            'onu_asset_id' => $onu->id,
        ])->assertCreated();

        $membership = PonMembership::where('onu_asset_id', $onu->id)->first();
        $this->actingAs($user)->deleteJson("/api/v1/pon-memberships/{$membership->id}")->assertOk();

        $logs = AuditLog::where('action', 'like', 'net.pon-membership.%')->get();
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertSame($user->id, $log->actor_id);
            $this->assertArrayHasKey('id', $log->metadata);
            $this->assertArrayHasKey('pon_domain_id', $log->metadata);
            $this->assertArrayHasKey('onu_asset_id', $log->metadata);
        }
    }

    // --- No Topology Inference ---

    public function test_no_physical_topology_edge_created(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, [...$this->basePerms(), 'net.pon-memberships.view', 'net.pon-memberships.create']);
        $domain = $this->ponDomain($company);
        $onu = $this->onuAsset($company);

        $this->actingAs($user)->postJson("/api/v1/pon-domains/{$domain->id}/memberships", [
            'onu_asset_id' => $onu->id,
        ])->assertCreated();

        $this->assertDatabaseCount('network_port_fiber_termination_attachments', 0);
        $this->assertDatabaseCount('fiber_termination_port_attachments', 0);
        $this->assertDatabaseCount('physical_connections', 0);
    }

    // --- Migration Rollback ---

    public function test_new_migration_rollback_reapply(): void
    {
        $migration = require database_path('migrations/2026_09_09_150000_create_pon_memberships_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('pon_memberships'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('pon_memberships'));
    }
}
