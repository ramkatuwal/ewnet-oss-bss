<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\PhysicalConnection;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManagementScopeService
{
    public static function getEffectiveScopes(User $user): array
    {
        if ($user->hasRole('Super Admin')) {
            return [['scope_type' => 'global', 'scope_id' => 0]];
        }

        $explicit = $user->managementScopes->map(fn (UserManagementScope $s) => [
            'scope_type' => $s->scope_type,
            'scope_id' => $s->scope_id,
        ])->toArray();

        if (! empty($explicit)) {
            return $explicit;
        }

        return static::deriveFromMembership($user);
    }

    protected static function deriveFromMembership(User $user): array
    {
        // NARROWEST first — most restrictive fallback
        if ($user->department_id) {
            return [['scope_type' => 'department', 'scope_id' => $user->department_id]];
        }
        if ($user->branch_id) {
            return [['scope_type' => 'branch', 'scope_id' => $user->branch_id]];
        }
        if ($user->company_id) {
            return [['scope_type' => 'company', 'scope_id' => $user->company_id]];
        }

        return [];
    }

    public static function hasGlobalScope(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public static function isInScope(User $user, Model $resource): bool
    {
        if (static::hasGlobalScope($user)) {
            return true;
        }

        $scopes = static::getEffectiveScopes($user);
        foreach ($scopes as $scope) {
            if (static::resourceMatchesScope($resource, $scope['scope_type'], $scope['scope_id'])) {
                return true;
            }
        }

        return false;
    }

    protected static function resourceMatchesScope(Model $resource, string $scopeType, int $scopeId): bool
    {
        if ($resource instanceof Company) {
            // Only company scope grants access to a company
            return $scopeType === 'company' && $resource->id === $scopeId;
        }
        if ($resource instanceof Region) {
            return in_array($resource->id, static::getRegionIdsForScope($scopeType, $scopeId));
        }
        if ($resource instanceof Branch) {
            return in_array($resource->id, static::getBranchIdsForScope($scopeType, $scopeId));
        }
        if ($resource instanceof Department) {
            // For unsaved models (no ID), check by attributes directly
            if (! $resource->exists) {
                return match ($scopeType) {
                    'company' => $resource->company_id === $scopeId,
                    'branch' => $resource->branch_id === $scopeId,
                    'region' => $resource->branch && $resource->branch->region_id === $scopeId,
                    'department' => false, // Can't match unsaved model to existing dept scope
                    default => false,
                };
            }

            return in_array($resource->id, static::getDepartmentIdsForScope($scopeType, $scopeId));
        }
        if ($resource instanceof Asset) {
            // Assets are in scope if their parent Site is in scope
            if (! $resource->site) {
                return false;
            }

            return static::isSiteInScope($resource->site, $scopeType, $scopeId);
        }
        if ($resource instanceof Site) {
            return static::isSiteInScope($resource, $scopeType, $scopeId);
        }
        if ($resource instanceof FiberCable) {
            return static::isFiberCableInScope($resource, $scopeType, $scopeId);
        }
        if ($resource instanceof NetworkConnectionPoint) {
            return static::isNetworkConnectionPointInScope($resource, $scopeType, $scopeId);
        }
        if ($resource instanceof FiberSegment) {
            return static::isFiberSegmentInScope($resource, $scopeType, $scopeId);
        }
        if ($resource instanceof FiberCore) {
            return static::isFiberCoreInScope($resource, $scopeType, $scopeId);
        }
        if ($resource instanceof FiberTermination) {
            return $scopeType === 'company' && (int) $resource->company_id === $scopeId;
        }
        if ($resource instanceof PhysicalConnection) {
            return $scopeType === 'company' && (int) $resource->company_id === $scopeId;
        }
        if ($resource instanceof PassiveOpticalPort) {
            return $scopeType === 'company' && (int) $resource->company_id === $scopeId;
        }
        if ($resource instanceof Integration) {
            // Global (system-level) integrations are only reachable via the global scope
            if ($resource->company_id === null) {
                return false;
            }

            return $scopeType === 'company' && $resource->company_id === $scopeId;
        }
        if ($resource instanceof IntegrationCredential) {
            $integration = $resource->integration;
            if (! $integration) {
                return false;
            }

            return static::resourceMatchesScope($integration, $scopeType, $scopeId);
        }
        if ($resource instanceof User) {
            return static::isUserInScope($resource, $scopeType, $scopeId);
        }

        return false;
    }

    /**
     * Apply scope constraints to a query builder.
     * Pre-resolves IDs in PHP and uses simple whereIn for reliability.
     */
    public static function applyScopeToQuery(Builder $query, User $user, string $modelClass): Builder
    {
        if (static::hasGlobalScope($user)) {
            return $query;
        }

        $scopes = static::getEffectiveScopes($user);
        if (empty($scopes)) {
            return $query->whereRaw('1 = 0');
        }

        $allowedIds = static::resolveAllowedIds($scopes, $modelClass);

        if (empty($allowedIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($query->getModel()->getTable().'.id', $allowedIds);
    }

    /**
     * Resolve all allowed resource IDs for a set of scopes.
     */
    protected static function resolveAllowedIds(array $scopes, string $modelClass): array
    {
        $ids = [];

        foreach ($scopes as $scope) {
            $type = $scope['scope_type'];
            $id = $scope['scope_id'];

            if ($modelClass === Company::class) {
                // Only company scopes grant access to companies
                if ($type === 'company') {
                    $ids[] = $id;
                }
            } elseif ($modelClass === Region::class) {
                $ids = array_merge($ids, static::getRegionIdsForScope($type, $id));
            } elseif ($modelClass === Branch::class) {
                $ids = array_merge($ids, static::getBranchIdsForScope($type, $id));
            } elseif ($modelClass === Department::class) {
                $ids = array_merge($ids, static::getDepartmentIdsForScope($type, $id));
            } elseif ($modelClass === Site::class) {
                $ids = array_merge($ids, static::getSiteIdsForScope($type, $id));
            } elseif ($modelClass === Integration::class) {
                if ($type === 'company') {
                    $ids = array_merge($ids, Integration::where('company_id', $id)->pluck('id')->toArray());
                }
            } elseif ($modelClass === Asset::class) {
                $siteIds = static::getSiteIdsForScope($type, $id);
                if (! empty($siteIds)) {
                    $assetIds = Asset::whereIn('site_id', $siteIds)->pluck('id')->toArray();
                    $ids = array_merge($ids, $assetIds);
                }
            } elseif ($modelClass === FiberCable::class) {
                $ids = array_merge($ids, static::getFiberCableIdsForScope($type, $id));
            } elseif ($modelClass === NetworkConnectionPoint::class) {
                $ids = array_merge($ids, static::getNetworkConnectionPointIdsForScope($type, $id));
            } elseif ($modelClass === FiberSegment::class) {
                $ids = array_merge($ids, static::getFiberSegmentIdsForScope($type, $id));
            } elseif ($modelClass === FiberCore::class) {
                $ids = array_merge($ids, static::getFiberCoreIdsForScope($type, $id));
            } elseif ($modelClass === FiberTermination::class) {
                if ($type === 'company') {
                    $ids = array_merge($ids, FiberTermination::where('company_id', $id)->pluck('id')->toArray());
                }
            } elseif ($modelClass === PhysicalConnection::class) {
                if ($type === 'company') {
                    $ids = array_merge($ids, PhysicalConnection::where('company_id', $id)->pluck('id')->toArray());
                }
            } elseif ($modelClass === PassiveOpticalPort::class) {
                if ($type === 'company') {
                    $ids = array_merge($ids, PassiveOpticalPort::where('company_id', $id)->pluck('id')->toArray());
                }
            } elseif ($modelClass === User::class) {
                $ids = array_merge($ids, static::getUserIdsForScope($type, $id));
            }
        }

        return array_unique(array_filter($ids));
    }

    /**
     * Resolve the integration IDs a user may operate on, based on their
     * company-scoped management scopes. Global (system-level) integrations
     * are intentionally excluded from non-global scopes.
     */
    public static function resolveAllowedIntegrationIds(User $user): array
    {
        $scopes = static::getEffectiveScopes($user);
        $companyIds = [];
        foreach ($scopes as $scope) {
            if ($scope['scope_type'] === 'company') {
                $companyIds[] = $scope['scope_id'];
            }
        }

        if (empty($companyIds)) {
            return [];
        }

        return Integration::whereIn('company_id', $companyIds)
            ->pluck('id')
            ->toArray();
    }

    // ── ID Resolution Helpers ─────────────────────────────────

    protected static function getRegionIdsForScope(string $type, int $id): array
    {
        return match ($type) {
            'company' => Region::where('company_id', $id)->pluck('id')->toArray(),
            'region' => [$id],
            // Branch/department scope does NOT grant region access (no upward traversal)
            'branch' => [],
            'department' => [],
            default => [],
        };
    }

    protected static function getBranchIdsForScope(string $type, int $id): array
    {
        return match ($type) {
            'company' => Branch::whereHas('region', fn ($q) => $q->where('company_id', $id))->pluck('id')->toArray(),
            'region' => Branch::where('region_id', $id)->pluck('id')->toArray(),
            'branch' => [$id],
            // Department scope does NOT grant branch access (no upward traversal)
            'department' => [],
            default => [],
        };
    }

    protected static function getDepartmentIdsForScope(string $type, int $id): array
    {
        return match ($type) {
            'company' => Department::where('company_id', $id)->pluck('id')->toArray(),
            'region' => Department::whereHas('branch', fn ($q) => $q->where('region_id', $id))->pluck('id')->toArray(),
            'branch' => Department::where('branch_id', $id)->pluck('id')->toArray(),
            'department' => [$id],
            default => [],
        };
    }

    protected static function getUserIdsForScope(string $type, int $id): array
    {
        return match ($type) {
            'company' => User::where('company_id', $id)->pluck('id')->toArray(),
            'region' => User::whereHas('branch', fn ($q) => $q->where('region_id', $id))->pluck('id')->toArray(),
            'branch' => User::where('branch_id', $id)->pluck('id')->toArray(),
            'department' => User::where('department_id', $id)->pluck('id')->toArray(),
            default => [],
        };
    }

    protected static function isUserInScope(User $user, string $scopeType, int $scopeId): bool
    {
        return match ($scopeType) {
            'company' => $user->company_id === $scopeId,
            'region' => $user->branch && $user->branch->region_id === $scopeId,
            'branch' => $user->branch_id === $scopeId,
            'department' => $user->department_id === $scopeId,
            default => false,
        };
    }

    // ── Scope Assignment Authority ────────────────────────────

    public static function canGrantScope(User $actor, string $scopeType, int $scopeId): bool
    {
        if (static::hasGlobalScope($actor)) {
            return UserManagementScope::validateScope($scopeType, $scopeId);
        }

        $actorScopes = static::getEffectiveScopes($actor);
        foreach ($actorScopes as $scope) {
            if (static::scopeContainsTarget($scope['scope_type'], $scope['scope_id'], $scopeType, $scopeId)) {
                return true;
            }
        }

        return false;
    }

    protected static function scopeContainsTarget(string $actorType, int $actorId, string $targetType, int $targetId): bool
    {
        if ($actorType === $targetType && $actorId === $targetId) {
            return true;
        }

        if ($actorType === 'company') {
            if ($targetType === 'region') {
                return Region::where('id', $targetId)->where('company_id', $actorId)->exists();
            }
            if ($targetType === 'branch') {
                return Branch::whereHas('region', fn ($q) => $q->where('company_id', $actorId))->where('id', $targetId)->exists();
            }
            if ($targetType === 'department') {
                return Department::where('company_id', $actorId)->where('id', $targetId)->exists();
            }
        }

        if ($actorType === 'region') {
            if ($targetType === 'branch') {
                return Branch::where('id', $targetId)->where('region_id', $actorId)->exists();
            }
            if ($targetType === 'department') {
                return Department::whereHas('branch', fn ($q) => $q->where('region_id', $actorId))->where('id', $targetId)->exists();
            }
        }

        if ($actorType === 'branch' && $targetType === 'department') {
            return Department::where('id', $targetId)->where('branch_id', $actorId)->exists();
        }

        return false;
    }

    protected static function isSiteInScope(Model $resource, string $scopeType, int $scopeId): bool
    {
        // A site is in scope if its organizational ownership matches the scope
        if ($scopeType === 'company') {
            return $resource->company_id === $scopeId;
        }
        if ($scopeType === 'region') {
            return $resource->region_id === $scopeId;
        }
        if ($scopeType === 'branch') {
            return $resource->branch_id === $scopeId;
        }

        // Department scope does not directly apply to Sites unless we add department_id to Sites later
        return false;
    }

    protected static function getSiteIdsForScope(string $type, int $id): array
    {
        return match ($type) {
            'company' => Site::where('company_id', $id)->pluck('id')->toArray(),
            'region' => Site::where('region_id', $id)->pluck('id')->toArray(),
            'branch' => Site::where('branch_id', $id)->pluck('id')->toArray(),
            'department' => [], // Sites are not scoped to departments in this foundation
            default => [],
        };
    }

    /**
     * FiberCable scope is intentionally company-restrictive in FIM-002.
     *
     * A cable carries only company_id plus optional coarse site anchors and can
     * span regions/branches, so region/branch/department scopes cannot be derived
     * safely from current data. Those scopes grant no cable access rather than
     * broadening existing authorization.
     */
    protected static function isFiberCableInScope(FiberCable $resource, string $scopeType, int $scopeId): bool
    {
        if ($scopeType === 'company') {
            return (int) $resource->company_id === $scopeId;
        }

        return false;
    }

    protected static function getFiberCableIdsForScope(string $type, int $id): array
    {
        if ($type === 'company') {
            return FiberCable::where('company_id', $id)->pluck('id')->toArray();
        }

        return [];
    }

    /**
     * NetworkConnectionPoint scope inherits from site company.
     *
     * Region/branch/department scopes do NOT grant NCP access unless the
     * NCP's site is in scope. Conservative: deny unless company scope matches.
     */
    protected static function isNetworkConnectionPointInScope(NetworkConnectionPoint $point, string $scopeType, int $scopeId): bool
    {
        if ($scopeType === 'company' && $point->company_id === $scopeId) {
            return true;
        }

        if ($point->site && static::isSiteInScope($point->site, $scopeType, $scopeId)) {
            return true;
        }

        return false;
    }

    protected static function getNetworkConnectionPointIdsForScope(string $type, int $id): array
    {
        $siteIds = static::getSiteIdsForScope($type, $id);

        if (! empty($siteIds)) {
            return NetworkConnectionPoint::whereIn('site_id', $siteIds)->pluck('id')->toArray();
        }

        return NetworkConnectionPoint::where('company_id', $id)->pluck('id')->toArray();
    }

    protected static function isFiberSegmentInScope(FiberSegment $segment, string $scopeType, int $scopeId): bool
    {
        return $scopeType === 'company' && (int) $segment->company_id === $scopeId;
    }

    protected static function getFiberSegmentIdsForScope(string $type, int $id): array
    {
        if ($type !== 'company') {
            return [];
        }

        return FiberSegment::where('company_id', $id)->pluck('id')->toArray();
    }

    protected static function isFiberCoreInScope(FiberCore $core, string $scopeType, int $scopeId): bool
    {
        return $scopeType === 'company' && (int) $core->company_id === $scopeId;
    }

    protected static function getFiberCoreIdsForScope(string $type, int $id): array
    {
        if ($type !== 'company') {
            return [];
        }

        return FiberCore::where('company_id', $id)->pluck('id')->toArray();
    }
}
