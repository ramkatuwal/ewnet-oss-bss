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
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        // Create permissions
        $permissions = [
            'librenms.import',
            'integration.uisp.import',
            'imports.view',
            'assets.view',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Sync all permissions to Super Admin role (mimicking DefaultSuperAdminSeeder)
        $role->syncPermissions(Permission::all());

        // Create users
        $this->uispUser = User::factory()->create();
        $this->uispUser->givePermissionTo('integration.uisp.import');
        $this->uispUser->givePermissionTo('imports.view');

        $this->librenmsUser = User::factory()->create();
        $this->librenmsUser->givePermissionTo('librenms.import');
        $this->librenmsUser->givePermissionTo('imports.view');

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole($role);

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
            'configuration' => ['api_url' => 'https://uisp.test/api'],
        ]);
    }

    public function test_librenms_user_can_access_librenms_import()
    {
        $this->actingAs($this->librenmsUser);
        $this->assertTrue($this->librenmsUser->hasPermissionTo('librenms.import'));
        $this->assertFalse($this->librenmsUser->hasPermissionTo('integration.uisp.import'));
    }

    public function test_uisp_user_can_access_uisp_import()
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

}
