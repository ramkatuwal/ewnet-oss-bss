<?php

namespace App\Support\Authorization;

use LogicException;

final class PermissionCatalog
{
    /** @return list<string> */
    public static function all(): array
    {
        $permissions = config('authorization.permissions', []);
        $duplicates = array_diff_assoc($permissions, array_unique($permissions));

        if ($duplicates !== []) {
            throw new LogicException('Duplicate permission names exist in the authorization catalog: '.implode(', ', array_unique($duplicates)));
        }

        sort($permissions);

        return array_values($permissions);
    }

    /** @return array<string, string|list<string>> */
    public static function managedRoles(): array
    {
        return config('authorization.managed_roles', []);
    }
}
