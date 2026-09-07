<?php

namespace Tests\Feature\Fim;

use App\Models\Branch;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiberCableAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function companyUser(Company $company, array $permissions, ?string $scopeType = 'company', ?int $scopeId = null): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId ?? $company->id,
            'granted_by' => $user->id,
        ]);

        return $user->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Company $company, string $code = 'FC-AUTH-001'): array
    {
        return [
            'company_id' => $company->id,
            'cable_code' => $code,
            'name' => 'Auth test cable',
            'cable_type' => 'distribution',
            'fiber_count' => 24,
            'status' => 'planned',
            'route_geometry' => [
                'type' => 'LineString',
                'coordinates' => [
                    [81.5, 28.9],
                    [81.6, 29.0],
                ],
            ],
        ];
    }

    public function test_unauthenticated_requests_are_rejected()
    {
        $this->postJson('/api/v1/fim/fiber-cables', [])->assertStatus(401);
        $this->getJson('/api/v1/fim/fiber-cables')->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden()
    {
        $company = Company::factory()->create();
        $user = $this->companyUser($company, []);

        $this->actingAs($user)->postJson('/api/v1/fim/fiber-cables', $this->payload($company))->assertStatus(403);
        $this->actingAs($user)->getJson('/api/v1/fim/fiber-cables')->assertStatus(403);
    }

    public function test_cannot_view_update_delete_cable_outside_scope()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $owner = $this->companyUser($companyA, ['fim.cables.create', 'fim.cables.view']);
        $outsider = $this->companyUser($companyB, ['fim.cables.view', 'fim.cables.update', 'fim.cables.delete']);

        $id = $this->actingAs($owner)->postJson('/api/v1/fim/fiber-cables', $this->payload($companyA))
            ->assertStatus(201)->json('data.id');

        $this->actingAs($outsider)->getJson("/api/v1/fim/fiber-cables/{$id}")->assertStatus(403);
        $this->actingAs($outsider)->putJson("/api/v1/fim/fiber-cables/{$id}", ['name' => 'Hijacked'])->assertStatus(403);
        $this->actingAs($outsider)->deleteJson("/api/v1/fim/fiber-cables/{$id}")->assertStatus(403);
    }

    public function test_scoped_listing_excludes_out_of_scope_cables()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $ownerA = $this->companyUser($companyA, ['fim.cables.create', 'fim.cables.view']);
        $ownerB = $this->companyUser($companyB, ['fim.cables.create', 'fim.cables.view']);

        $this->actingAs($ownerA)->postJson('/api/v1/fim/fiber-cables', $this->payload($companyA, 'FC-A'))->assertStatus(201);
        $this->actingAs($ownerB)->postJson('/api/v1/fim/fiber-cables', $this->payload($companyB, 'FC-B'))->assertStatus(201);

        $list = $this->actingAs($ownerA)->getJson('/api/v1/fim/fiber-cables')->assertStatus(200);
        $this->assertEquals(1, $list->json('meta.total'));
        $this->assertEquals('FC-A', $list->json('data.0.cable_code'));
    }

    public function test_company_filter_cannot_bypass_scope()
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $ownerA = $this->companyUser($companyA, ['fim.cables.create', 'fim.cables.view']);
        $ownerB = $this->companyUser($companyB, ['fim.cables.create', 'fim.cables.view']);

        $this->actingAs($ownerB)->postJson('/api/v1/fim/fiber-cables', $this->payload($companyB, 'FC-B'))->assertStatus(201);

        // Filtering by another company must not expose its cables.
        $list = $this->actingAs($ownerA)->getJson("/api/v1/fim/fiber-cables?company_id={$companyB->id}")->assertStatus(200);
        $this->assertEquals(0, $list->json('meta.total'));
    }

    public function test_branch_scoped_user_does_not_gain_company_wide_cable_access()
    {
        $company = Company::factory()->create();
        $region = Region::factory()->create(['company_id' => $company->id]);
        $branch = Branch::factory()->create(['region_id' => $region->id]);
        $site = Site::factory()->create(['company_id' => $company->id, 'region_id' => $region->id, 'branch_id' => $branch->id]);

        $branchUser = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
        $branchUser->givePermissionTo('fim.cables.view');
        $branchUser->givePermissionTo('fim.cables.create');
        UserManagementScope::create([
            'user_id' => $branchUser->id,
            'scope_type' => 'branch',
            'scope_id' => $branch->id,
            'granted_by' => $branchUser->id,
        ]);
        $branchUser->refresh();

        // FIM-002 scope is company-restrictive: a branch scope grants no cable access.
        $this->actingAs($branchUser)->postJson('/api/v1/fim/fiber-cables', $this->payload($company))
            ->assertStatus(422);

        $owner = $this->companyUser($company, ['fim.cables.create', 'fim.cables.view']);
        $payload = $this->payload($company, 'FC-BRANCH-001');
        $payload['start_site_id'] = $site->id;
        $id = $this->actingAs($owner)->postJson('/api/v1/fim/fiber-cables', $payload)->assertStatus(201)->json('data.id');

        $this->actingAs($branchUser)->getJson("/api/v1/fim/fiber-cables/{$id}")->assertStatus(403);

        $cable = FiberCable::find($id);
        $this->assertNotNull($cable);
    }
}
