<?php

namespace Tests\Feature\Integrations;

use App\Models\Company;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibreNMSSecurityTest extends TestCase
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

    public function test_credential_value_never_returned_in_api(): void
    {
        [$user, $company] = $this->companyScopedUser(['integrations.view', 'integrations.credentials.manage']);

        $integration = Integration::create([
            'company_id' => $company->id,
            'name' => 'Security Test',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'status' => 'pending',
            'configuration' => ['endpoint' => 'https://test.example.com'],
        ]);

        $cred = new IntegrationCredential([
            'integration_id' => $integration->id,
            'credential_type' => 'api_token',
            'label' => 'Test Token',
            'is_active' => true,
        ]);
        $cred->setSecretValue('super-secret-token-12345');
        $cred->save();

        $response = $this->actingAs($user)->getJson("/api/v1/integrations/{$integration->id}/credentials");
        $response->assertStatus(200);

        $responseData = json_encode($response->json());
        $this->assertStringNotContainsString('super-secret-token-12345', $responseData);
        $this->assertStringContainsString('2345', $responseData);
    }

    public function test_unauthorized_user_cannot_access_credentials(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('integrations.view');
        // NOT giving credentials.manage

        $integration = Integration::create([
            'name' => 'Auth Test',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'status' => 'pending',
            'configuration' => ['endpoint' => 'https://test.example.com'],
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/integrations/{$integration->id}/credentials");
        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_trigger_sync_without_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('integrations.view');
        // NOT giving integrations.sync

        $integration = Integration::create([
            'name' => 'Sync Auth Test',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'status' => 'pending',
            'configuration' => ['endpoint' => 'https://test.example.com'],
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/integrations/{$integration->id}/sync");
        $response->assertStatus(403);
    }

    public function test_user_from_other_company_cannot_access_credentials(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $integration = Integration::create([
            'company_id' => $companyA->id,
            'name' => 'Tenant A Test',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'status' => 'pending',
            'configuration' => ['endpoint' => 'https://test.example.com'],
        ]);

        $otherCompanyUser = User::factory()->create(['company_id' => $companyB->id]);
        $otherCompanyUser->givePermissionTo(['integrations.view', 'integrations.credentials.manage']);

        $response = $this->actingAs($otherCompanyUser)
            ->getJson("/api/v1/integrations/{$integration->id}/credentials");
        $response->assertStatus(403);
    }
}
