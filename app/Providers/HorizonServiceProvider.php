<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class HorizonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Horizon::auth(function ($request) {
            $user = $request->user();

            if (! $user) {
                return false;
            }

            return $user->hasRole('Super Admin')
                || $user->can('system.debug.view');
        });
    }
}
