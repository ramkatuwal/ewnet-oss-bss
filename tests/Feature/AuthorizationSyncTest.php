<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Authorization\AuthorizationSynchronizer;
use App\Support\Authorization\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_is_unique_and_deterministic(): void
    {
        $catalog = PermissionCatalog::all();

        $this->assertSame($catalog, array_values(array_unique($catalog)));
        $this->assertSame($catalog, [...$catalog]);
        $this->assertCount(170, $catalog);
    }

    public function test_empty_database_is_filled_and_super_admin_receives_catalog(): void
    {
        $result = app(AuthorizationSynchronizer::class)->synchronize();

        $this->assertSame(170, $result['created']);
        $this->assertSame(170, Permission::where('guard_name', 'web')->count());
        $this->assertSame(170, Role::where('name', 'Super Admin')->firstOrFail()->permissions()->count());
        $this->assertTrue($result['cache_reset']);
    }

    public function test_second_sync_is_idempotent(): void
    {
        $synchronizer = app(AuthorizationSynchronizer::class);

        $synchronizer->synchronize();
        $result = $synchronizer->synchronize();

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['roles_created']);
        $this->assertSame(0, $result['grants_added']);
    }

    public function test_custom_role_assignments_and_extra_permissions_are_preserved(): void
    {
        $extra = Permission::create(['name' => 'legacy.custom.permission', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'Field Custom', 'guard_name' => 'web']);
        $role->givePermissionTo($extra);
        $user = User::factory()->create();
        $user->assignRole($role);
        $assignmentCount = DB::table('model_has_roles')->count();

        app(AuthorizationSynchronizer::class)->synchronize();

        $this->assertTrue($role->fresh()->hasPermissionTo($extra));
        $this->assertSame($assignmentCount, DB::table('model_has_roles')->count());
        $this->assertDatabaseHas('permissions', ['name' => 'legacy.custom.permission', 'guard_name' => 'web']);
    }

    public function test_dry_run_reports_missing_permissions_without_mutation(): void
    {
        $missing = PermissionCatalog::all()[0];
        Permission::where('name', $missing)->delete();
        $before = Permission::count();

        $result = app(AuthorizationSynchronizer::class)->synchronize(true);

        $this->assertContains($missing, $result['missing']);
        $this->assertSame($before, Permission::count());
        $this->assertFalse($result['cache_reset']);
    }
}
