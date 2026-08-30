<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Policies\RolePolicy;
use App\Policies\PermissionPolicy;
use App\Models\Site;
use App\Policies\SitePolicy;

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
    }
}
