<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Integration;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibreNMSImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authorization_required_for_import()
    {
        $user = User::factory()->create();
        $integration = Integration::factory()->create(['provider' => 'librenms']);

        $response = $this->actingAs($user)->postJson("/api/v1/integrations/librenms/{$integration->id}/import");

        $response->assertStatus(403);
    }

    public function test_librenms_import_permission_exists()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('librenms.import');

        $this->assertTrue($user->hasPermissionTo('librenms.import'));
    }

    public function test_import_endpoint_requires_authentication()
    {
        $integration = Integration::factory()->create(['provider' => 'librenms']);
        $response = $this->postJson("/api/v1/integrations/librenms/{$integration->id}/import");
        $response->assertStatus(401);
    }

    public function test_devices_endpoint_requires_authentication()
    {
        $integration = Integration::factory()->create(['provider' => 'librenms']);
        $response = $this->getJson("/api/v1/integrations/librenms/{$integration->id}/devices");
        $response->assertStatus(401);
    }

    public function test_preview_endpoint_requires_authentication()
    {
        $integration = Integration::factory()->create(['provider' => 'librenms']);
        $response = $this->getJson("/api/v1/integrations/librenms/{$integration->id}/preview");
        $response->assertStatus(401);
    }
}
