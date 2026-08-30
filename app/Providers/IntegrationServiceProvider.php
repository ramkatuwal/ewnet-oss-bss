<?php

namespace App\Providers;

use App\Services\Integrations\IntegrationManager;
use App\Integrations\Providers\LibreNMS\LibreNMSProvider;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        IntegrationManager::register('librenms', LibreNMSProvider::class);
    }
}
