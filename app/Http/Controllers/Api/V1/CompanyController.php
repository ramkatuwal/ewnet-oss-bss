<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompanyRequest;
use App\Http\Resources\V1\CompanyResource;
use App\Models\Company;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Company::class);

        $query = Company::query();

        // Apply centralized scope filtering
        $query = ManagementScopeService::applyScopeToQuery($query, $request->user(), \App\Models\Company::class);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('registration_number', 'ilike', "%{$search}%")
                  ->orWhere('pan_number', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $companies = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return CompanyResource::collection($companies);
    }

    public function store(CompanyRequest $request)
    {
        $this->authorize('create', Company::class);

        $data = $request->validated();
        unset($data['logo'], $data['remove_logo']);

        $company = Company::create($data);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store("companies/{$company->id}", 'public');
            $company->update(['logo_path' => $path]);
        }

        AuditService::log('company.create', 'success', $company);

        return new CompanyResource($company->fresh());
    }

    public function show(Request $request, Company $company)
    {
        $this->authorize('view', $company);

        return new CompanyResource($company);
    }

    public function update(CompanyRequest $request, Company $company)
    {
        $this->authorize('update', $company);

        $data = $request->validated();
        unset($data['logo'], $data['remove_logo']);

        // Handle logo removal
        if ($request->boolean('remove_logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = null;
        }

        $company->update($data);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $path = $request->file('logo')->store("companies/{$company->id}", 'public');
            $company->update(['logo_path' => $path]);
        }

        AuditService::log('company.update', 'success', $company);

        return new CompanyResource($company->fresh());
    }

    public function destroy(Request $request, Company $company)
    {
        $this->authorize('delete', $company);

        // Prevent deletion if company has child records
        if ($company->regions()->exists()) {
            abort(422, 'Cannot delete company with existing regions. Delete or reassign regions first.');
        }

        // Clean up logo
        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $company->delete();

        AuditService::log('company.delete', 'success', $company);

        return response()->json(['message' => 'Company deleted successfully']);
    }
}
