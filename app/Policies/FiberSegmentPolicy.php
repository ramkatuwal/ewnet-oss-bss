<?php

namespace App\Policies;

use App\Models\FiberSegment;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class FiberSegmentPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('fim.fiber-segments.view');
    }

    public function view(User $user, FiberSegment $segment): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.fiber-segments.view', $segment);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('fim.fiber-segments.create');
    }

    public function update(User $user, FiberSegment $segment): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.fiber-segments.update', $segment);
    }

    public function delete(User $user, FiberSegment $segment): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.fiber-segments.delete', $segment);
    }
}
