<?php

namespace App\Providers;

use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\PhysicalConnection;
use App\Models\Site;
use App\Policies\FiberCablePolicy;
use App\Policies\FiberCorePolicy;
use App\Policies\FiberSegmentPolicy;
use App\Policies\FiberTerminationPolicy;
use App\Policies\NetworkConnectionPointPolicy;
use App\Policies\PassiveOpticalPortPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PhysicalConnectionPolicy;
use App\Policies\RolePolicy;
use App\Policies\SitePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        'App\Models\SystemSetting' => 'App\Policies\SystemSettingPolicy',
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
        Site::class => SitePolicy::class,
        FiberCable::class => FiberCablePolicy::class,
        FiberCore::class => FiberCorePolicy::class,
        FiberSegment::class => FiberSegmentPolicy::class,
        FiberTermination::class => FiberTerminationPolicy::class,
        PhysicalConnection::class => PhysicalConnectionPolicy::class,
        PassiveOpticalPort::class => PassiveOpticalPortPolicy::class,
        NetworkConnectionPoint::class => NetworkConnectionPointPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Explicitly register policies for Spatie models and Site
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(FiberCable::class, FiberCablePolicy::class);
        Gate::policy(FiberCore::class, FiberCorePolicy::class);
        Gate::policy(FiberSegment::class, FiberSegmentPolicy::class);
        Gate::policy(FiberTermination::class, FiberTerminationPolicy::class);
        Gate::policy(PhysicalConnection::class, PhysicalConnectionPolicy::class);
        Gate::policy(PassiveOpticalPort::class, PassiveOpticalPortPolicy::class);
        Gate::policy(NetworkConnectionPoint::class, NetworkConnectionPointPolicy::class);
    }
}
