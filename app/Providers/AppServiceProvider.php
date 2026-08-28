<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationAttempt;
use App\Policies\SystemPolicy;
use App\Models\SystemSetting;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register policies
        Gate::policy(SystemSetting::class, SystemPolicy::class);

        // Authentication Events
        Event::listen(Attempting::class, [LogAuthenticationAttempt::class, 'handleAttempting']);
        Event::listen(Failed::class, [LogAuthenticationAttempt::class, 'handleFailed']);
        Event::listen(Login::class, [LogAuthenticationAttempt::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthenticationAttempt::class, 'handleLogout']);
    }
}
