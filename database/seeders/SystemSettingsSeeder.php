<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Branding
            ['key' => 'app_name', 'value' => '"EWNET"', 'group' => 'branding', 'description' => 'Application name'],
            ['key' => 'browser_title', 'value' => '"EWNET OSS/BSS"', 'group' => 'branding', 'description' => 'Browser title'],
            ['key' => 'logo_path', 'value' => 'null', 'group' => 'branding', 'description' => 'Logo file path'],
            ['key' => 'favicon_path', 'value' => 'null', 'group' => 'branding', 'description' => 'Favicon file path'],
            ['key' => 'login_branding', 'value' => '"EWNET"', 'group' => 'branding', 'description' => 'Login page branding text'],
            // Navigation
            ['key' => 'menu_visibility', 'value' => '{}', 'group' => 'navigation', 'description' => 'Menu visibility settings'],
            ['key' => 'menu_ordering', 'value' => '{}', 'group' => 'navigation', 'description' => 'Menu ordering settings'],
            // Header
            ['key' => 'show_logo', 'value' => 'true', 'group' => 'header', 'description' => 'Show logo in header'],
            ['key' => 'show_title', 'value' => 'true', 'group' => 'header', 'description' => 'Show title in header'],
            ['key' => 'show_user_menu', 'value' => 'true', 'group' => 'header', 'description' => 'Show user menu in header'],
            ['key' => 'show_notifications', 'value' => 'true', 'group' => 'header', 'description' => 'Show notifications in header'],
            // Theme
            ['key' => 'compactness', 'value' => '"compact"', 'group' => 'theme', 'description' => 'UI compactness: compact, comfortable, spacious'],
            ['key' => 'dark_mode', 'value' => 'false', 'group' => 'theme', 'description' => 'Dark mode enabled'],
            ['key' => 'primary_color', 'value' => '"#1976d2"', 'group' => 'theme', 'description' => 'Primary color hex code'],
        ];

        foreach ($defaults as $default) {
            SystemSetting::firstOrCreate(
                ['key' => $default['key']],
                [
                    'value' => $default['value'],
                    'group' => $default['group'],
                    'description' => $default['description'],
                ]
            );
        }
    }
}
