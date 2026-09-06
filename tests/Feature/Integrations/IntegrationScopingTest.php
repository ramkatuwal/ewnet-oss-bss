<?php

namespace Tests\Feature\Integrations;

use App\Models\Company;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IntegrationScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function companyScopedUser(array $permissions): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);

        return [$user, $company];
    }

    private function integrationFor(User $user, Company $company): Integration
    {
        return Integration::factory()->forCompany($company->id)->create([
            'created_by' => $user->id,
        ]);
    }

    public function test_index_only_returns_own_company_integrations()
    {
        [$userA, $companyA] = $this->companyScopedUser(['integrations.view']);
        [$userB, $companyB] = $this->companyScopedUser(['integrations.view']);

        $integrationA = $this->integrationFor($userA, $companyA);
        $integrationB = $this->integrationFor($userB, $companyB);

        $response = $this->actingAs($userA)->getJson('/api/v1/integrations');

        $response->assertStatus(200);
        $this->assertTrue(collect($response->json('data'))->contains('id', $integrationA->id));
        $this->assertFalse(collect($response->json('data'))->contains('id', $integrationB->id));
    }

    public function test_user_cannot_view_integration_from_other_company()
    {
        [$userA, $companyA] = $this->companyScopedUser(['integrations.view']);
        [, $companyB] = $this->companyScopedUser(['integrations.view']);

        $integration = Integration::factory()->forCompany($companyB->id)->create();

        $response = $this->actingAs($userA)->getJson("/api/v1/integrations/{$integration->id}");
        $response->assertStatus(403);
    }

    public function test_global_integration_only_visible_to_super_admin()
    {
        [$user, $company] = $this->companyScopedUser(['integrations.view']);
        $global = Integration::factory()->create(['company_id' => null]);

        $response = $this->actingAs($user)->getJson("/api/v1/integrations/{$global->id}");
        $response->assertStatus(403);

        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo('integrations.view');
        $role = Role::findOrCreate('Super Admin', 'web');
        $superAdmin->assignRole($role);

        $response = $this->actingAs($superAdmin)->getJson("/api/v1/integrations/{$global->id}");
        $response->assertStatus(200);
    }

    public function test_admin_role_can_add_credential_to_own_company_integration()
    {
        [$user, $company] = $this->companyScopedUser(['integrations.view', 'integrations.credentials.manage']);
        $integration = $this->integrationFor($user, $company);

        $response = $this->actingAs($user)->postJson("/api/v1/integrations/{$integration->id}/credentials", [
            'credential_type' => 'api_token',
            'label' => 'Ops Token',
            'value' => 'abc-def-ghi',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('integration_credentials', [
            'integration_id' => $integration->id,
            'credential_type' => 'api_token',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_create_global_integration()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo('integrations.create');
        $superAdmin->assignRole(Role::findOrCreate('Super Admin', 'web'));

        $response = $this->actingAs($superAdmin)->postJson('/api/v1/integrations', [
            'name' => 'Global NMS',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'configuration' => ['api_url' => 'https://nms.example.com/api/v0'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('integrations', [
            'name' => 'Global NMS',
            'company_id' => null,
        ]);
    }

    public function test_scoped_user_can_create_integration_for_own_company()
    {
        [$user, $company] = $this->companyScopedUser(['integrations.create']);

        $response = $this->actingAs($user)->postJson('/api/v1/integrations', [
            'name' => 'Company NMS',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'configuration' => ['api_url' => 'https://nms.example.com/api/v0'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('integrations', [
            'name' => 'Company NMS',
            'company_id' => $company->id,
        ]);
    }

    public function test_scoped_user_cannot_create_integration_for_other_company()
    {
        [$user] = $this->companyScopedUser(['integrations.create']);
        $otherCompany = Company::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/integrations', [
            'name' => 'Sneaky NMS',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'company_id' => $otherCompany->id,
            'configuration' => ['api_url' => 'https://nms.example.com/api/v0'],
        ]);

        $response->assertStatus(403);
    }

    public function test_credentials_of_other_company_are_invisible()
    {
        [, $companyA] = $this->companyScopedUser(['integrations.view', 'integrations.credentials.manage']);
        [$user, $companyB] = $this->companyScopedUser(['integrations.view', 'integrations.credentials.manage']);

        $integration = Integration::factory()->forCompany($companyA->id)->create();

        $cred = new IntegrationCredential([
            'integration_id' => $integration->id,
            'credential_type' => 'api_token',
            'label' => 'Tenant A Secret',
            'is_active' => true,
        ]);
        $cred->setSecretValue('tenant-a-token');
        $cred->save();

        $response = $this->actingAs($user)->getJson("/api/v1/integrations/{$integration->id}/credentials");
        $response->assertStatus(403);
    }
}
