<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\FiberTerminationPortAttachment;
use App\Models\Lead;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\PhysicalConnection;
use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Models\RoutingL3InterfaceAddress;
use App\Models\Service;
use App\Models\Site;
use App\Models\StaticRoute;
use App\Policies\CustomerPolicy;
use App\Policies\CustomerServicePolicy;
use App\Policies\FiberCablePolicy;
use App\Policies\FiberCorePolicy;
use App\Policies\FiberSegmentPolicy;
use App\Policies\FiberTerminationPolicy;
use App\Policies\FiberTerminationPortAttachmentPolicy;
use App\Policies\LeadPolicy;
use App\Policies\NetworkConnectionPointPolicy;
use App\Policies\PassiveOpticalPortPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\PhysicalConnectionPolicy;
use App\Policies\RolePolicy;
use App\Policies\RoutingInstancePolicy;
use App\Policies\RoutingL3InterfaceAddressPolicy;
use App\Policies\RoutingL3InterfacePolicy;
use App\Policies\ServicePolicy;
use App\Policies\SitePolicy;
use App\Policies\StaticRoutePolicy;
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
        Customer::class => CustomerPolicy::class,
        Service::class => ServicePolicy::class,
        CustomerService::class => CustomerServicePolicy::class,
        Lead::class => LeadPolicy::class,
        FiberCable::class => FiberCablePolicy::class,
        FiberCore::class => FiberCorePolicy::class,
        FiberSegment::class => FiberSegmentPolicy::class,
        FiberTermination::class => FiberTerminationPolicy::class,
        FiberTerminationPortAttachment::class => FiberTerminationPortAttachmentPolicy::class,
        PhysicalConnection::class => PhysicalConnectionPolicy::class,
        PassiveOpticalPort::class => PassiveOpticalPortPolicy::class,
        NetworkConnectionPoint::class => NetworkConnectionPointPolicy::class,
        RoutingInstance::class => RoutingInstancePolicy::class,
        RoutingL3Interface::class => RoutingL3InterfacePolicy::class,
        RoutingL3InterfaceAddress::class => RoutingL3InterfaceAddressPolicy::class,
        StaticRoute::class => StaticRoutePolicy::class,
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
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(CustomerService::class, CustomerServicePolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(FiberCable::class, FiberCablePolicy::class);
        Gate::policy(FiberCore::class, FiberCorePolicy::class);
        Gate::policy(FiberSegment::class, FiberSegmentPolicy::class);
        Gate::policy(FiberTermination::class, FiberTerminationPolicy::class);
        Gate::policy(FiberTerminationPortAttachment::class, FiberTerminationPortAttachmentPolicy::class);
        Gate::policy(PhysicalConnection::class, PhysicalConnectionPolicy::class);
        Gate::policy(PassiveOpticalPort::class, PassiveOpticalPortPolicy::class);
        Gate::policy(NetworkConnectionPoint::class, NetworkConnectionPointPolicy::class);
        Gate::policy(RoutingInstance::class, RoutingInstancePolicy::class);
        Gate::policy(RoutingL3Interface::class, RoutingL3InterfacePolicy::class);
        Gate::policy(RoutingL3InterfaceAddress::class, RoutingL3InterfaceAddressPolicy::class);
        Gate::policy(StaticRoute::class, StaticRoutePolicy::class);
    }
}
