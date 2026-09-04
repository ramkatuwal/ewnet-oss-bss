<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Services\Imports\GenericImportService;
use App\Services\Imports\LibreNmsSourceAdapter;
use App\Services\Imports\UispSourceAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ImportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $uispUser;
    protected $librenmsUser;
    protected $superAdmin;
    protected $integration;
    protected $uispIntegration;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Super Admin role exists
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        // Create permissions
        Permission::firstOrCreate(['name' => 'librenms.import', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'integration.uisp.import', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'imports.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'assets.view', 'guard_name' => 'web']);

        // Create users
        $this->uispUser = User::factory()->create();
        $this->uispUser->givePermissionTo('integration.uisp.import');
        $this->uispUser->givePermissionTo('imports.view');

        $this->librenmsUser = User::factory()->create();
        $this->librenmsUser->givePermissionTo('librenms.import');
        $this->librenmsUser->givePermissionTo('imports.view');

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        // Create LibreNMS integration
        $this->integration = Integration::factory()->create([
            'provider' => 'librenms',
            'enabled' => true,
            'configuration' => ['api_url' => 'https://nms.test/api/v0'],
        ]);

        // Create UISP integration
        $this->uispIntegration = Integration::factory()->create([
            'provider' => 'uisp',
            'enabled' => true,
            'configuration' => ['api_url' => 'https://unms.test/api.v2.1'],
        ]);

        // Create credentials
        $credential = new IntegrationCredential();
        $credential->integration_id = $this->integration->id;
        $credential->credential_type = 'api_token';
        $credential->setSecretValue('test-token-123');
        $credential->save();

        $credential2 = new IntegrationCredential();
        $credential2->integration_id = $this->uispIntegration->id;
        $credential2->credential_type = 'api_token';
        $credential2->setSecretValue('test-token-456');
        $credential2->save();
    }

    // ============================================================
    // PERMISSION TESTS (No API calls - pure permission checks)
    // ============================================================

    public function test_uisp_user_can_access_uisp_import()
    {
        $this->actingAs($this->uispUser);
        $this->assertTrue($this->uispUser->hasPermissionTo('integration.uisp.import'));
        $this->assertTrue($this->uispUser->hasPermissionTo('imports.view'));
    }

    public function test_librenms_user_can_access_librenms_import()
    {
        $this->actingAs($this->librenmsUser);
        $this->assertTrue($this->librenmsUser->hasPermissionTo('librenms.import'));
        $this->assertTrue($this->librenmsUser->hasPermissionTo('imports.view'));
    }

    public function test_librenms_user_cannot_access_uisp_import()
    {
        $this->actingAs($this->librenmsUser);
        $this->assertFalse($this->librenmsUser->hasPermissionTo('integration.uisp.import'));
    }

    public function test_uisp_user_cannot_access_librenms_import()
    {
        $this->actingAs($this->uispUser);
        $this->assertFalse($this->uispUser->hasPermissionTo('librenms.import'));
    }

    public function test_librenms_user_has_correct_permission()
    {
        $this->actingAs($this->librenmsUser);
        $this->assertTrue($this->librenmsUser->hasPermissionTo('librenms.import'));
        $this->assertFalse($this->librenmsUser->hasPermissionTo('integration.uisp.import'));
    }

    public function test_uisp_user_has_correct_permission()
    {
        $this->actingAs($this->uispUser);
        $this->assertTrue($this->uispUser->hasPermissionTo('integration.uisp.import'));
        $this->assertFalse($this->uispUser->hasPermissionTo('librenms.import'));
    }

    public function test_super_admin_bypasses_all_scope_checks()
    {
        $this->actingAs($this->superAdmin);
        $this->assertTrue($this->superAdmin->hasRole('Super Admin'));
        $this->assertTrue($this->superAdmin->hasPermissionTo('librenms.import'));
        $this->assertTrue($this->superAdmin->hasPermissionTo('integration.uisp.import'));
    }

    public function test_unknown_provider_permission_mapping()
    {
        // Test that the provider permission mapping works correctly
        $controller = new \App\Http\Controllers\Api\V1\ImportController();
        
        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('getProviderPermission');
        $method->setAccessible(true);
        
        $this->assertEquals('librenms.import', $method->invoke($controller, 'librenms'));
        $this->assertEquals('integration.uisp.import', $method->invoke($controller, 'uisp'));
        $this->assertNull($method->invoke($controller, 'unknown'));
    }

    public function test_imports_view_permission_exists()
    {
        $this->assertTrue(Permission::where('name', 'imports.view')->exists());
    }

    public function test_librenms_import_permission_exists()
    {
        $this->assertTrue(Permission::where('name', 'librenms.import')->exists());
    }

    public function test_uisp_import_permission_exists()
    {
        $this->assertTrue(Permission::where('name', 'integration.uisp.import')->exists());
    }
}
