<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\Department;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Check if user has permission to view dashboard
        if (!$user->can('dashboard.view')) {
            abort(403, 'Unauthorized to view dashboard');
        }

        $isSuperAdmin = $user->hasRole('Super Admin');
        $scopeQuery = $isSuperAdmin ? null : function($query) use ($user) {
            if ($user->company_id) {
                $query->where('company_id', $user->company_id);
            }
            if ($user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }
            if ($user->department_id) {
                $query->where('department_id', $user->department_id);
            }
        };

        // Organization metrics
        $organization = [
            'companies' => $isSuperAdmin ? Company::count() : 0,
            'regions' => $isSuperAdmin ? Region::count() : Region::when($user->company_id, fn($q) => $q->where('company_id', $user->company_id))->count(),
            'branches' => Branch::when($scopeQuery, fn($q) => $scopeQuery($q))->count(),
            'departments' => Department::when($scopeQuery, fn($q) => $scopeQuery($q))->count(),
            'users' => User::when($scopeQuery, fn($q) => $scopeQuery($q))->count(),
        ];

        // Security metrics
        $security = [
            'roles' => Role::count(),
            'permissions' => Permission::count(),
        ];

        // Recent activity (last 10 audit logs)
        $activityQuery = AuditLog::with(['actor', 'target'])
            ->orderBy('created_at', 'desc')
            ->take(10);

        if (!$isSuperAdmin && $scopeQuery) {
            // For non-super-admin, filter by scope
            $activityQuery->where(function($q) use ($user) {
                $q->where('actor_id', $user->id)
                  ->orWhere('target_id', $user->id);
            });
        }

        $activity = $activityQuery->get()->map(function($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'result' => $log->result,
                'actor' => $log->actor ? $log->actor->name : 'System',
                'target_type' => $log->target_type ? class_basename($log->target_type) : null,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        });

        // Account info
        $account = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->toArray(),
            ],
            'membership' => [
                'company' => $user->company ? $user->company->name : null,
                'branch' => $user->branch ? $user->branch->name : null,
                'department' => $user->department ? $user->department->name : null,
            ],
            'is_super_admin' => $isSuperAdmin,
        ];

        return response()->json([
            'organization' => $organization,
            'security' => $security,
            'activity' => $activity,
            'account' => $account,
        ]);
    }
}
