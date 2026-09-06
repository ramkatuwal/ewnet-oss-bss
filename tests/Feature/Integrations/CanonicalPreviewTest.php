<?php

namespace Tests\Feature\Integrations;

use App\Models\Company;
use App\Models\Integration;
use App\Models\User;
use App\Services\Integrations\Uisp\UispImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalPreviewTest extends TestCase
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

    public function test_unauthenticated_request_returns_401()
    {
        $integration = Integration::factory()->create();
        $response = $this->postJson("/api/v1/integrations/{$integration->id}/import/preview", [
            'resource_type' => 'device',
        ]);
        $response->assertStatus(401);
    }

    public function test_authenticated_user_without_permission_returns_403()
    {
        [$user, $company] = $this->companyScopedUser([]);
        $integration = Integration::factory()->forCompany($company->id)->create();
        $response = $this->actingAs($user)
            ->postJson("/api/v1/integrations/{$integration->id}/import/preview", [
                'resource_type' => 'device',
            ]);
        $response->assertStatus(403);
    }

    public function test_missing_resource_type_returns_422()
    {
        [$user, $company] = $this->companyScopedUser(['integrations.view']);
        $integration = Integration::factory()->forCompany($company->id)->create();
        $response = $this->actingAs($user)
            ->postJson("/api/v1/integrations/{$integration->id}/import/preview", []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['resource_type']);
    }

    public function test_invalid_resource_type_returns_422()
    {
        [$user, $company] = $this->companyScopedUser(['integrations.view']);
        $integration = Integration::factory()->forCompany($company->id)->create();
        $response = $this->actingAs($user)
            ->postJson("/api/v1/integrations/{$integration->id}/import/preview", [
                'resource_type' => 'invalid',
            ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['resource_type']);
    }

    public function test_error_response_sanitizes_exception_details()
    {
        [$user, $company] = $this->companyScopedUser(['integrations.view']);
        $integration = Integration::factory()->forCompany($company->id)->create([
            'provider' => 'uisp',
            'enabled' => true,
            'configuration' => ['api_url' => 'https://uisp.test/api'],
        ]);

        $this->app->bind(UispImportService::class, function ($app) {
            $mock = $this->createMock(UispImportService::class);
            $mock->expects($this->once())
                ->method('preview')
                ->willThrowException(new \RuntimeException('synthetic-secret-for-test'));

            return $mock;
        });

        $response = $this->actingAs($user)
            ->postJson("/api/v1/integrations/{$integration->id}/import/preview", [
                'resource_type' => 'device',
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error' => 'Preview could not be completed. Please try again later.',
        ]);
        $response->assertJsonMissing(['error' => 'synthetic-secret-for-test']);
        $response->assertJsonMissing(['error' => 'RuntimeException']);
    }
}
