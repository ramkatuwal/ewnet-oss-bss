<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'branding' => [
                'app_name' => $this['branding']['app_name'] ?? 'EWNET',
                'browser_title' => $this['branding']['browser_title'] ?? 'EWNET OSS/BSS',
                'logo_path' => $this['branding']['logo_path'] ?? null,
                'favicon_path' => $this['branding']['favicon_path'] ?? null,
                'login_branding' => $this['branding']['login_branding'] ?? 'EWNET',
            ],
            'navigation' => [
                'menu_visibility' => $this['navigation']['menu_visibility'] ?? (object) [],
                'menu_ordering' => $this['navigation']['menu_ordering'] ?? (object) [],
            ],
            'header' => [
                'show_logo' => $this['header']['show_logo'] ?? true,
                'show_title' => $this['header']['show_title'] ?? true,
                'show_user_menu' => $this['header']['show_user_menu'] ?? true,
                'show_notifications' => $this['header']['show_notifications'] ?? true,
            ],
            'theme' => [
                'compactness' => $this['theme']['compactness'] ?? 'compact',
                'dark_mode' => $this['theme']['dark_mode'] ?? false,
                'primary_color' => $this['theme']['primary_color'] ?? '#1976d2',
            ],
        ];
    }
}
