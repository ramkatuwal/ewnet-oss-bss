<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class AuditService
{
    public static function log(
        string $action,
        string $result,
        ?object $target = null,
        array $metadata = [],
        ?array $orgContext = null
    ): void {
        try {
            $user = Auth::user();
            $request = request();

            // SANITIZE METADATA: Remove any potential secrets
            $safeMetadata = collect($metadata)->forget(['password', 'token', 'secret', 'api_key', 'credentials'])->toArray();

            AuditLog::create([
                'actor_type' => $user ? get_class($user) : 'guest',
                'actor_id' => $user ? $user->id : null,
                'action' => $action,
                'target_type' => $target ? get_class($target) : null,
                'target_id' => $target && method_exists($target, 'getKey') ? $target->getKey() : null,
                'organization_context' => $orgContext ?? ($user ? [
                    'company_id' => $user->company_id ?? null,
                    'branch_id' => $user->branch_id ?? null,
                    'department_id' => $user->department_id ?? null,
                ] : null),
                'result' => $result,
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? $request->userAgent() : null,
                'correlation_id' => $request && $request->hasHeader('X-Correlation-ID') 
                    ? $request->header('X-Correlation-ID') 
                    : (string) Str::uuid(),
                'metadata' => $safeMetadata,
            ]);
        } catch (\Exception $e) {
            // CRITICAL: Audit failure must NOT silently allow the original operation to succeed if it was a security check.
            // However, for general logging, we catch and report to error log to avoid breaking the app.
            \Log::error('Audit logging failed: ' . $e->getMessage());
            
            // If this was a critical security denial, we might want to throw, but for now, fail closed on the log, not the app.
        }
    }
}
