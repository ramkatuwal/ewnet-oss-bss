<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Models\Vlan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VlanTest extends TestCase
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

    protected function vlan(Company $company, array $attributes = []): Vlan
    {
        return Vlan::create([
            'company_id' => $company->id,
            'vid' => 100,
            'name' => 'Customer 100',
            ...$attributes,
        ]);
    }

    protected function payload(Company $company, array $attributes = []): array
    {
        return [
            'company_id' => $company->id,
            'vid' => 100,
            'name' => 'Customer 100',
            ...$attributes,
        ];
    }

    public function test_create_valid_vlan(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.vlans.create']);

        $this->actingAs($user)->postJson('/api/v1/vlans', $this->payload($company, [
            'description' => 'Customer delivery namespace',
            'reserved' => true,
            'metadata' => ['internal' => 'safe to store, never audited'],
        ]))->assertCreated()
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.vid', 100)
            ->assertJsonPath('data.reserved', true);

        $this->assertDatabaseHas('vlans', ['company_id' => $company->id, 'vid' => 100, 'reserved' => true]);
    }

    public function test_vid_boundary_values_are_accepted(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.vlans.create']);

        $this->actingAs($user)->postJson('/api/v1/vlans', $this->payload($company, ['vid' => 1, 'name' => 'Lowest']))->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/vlans', $this->payload($company, ['vid' => 4094, 'name' => 'Highest']))->assertCreated();
    }

    public function test_vid_zero_and_4095_are_rejected_by_api(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.vlans.create']);

        $this->actingAs($user)->postJson('/api/v1/vlans', $this->payload($company, ['vid' => 0]))->assertUnprocessable()->assertJsonValidationErrors('vid');
        $this->actingAs($user)->postJson('/api/v1/vlans', $this->payload($company, ['vid' => 4095]))->assertUnprocessable()->assertJsonValidationErrors('vid');
    }

    public function test_vid_zero_and_4095_are_rejected_by_database(): void
    {
        $company = Company::factory()->create();

        foreach ([0, 4095] as $vid) {
            try {
                DB::transaction(fn () => DB::table('vlans')->insert(['company_id' => $company->id, 'vid' => $vid, 'name' => 'Invalid', 'created_at' => now(), 'updated_at' => now()]));
                $this->fail('The database accepted an invalid VLAN ID.');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
    }

    public function test_duplicate_live_vid_in_company_is_rejected_but_another_company_may_reuse_it(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->user($company, ['net.vlans.create']);
        $this->vlan($company);

        $this->actingAs($user)->postJson('/api/v1/vlans', $this->payload($company))->assertUnprocessable()->assertJsonValidationErrors('vid');
        $this->vlan($otherCompany);
        $this->assertDatabaseCount('vlans', 2);
    }

    public function test_retirement_releases_vid_and_recreation_creates_new_identity_without_restoring_history(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.vlans.create', 'net.vlans.delete']);
        $old = $this->vlan($company);

        $this->actingAs($user)->deleteJson("/api/v1/vlans/{$old->id}")->assertOk();
        $this->actingAs($user)->postJson('/api/v1/vlans', $this->payload($company))->assertCreated();
        $new = Vlan::where('company_id', $company->id)->where('vid', 100)->firstOrFail();

        $this->assertNotSame($old->id, $new->id);
        $this->assertSoftDeleted('vlans', ['id' => $old->id]);
        $this->assertDatabaseHas('vlans', ['id' => $new->id, 'deleted_at' => null]);
    }

    public function test_historical_row_cannot_be_restored_or_hard_deleted(): void
    {
        $company = Company::factory()->create();
        $vlan = $this->vlan($company);
        $vlan->delete();

        foreach ([['deleted_at' => null], ['company_id' => $company->id + 1]] as $update) {
            try {
                DB::transaction(fn () => DB::table('vlans')->where('id', $vlan->id)->update($update));
                $this->fail('The database allowed an invalid VLAN history mutation.');
            } catch (QueryException $e) {
                $this->assertSame('23503', $e->errorInfo[0]);
            }
        }

        $this->expectException(QueryException::class);
        DB::table('vlans')->where('id', $vlan->id)->delete();
    }

    public function test_company_id_and_vid_are_immutable_at_api_and_database_boundaries(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = $this->user($company, ['net.vlans.update']);
        $vlan = $this->vlan($company);

        $this->actingAs($user)->patchJson("/api/v1/vlans/{$vlan->id}", ['company_id' => $otherCompany->id])->assertUnprocessable()->assertJsonValidationErrors('company_id');
        $this->actingAs($user)->patchJson("/api/v1/vlans/{$vlan->id}", ['vid' => 101])->assertUnprocessable()->assertJsonValidationErrors('vid');

        foreach ([['company_id' => $otherCompany->id], ['vid' => 101]] as $update) {
            try {
                DB::transaction(fn () => DB::table('vlans')->where('id', $vlan->id)->update($update));
                $this->fail('The database allowed an immutable VLAN identity field to change.');
            } catch (QueryException $e) {
                $this->assertSame('23503', $e->errorInfo[0]);
            }
        }
    }

    public function test_descriptive_fields_and_reserved_state_are_mutable(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.vlans.update']);
        $vlan = $this->vlan($company);

        $this->actingAs($user)->patchJson("/api/v1/vlans/{$vlan->id}", [
            'name' => 'Updated',
            'description' => null,
            'reserved' => true,
            'metadata' => ['purpose' => 'management'],
        ])->assertOk()->assertJsonPath('data.name', 'Updated')->assertJsonPath('data.reserved', true);

        $this->actingAs($user)->patchJson("/api/v1/vlans/{$vlan->id}", ['reserved' => false])->assertOk()->assertJsonPath('data.reserved', false);
        $this->assertDatabaseHas('vlans', ['id' => $vlan->id, 'name' => 'Updated', 'reserved' => false, 'description' => null]);
    }

    public function test_description_and_metadata_are_nullable(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.vlans.create']);

        $this->actingAs($user)->postJson('/api/v1/vlans', $this->payload($company))
            ->assertCreated()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.metadata', null);
    }

    public function test_rbac_and_scope_prevent_cross_company_creation_and_visibility_leaks(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $viewer = $this->user($company, ['net.vlans.view']);
        $creator = $this->user($company, ['net.vlans.create']);
        $local = $this->vlan($company);
        $foreign = $this->vlan($otherCompany, ['vid' => 200]);

        $this->actingAs($viewer)->getJson('/api/v1/vlans')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $local->id);
        $this->actingAs($viewer)->getJson("/api/v1/vlans/{$foreign->id}")->assertForbidden();
        $this->actingAs($creator)->postJson('/api/v1/vlans', $this->payload($otherCompany, ['vid' => 201]))->assertUnprocessable()->assertJsonValidationErrors('company_id');
        $this->actingAs($viewer)->postJson('/api/v1/vlans', $this->payload($company, ['vid' => 202]))->assertForbidden();
    }

    public function test_audit_contains_only_safe_vlan_fields(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['net.vlans.create', 'net.vlans.update', 'net.vlans.delete']);

        $response = $this->actingAs($user)->postJson('/api/v1/vlans', $this->payload($company, ['metadata' => ['secret_topology' => 'do not audit']]))->assertCreated();
        $id = $response->json('data.id');
        $this->actingAs($user)->patchJson("/api/v1/vlans/{$id}", ['name' => 'Renamed'])->assertOk();
        $this->actingAs($user)->deleteJson("/api/v1/vlans/{$id}")->assertOk();

        $logs = AuditLog::where('target_type', Vlan::class)->orderBy('id')->get();
        $this->assertCount(3, $logs);
        foreach ($logs as $log) {
            $keys = array_keys($log->metadata);
            sort($keys);
            $this->assertSame(['company_id', 'name', 'reserved', 'vid', 'vlan_id'], $keys);
            $this->assertArrayNotHasKey('metadata', $log->metadata);
            $this->assertArrayNotHasKey('secret_topology', $log->metadata);
        }
    }

    public function test_vlan_identity_has_no_pon_or_fim_topology_side_effects(): void
    {
        $company = Company::factory()->create();
        $this->vlan($company);

        $this->assertDatabaseCount('pon_domains', 0);
        $this->assertDatabaseCount('pon_memberships', 0);
        $this->assertDatabaseCount('network_ports', 0);
    }
}
