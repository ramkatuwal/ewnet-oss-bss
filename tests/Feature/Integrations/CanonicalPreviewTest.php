<?php

namespace Tests\Feature\Integrations;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_returns_401()
    {
        $integration = Integration::factory()->create();
        $response = $this->postJson("/api/v1/integrations/{$integration->id}/import/preview", [
            'resource_type' => 'device'
        ]);
        $response->assertStatus(401);
    }

    public function test_authenticated_user_without_permission_returns_403()
    {
        $user = User::factory()->create();
        $integration = Integration::factory()->create();
        $response = $this->actingAs($user)
            ->postJson("/api/v1/integrations/{$integration->id}/import/preview", [
                'resource_type' => 'device'
            ]);
        $response->assertStatus(403);
    }

    public function test_missing_resource_type_returns_422()
    {
        $user = User::factory()->create();
        $integration = Integration::factory()->create();
        $user->givePermissionTo('integrations.view');
        $response = $this->actingAs($user)
            ->postJson("/api/v1/integrations/{$integration->id}/import/preview", []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['resource_type']);
    }

    public function test_invalid_resource_type_returns_422()
    {
        $user = User::factory()->create();
        $integration = Integration::factory()->create();
        $user->givePermissionTo('integrations.view');
        $response = $this->actingAs($user)
            ->postJson("/api/v1/integrations/{$integration->id}/import/preview", [
                'resource_type' => 'invalid'
            ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['resource_type']);
    }

    public function test_error_response_sanitizes_exception_details()
    {
        $user = User::factory()->create();
        $integration = Integration::factory()->create([
            'provider' => 'uisp',
            'enabled' => true,
        ]);
        $user->givePermissionTo('integrations.view');

        $this->app->bind(\App\Services\Integrations\Uisp\UispImportService::class, function ($app) use ($integration) {
            $mock = $this->createMock(\App\Services\Integrations\Uisp\UispImportService::class);
            $mock->expects($this->once())
                ->method('previewDevices')
                ->willThrowException(new \RuntimeException('synthetic-secret-for-test'));
            return $mock;
        });

        $response = $this->actingAs($user)
            ->postJson("/api/v1/integrations/{$integration->id}/import/preview", [
                'resource_type' => 'device'
            ]);

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error' => 'Preview could not be completed. Please try again later.'
        ]);
        $response->assertJsonMissing(['error' => 'synthetic-secret-for-test']);
        $response->assertJsonMissing(['error' => 'RuntimeException']);
    }
}
