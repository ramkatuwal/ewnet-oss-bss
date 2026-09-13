<?php

namespace App\Services\Authorization;

use App\Support\Authorization\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AuthorizationSynchronizer
{
    /** @return array<string, mixed> */
    public function audit(): array
    {
        $canonical = PermissionCatalog::all();
        $database = Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all();
        $missing = array_values(array_diff($canonical, $database));
        $extra = array_values(array_diff($database, $canonical));
        $roleGrants = [];

        foreach (PermissionCatalog::managedRoles() as $roleName => $mode) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            $roleGrants[$roleName] = [
                'exists' => $role !== null,
                'missing' => $role === null ? $canonical : array_values(array_diff($canonical, $role->permissions()->pluck('name')->all())),
            ];
        }

        return [
            'canonical' => count($canonical),
            'database' => count($database),
            'present' => count($canonical) - count($missing),
            'missing' => $missing,
            'extra' => $extra,
            'managed_roles' => $roleGrants,
        ];
    }

    /** @return array<string, mixed> */
    public function synchronize(bool $dryRun = false): array
    {
        $audit = $this->audit();
        $created = 0;
        $rolesCreated = 0;
        $grantsAdded = 0;

        if (! $dryRun) {
            DB::transaction(function () use (&$created, &$rolesCreated, &$grantsAdded): void {
                foreach (PermissionCatalog::all() as $name) {
                    $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                    $created += (int) $permission->wasRecentlyCreated;
                }

                foreach (PermissionCatalog::managedRoles() as $roleName => $mode) {
                    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                    $rolesCreated += (int) $role->wasRecentlyCreated;

                    $required = $mode === 'all' ? PermissionCatalog::all() : $mode;
                    if ($required !== []) {
                        $existing = $role->permissions()->pluck('permissions.name')->all();
                        $missing = array_values(array_diff($required, $existing));
                        if ($missing !== []) {
                            $role->givePermissionTo($missing);
                            $grantsAdded += count($missing);
                        }
                    }
                }
            });

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $audit = $this->audit();
        }

        return array_merge($audit, [
            'dry_run' => $dryRun,
            'created' => $created,
            'roles_created' => $rolesCreated,
            'grants_added' => $grantsAdded,
            'cache_reset' => ! $dryRun,
        ]);
    }
}
