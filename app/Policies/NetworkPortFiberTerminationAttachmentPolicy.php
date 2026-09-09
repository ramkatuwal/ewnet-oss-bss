<?php

namespace App\Policies;

use App\Models\FiberTermination;
use App\Models\NetworkPort;
use App\Models\NetworkPortFiberTerminationAttachment;
use App\Models\User;
use App\Services\ManagementScopeService;

class NetworkPortFiberTerminationAttachmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('net.network-port-fiber-attachments.view');
    }

    public function view(User $user, NetworkPortFiberTerminationAttachment $attachment): bool
    {
        return $this->viewAny($user)
            && ManagementScopeService::isInScope($user, $attachment)
            && $attachment->networkPort !== null && $attachment->fiberTermination !== null
            && $user->can('view', $attachment->networkPort)
            && $user->can('view', $attachment->fiberTermination);
    }

    public function create(User $user, NetworkPort $port, FiberTermination $termination): bool
    {
        return $user->hasPermissionTo('net.network-port-fiber-attachments.create')
            && $user->can('view', $port) && $user->can('view', $termination);
    }

    public function delete(User $user, NetworkPortFiberTerminationAttachment $attachment): bool
    {
        return $user->hasPermissionTo('net.network-port-fiber-attachments.delete')
            && ManagementScopeService::isInScope($user, $attachment)
            && $attachment->networkPort !== null && $attachment->fiberTermination !== null
            && $user->can('view', $attachment->networkPort)
            && $user->can('view', $attachment->fiberTermination);
    }
}
