<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SystemConfigService;
use Illuminate\Http\Request;

class PublicBrandingController extends Controller
{
    /**
     * Returns only public branding data (logo, app name) for unauthenticated pages
     */
    public function index()
    {
        $config = SystemConfigService::getAll();

        return response()->json([
            'data' => [
                'app_name' => $config['branding']['app_name'] ?? 'EWNET',
                'logo_path' => $config['branding']['logo_path'] ?? null,
                'login_branding' => $config['branding']['login_branding'] ?? 'EWNET',
            ]
        ]);
    }
}
