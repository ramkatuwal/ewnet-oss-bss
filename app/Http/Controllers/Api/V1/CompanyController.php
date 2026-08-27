<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompanyRequest;
use App\Http\Resources\V1\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Company::class);

        $query = Company::query();

        // Scope to user's company if not Super Admin
        if (!$request->user()->hasRole('Super Admin')) {
            $query->where('id', $request->user()->company_id);
        }

        $companies = $query->paginate($request->get('per_page', 15));

        return CompanyResource::collection($companies);
    }

    public function store(CompanyRequest $request)
    {
        $this->authorize('create', Company::class);

        $company = Company::create($request->validated());

        return new CompanyResource($company);
    }

    public function show(Request $request, Company $company)
    {
        $this->authorize('view', $company);

        return new CompanyResource($company);
    }

    public function update(CompanyRequest $request, Company $company)
    {
        $this->authorize('update', $company);

        $company->update($request->validated());

        return new CompanyResource($company);
    }

    public function destroy(Request $request, Company $company)
    {
        $this->authorize('delete', $company);

        // Soft delete with cascade warning
        $company->update(['is_active' => false]);
        $company->delete();

        return response()->noContent();
    }
}
