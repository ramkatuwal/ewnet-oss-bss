<?php

declare(strict_types=1);

/*
 * Test bootstrap guard.
 *
 * Two hazards exist in this repository and both are neutralised here before
 * the Laravel application boots:
 *
 * 1. Config cache: the development deployment (re)builds
 *    `bootstrap/cache/config.php` via `php artisan config:cache`, which
 *    freezes the application database to `ewnet`. If that file is present
 *    while PHPUnit runs, the cached config takes precedence over every
 *    environment override and a `RefreshDatabase`-based suite would run
 *    `migrate:fresh` against the development database, wiping it. The cached
 *    config is removed below so tests always load configuration fresh.
 *
 * 2. Inherited environment: docker-compose injects `DB_DATABASE=ewnet` (and
 *    other application variables) into the real process environment, visible
 *    through `$_SERVER`. PHPUnit's `<env force="true">` only calls
 *    `putenv()`; it does not overwrite `$_SERVER`, and Laravel's `env()`
 *    reads `$_SERVER` first. Tests would therefore still resolve the
 *    development database. This bootstrap pins the test values across
 *    `$_SERVER`, `$_ENV` and `putenv()` so no `RefreshDatabase` suite can
 *    ever target the `ewnet` development database.
 */

$configCache = __DIR__.'/../bootstrap/cache/config.php';

if (is_file($configCache)) {
    @unlink($configCache);
}

$testEnv = [
    'APP_ENV' => 'testing',
    'BCRYPT_ROUNDS' => '4',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => 'postgres',
    'DB_PORT' => '5432',
    'DB_DATABASE' => 'ewnet_test',
    'DB_USERNAME' => 'ewnet',
    'DB_PASSWORD' => 'ewnet123',
    'CACHE_STORE' => 'array',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'TELESCOPE_ENABLED' => 'false',
];

foreach ($testEnv as $name => $value) {
    $_SERVER[$name] = $value;
    $_ENV[$name] = $value;
    putenv(sprintf('%s=%s', $name, $value));
}