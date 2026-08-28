<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Authorization check
        if (!$user || !$user->can('system.debug.view')) {
            abort(403, 'Unauthorized to view audit logs');
        }

        $isSuperAdmin = $this->isSuperAdmin($user);

        $query = AuditLog::query()
            ->with(['actor', 'target'])
            ->orderBy('created_at', 'desc');

        // Management scope filtering for non-Super Admin users
        if (!$isSuperAdmin) {
            $query = $this->applyManagementScopeFilter($query, $user);
        }

        // Apply filters
        $query = $this->applyFilters($query, $request);

        // Apply search
        if ($search = $request->input('search')) {
            $query = $this->applySearch($query, $search);
        }

        // Apply date range
        $query = $this->applyDateRange($query, $request);

        // Paginate
        $perPage = min($request->input('per_page', 25), 100);
        $logs = $query->paginate($perPage);

        return AuditLogResource::collection($logs);
    }

    public function show(Request $request, AuditLog $auditLog)
    {
        $user = Auth::user();
        
        // Authorization check
        if (!$user || !$user->can('system.debug.view')) {
            abort(403, 'Unauthorized to view audit logs');
        }

        $isSuperAdmin = $this->isSuperAdmin($user);

        // Management scope check for non-Super Admin
        if (!$isSuperAdmin && !$this->isLogInScope($auditLog, $user)) {
            abort(404, 'Audit log not found');
        }

        $auditLog->load(['actor', 'target']);

        return new AuditLogResource($auditLog);
    }

    protected function isSuperAdmin($user): bool
    {
        // Check if user has Super Admin role
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('Super Admin');
        }
        
        // Fallback: check roles relationship
        if (method_exists($user, 'roles')) {
            return $user->roles()->where('name', 'Super Admin')->exists();
        }
        
        return false;
    }

    protected function applyManagementScopeFilter($query, $user)
    {
        $companyId = $user->company_id;
        $branchId = $user->branch_id;
        $departmentId = $user->department_id;

        // If user has no scope, return empty result
        if (!$companyId && !$branchId && !$departmentId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($companyId, $branchId, $departmentId) {
            $hasCondition = false;
            
            // Include logs where actor is in user's scope
            if ($companyId) {
                $q->orWhere(function ($subQ) use ($companyId) {
                    $subQ->where('actor_type', 'App\\Models\\User')
                        ->whereExists(function ($query) use ($companyId) {
                            $query->select(\DB::raw(1))
                                ->from('users')
                                ->whereColumn('users.id', 'audit_logs.actor_id')
                                ->where('users.company_id', $companyId);
                        });
                });
                $hasCondition = true;
            }

            // Or where target is in user's scope
            if ($companyId) {
                $q->orWhere(function ($subQ) use ($companyId) {
                    $subQ->where('target_type', 'App\\Models\\User')
                        ->whereExists(function ($query) use ($companyId) {
                            $query->select(\DB::raw(1))
                                ->from('users')
                                ->whereColumn('users.id', 'audit_logs.target_id')
                                ->where('users.company_id', $companyId);
                        });
                });
            }

            // Or where organization_context matches user's scope
            if ($departmentId) {
                $q->orWhereRaw("organization_context->>'department_id' = ?", [$departmentId]);
            }
            if ($branchId) {
                $q->orWhereRaw("organization_context->>'branch_id' = ?", [$branchId]);
            }
            if ($companyId) {
                $q->orWhereRaw("organization_context->>'company_id' = ?", [$companyId]);
            }
        });
    }

    protected function isLogInScope(AuditLog $log, $user): bool
    {
        $companyId = $user->company_id;
        $branchId = $user->branch_id;
        $departmentId = $user->department_id;

        // Check actor scope
        if ($log->actor_type === 'App\\Models\\User' && $log->actor_id) {
            $actor = \App\Models\User::find($log->actor_id);
            if ($actor) {
                if ($departmentId && $actor->department_id == $departmentId) return true;
                if ($branchId && $actor->branch_id == $branchId) return true;
                if ($companyId && $actor->company_id == $companyId) return true;
            }
        }

        // Check target scope
        if ($log->target_type === 'App\\Models\\User' && $log->target_id) {
            $target = \App\Models\User::find($log->target_id);
            if ($target) {
                if ($departmentId && $target->department_id == $departmentId) return true;
                if ($branchId && $target->branch_id == $branchId) return true;
                if ($companyId && $target->company_id == $companyId) return true;
            }
        }

        // Check organization_context
        if ($log->organization_context) {
            $ctx = $log->organization_context;
            if ($departmentId && isset($ctx['department_id']) && $ctx['department_id'] == $departmentId) return true;
            if ($branchId && isset($ctx['branch_id']) && $ctx['branch_id'] == $branchId) return true;
            if ($companyId && isset($ctx['company_id']) && $ctx['company_id'] == $companyId) return true;
        }

        return false;
    }

    protected function applyFilters($query, Request $request)
    {
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($result = $request->input('result')) {
            $query->where('result', $result);
        }

        if ($actorId = $request->input('actor_id')) {
            $query->where('actor_id', $actorId);
        }

        if ($targetType = $request->input('target_type')) {
            $query->where('target_type', $targetType);
        }

        return $query;
    }

    protected function applySearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('action', 'ilike', "%{$search}%")
                ->orWhere('result', 'ilike', "%{$search}%")
                ->orWhere('ip_address', 'ilike', "%{$search}%")
                ->orWhere('correlation_id', 'ilike', "%{$search}%");
        });
    }

    protected function applyDateRange($query, Request $request)
    {
        if ($dateFrom = $request->input('date_from')) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query;
    }
}
