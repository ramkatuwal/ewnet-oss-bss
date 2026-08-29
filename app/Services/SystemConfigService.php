<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemConfigService
{
    protected const CACHE_TTL = 3600; // 1 hour

    protected static array $allowedKeys = [
        // Branding
        'app_name' => ['group' => 'branding', 'default' => 'EWNET'],
        'browser_title' => ['group' => 'branding', 'default' => 'EWNET OSS/BSS'],
        'logo_path' => ['group' => 'branding', 'default' => null],
        'favicon_path' => ['group' => 'branding', 'default' => null],
        'login_branding' => ['group' => 'branding', 'default' => 'EWNET'],
        // Navigation
        'menu_visibility' => ['group' => 'navigation', 'default' => '{}'],
        'menu_ordering' => ['group' => 'navigation', 'default' => '{}'],
        // Header
        'show_logo' => ['group' => 'header', 'default' => true],
        'show_title' => ['group' => 'header', 'default' => true],
        'show_user_menu' => ['group' => 'header', 'default' => true],
        'show_notifications' => ['group' => 'header', 'default' => true],
        // Theme
        'compactness' => ['group' => 'theme', 'default' => 'compact'],
        'dark_mode' => ['group' => 'theme', 'default' => false],
        'primary_color' => ['group' => 'theme', 'default' => '#1976d2'],
    ];

    protected static array $protectedKeys = [
        'app_key',
        'app_debug',
        'database_password',
        'redis_password',
        'api_secret',
        'smtp_password',
        'snmp_community',
        'private_key',
        'token',
        'credential',
        'password',
        'secret',
    ];

    public static function getAll(): array
    {
        $settings = self::getSettingsFromCache();

        $result = [];
        foreach (self::$allowedKeys as $key => $config) {
            $value = $settings[$key] ?? $config['default'];
            $group = $config['group'];

            if (!isset($result[$group])) {
                $result[$group] = [];
            }

            // Cast boolean values
            if (in_array($key, ['show_logo', 'show_title', 'show_user_menu', 'show_notifications', 'dark_mode'])) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $result[$group][$key] = $value;
        }

        return $result;
    }

    public static function get(string $key)
    {
        if (!isset(self::$allowedKeys[$key])) {
            return null;
        }

        $settings = self::getSettingsFromCache();
        $value = $settings[$key] ?? self::$allowedKeys[$key]['default'];

        // Cast boolean values
        if (in_array($key, ['show_logo', 'show_title', 'show_user_menu', 'show_notifications', 'dark_mode'])) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }

    public static function update(array $data, int $userId): array
    {
        $updated = [];
        $changedKeys = [];

        foreach ($data as $group => $values) {
            if (!is_array($values)) {
                continue;
            }

            foreach ($values as $key => $value) {
                $fullKey = $key;

                // Check if key is allowed
                if (!isset(self::$allowedKeys[$fullKey])) {
                    continue;
                }

                // Check if key is protected
                if (self::isProtectedKey($fullKey)) {
                    continue;
                }

                // Validate value based on type
                $validatedValue = self::validateValue($fullKey, $value);
                if ($validatedValue === null) {
                    continue;
                }

                $oldValue = self::get($fullKey);

                // Convert boolean to string for storage
                if (is_bool($validatedValue)) {
                    $storedValue = $validatedValue ? 'true' : 'false';
                } else {
                    $storedValue = (string) $validatedValue;
                }

                // Update or create
                $setting = SystemSetting::updateOrCreate(
                    ['key' => $fullKey],
                    [
                        'value' => $storedValue,
                        'group' => self::$allowedKeys[$fullKey]['group'],
                        'updated_by' => $userId,
                    ]
                );

                $updated[$fullKey] = $validatedValue;
                $changedKeys[] = $fullKey;

                // Clear cache for this key
                Cache::forget('system_settings');
                Cache::forget('system_settings_cache');
            }
        }

        // Clear all possible cache keys
        Cache::forget('system_settings');
        Cache::forget('system_settings_cache');
        Cache::tags(['system', 'settings', 'config'])->flush();

        return [
            'updated' => $updated,
            'changed_keys' => $changedKeys,
        ];
    }

    protected static function getSettingsFromCache(): array
    {
        // Always clear cache before fetching to ensure fresh data
        Cache::forget('system_settings');
        
        return Cache::remember('system_settings', self::CACHE_TTL, function () {
            $settings = SystemSetting::all();
            $result = [];
            foreach ($settings as $setting) {
                $result[$setting->key] = $setting->value;
            }
            return $result;
        });
    }

    protected static function isProtectedKey(string $key): bool
    {
        $lowerKey = strtolower($key);
        foreach (self::$protectedKeys as $protected) {
            if (str_contains($lowerKey, $protected)) {
                return true;
            }
        }
        return false;
    }

    protected static function validateValue(string $key, $value)
    {
        switch ($key) {
            case 'app_name':
            case 'browser_title':
            case 'login_branding':
                return is_string($value) ? substr($value, 0, 255) : null;

            case 'logo_path':
            case 'favicon_path':
                return is_string($value) ? substr($value, 0, 255) : null;

            case 'menu_visibility':
            case 'menu_ordering':
                return is_array($value) ? json_encode($value) : '{}';

            case 'show_logo':
            case 'show_title':
            case 'show_user_menu':
            case 'show_notifications':
            case 'dark_mode':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);

            case 'compactness':
                return in_array($value, ['compact', 'comfortable', 'spacious']) ? $value : 'compact';

            case 'primary_color':
                return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : '#1976d2';

            default:
                return null;
        }
    }

    public static function getGroups(): array
    {
        return ['branding', 'navigation', 'header', 'theme'];
    }

    public static function getDefault(string $key)
    {
        return self::$allowedKeys[$key]['default'] ?? null;
    }

    public static function getAllowedKeys(): array
    {
        return self::$allowedKeys;
    }
}
