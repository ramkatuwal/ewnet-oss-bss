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

        // SAFE Organization metrics (Respects actual DB relationships)
        $organization = [
            'companies' => 0,
            'regions' => 0,
            'branches' => 0,
            'departments' => 0,
            'users' => 0,
        ];

        if ($isSuperAdmin) {
            $organization['companies'] = Company::count();
            $organization['regions'] = Region::count();
            $organization['branches'] = Branch::count();
            $organization['departments'] = Department::count();
            $organization['users'] = User::count();
        } else {
            $companyIds = [];
            $regionIds = [];
            $branchIds = [];
            $departmentIds = [];

            // Traverse relationships safely
            if ($user->company_id) {
                $companyIds[] = $user->company_id;
                $regionIds = Region::where('company_id', $user->company_id)->pluck('id')->toArray();
                $branchIds = Branch::whereIn('region_id', $regionIds)->pluck('id')->toArray();
                $departmentIds = Department::whereIn('branch_id', $branchIds)->pluck('id')->toArray();
            }
            
            if ($user->branch_id && !in_array($user->branch_id, $branchIds)) {
                $branchIds[] = $user->branch_id;
                $departmentIds = array_merge($departmentIds, Department::where('branch_id', $user->branch_id)->pluck('id')->toArray());
                $branch = Branch::find($user->branch_id);
                if ($branch && $branch->region_id && !in_array($branch->region_id, $regionIds)) {
                    $regionIds[] = $branch->region_id;
                }
            }
            
            if ($user->department_id && !in_array($user->department_id, $departmentIds)) {
                $departmentIds[] = $user->department_id;
            }

            $organization['companies'] = Company::whereIn('id', $companyIds)->count();
            $organization['regions'] = Region::whereIn('id', $regionIds)->count();
            $organization['branches'] = Branch::whereIn('id', $branchIds)->count();
            $organization['departments'] = Department::whereIn('id', $departmentIds)->count();
            
            $organization['users'] = User::where(function($q) use ($companyIds, $branchIds, $departmentIds, $user) {
                $q->whereIn('company_id', $companyIds)
                  ->orWhereIn('branch_id', $branchIds)
                  ->orWhereIn('department_id', $departmentIds)
                  ->orWhere('id', $user->id); // Always include self
            })->count();
        }

        // Security metrics
        $security = [
            'roles' => Role::count(),
            'permissions' => Permission::count(),
        ];

        // Recent activity (last 10 audit logs)
        $activityQuery = AuditLog::with(['actor', 'target'])
            ->orderBy('created_at', 'desc')
            ->take(10);

        if (!$isSuperAdmin) {
            // For non-super-admin, filter by scope (actor or target is the user, or within their org)
            $activityQuery->where(function($q) use ($user, $companyIds, $branchIds, $departmentIds) {
                $q->where('actor_id', $user->id)
                  ->orWhere('target_id', $user->id)
                  ->orWhereIn('company_id', $companyIds)
                  ->orWhereIn('branch_id', $branchIds)
                  ->orWhereIn('department_id', $departmentIds);
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
