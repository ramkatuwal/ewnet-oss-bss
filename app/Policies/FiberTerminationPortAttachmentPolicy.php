<?php

namespace App\Policies;

use App\Models\FiberTermination;
use App\Models\FiberTerminationPortAttachment;
use App\Models\PassiveOpticalPort;
use App\Models\User;
use App\Policies\Concerns\ChecksManagementScope;

class FiberTerminationPortAttachmentPolicy
{
    use ChecksManagementScope;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('fim.termination-port-attachments.view');
    }

    public function view(User $user, FiberTerminationPortAttachment $attachment): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.termination-port-attachments.view', $attachment)
            && $user->can('view', $attachment->fiberTermination)
            && $user->can('view', $attachment->passiveOpticalPort);
    }

    public function create(User $user, FiberTermination $termination, PassiveOpticalPort $port): bool
    {
        return $user->hasPermissionTo('fim.termination-port-attachments.create')
            && $user->can('view', $termination)
            && $user->can('view', $port);
    }

    public function delete(User $user, FiberTerminationPortAttachment $attachment): bool
    {
        return $this->hasPermissionAndInScope($user, 'fim.termination-port-attachments.delete', $attachment)
            && $user->can('view', $attachment->fiberTermination)
            && $user->can('view', $attachment->passiveOpticalPort);
    }
}
