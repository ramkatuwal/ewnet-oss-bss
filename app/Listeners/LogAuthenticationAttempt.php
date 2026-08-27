<?php

namespace App\Listeners;

use App\Services\AuditService;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthenticationAttempt
{
    public function handleAttempting(Attempting $event): void
    {
        // We don't log the password, just the attempt
        AuditService::log(
            action: 'auth.login.attempt',
            result: 'pending',
            metadata: ['email' => $event->credentials['email'] ?? 'unknown']
        );
    }

    public function handleFailed(Failed $event): void
    {
        AuditService::log(
            action: 'auth.login.failure',
            result: 'failure',
            metadata: ['email' => $event->credentials['email'] ?? 'unknown', 'reason' => 'invalid_credentials']
        );
    }

    public function handleLogin(Login $event): void
    {
        AuditService::log(
            action: 'auth.login.success',
            result: 'success',
            target: $event->user
        );
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            AuditService::log(
                action: 'auth.logout',
                result: 'success',
                target: $event->user
            );
        }
    }
}
