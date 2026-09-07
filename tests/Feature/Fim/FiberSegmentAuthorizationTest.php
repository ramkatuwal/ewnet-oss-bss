<?php

namespace Tests\Feature\Fim;

use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberSegment;
use App\Models\NetworkConnectionPoint;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiberSegmentAuthorizationTest extends TestCase
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
        $user->givePermissionTo($permissions);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);

        return $user;
    }

    protected function segment(Company $company): FiberSegment
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $cable = FiberCable::factory()->create(['company_id' => $company->id]);
        $a = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);
        $b = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'company_id' => $company->id]);

        return FiberSegment::factory()->create(['fiber_cable_id' => $cable->id, 'endpoint_a_id' => $a->id, 'endpoint_b_id' => $b->id, 'company_id' => $company->id]);
    }

    public function test_other_company_cannot_view_update_or_delete_segment(): void
    {
        $owner = Company::factory()->create();
        $other = Company::factory()->create();
        $segment = $this->segment($owner);
        $user = $this->user($other, ['fim.fiber-segments.view', 'fim.fiber-segments.update', 'fim.fiber-segments.delete']);

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/fiber-segments/{$segment->id}")->assertForbidden();
        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/fim/fiber-segments/{$segment->id}", ['status' => 'active'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/fim/fiber-segments/{$segment->id}")->assertForbidden();
    }

    public function test_permission_is_required_to_create_and_view(): void
    {
        $company = Company::factory()->create();
        $segment = $this->segment($company);
        $user = $this->user($company, []);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/fim/fiber-segments')->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/fim/fiber-segments/{$segment->id}")->assertForbidden();
    }
}
