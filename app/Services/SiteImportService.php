<?php

namespace App\Services;

use App\Models\Site;
use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SiteImportService
{
    /**
     * Validate and process a single row from the import file.
     * Returns ['success' => bool, 'message' => string, 'site_id' => ?int]
     */
    public function processRow(array $row, User $actor): array
    {
        try {
            // 1. Validate Row Data (Dedicated Import Validation)
            $validator = Validator::make($row, [
                'site_code' => 'required|string|max:255',
                'name' => 'required|string|max:255',
                'type' => 'required|in:' . implode(',', Site::TYPES),
                'status' => 'required|in:' . implode(',', Site::STATUSES),
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'altitude' => 'nullable|numeric',
                'company_name' => 'nullable|string',
                'region_name' => 'nullable|string',
                'branch_name' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Validation failed: ' . implode(', ', $validator->errors()->all()),
                    'site_id' => null,
                ];
            }

            $data = $validator->validated();

            // 2. Resolve Organization IDs with Scope Enforcement
            $companyId = null;
            $regionId = null;
            $branchId = null;

            if (!empty($data['company_name'])) {
                $company = Company::where('name', $data['company_name'])->first();
                if (!$company || !ManagementScopeService::isInScope($actor, $company)) {
                    return [
                        'success' => false,
                        'message' => "Company '{$data['company_name']}' not found or outside your management scope.",
                        'site_id' => null,
                    ];
                }
                $companyId = $company->id;
            }

            if (!empty($data['region_name']) && $companyId) {
                $region = Region::where('name', $data['region_name'])
                    ->where('company_id', $companyId)
                    ->first();
                
                if (!$region || !ManagementScopeService::isInScope($actor, $region)) {
                    return [
                        'success' => false,
                        'message' => "Region '{$data['region_name']}' not found in company or outside scope.",
                        'site_id' => null,
                    ];
                }
                $regionId = $region->id;
            } elseif (!empty($data['region_name'])) {
                 return [
                    'success' => false,
                    'message' => "Region specified without Company.",
                    'site_id' => null,
                ];
            }

            if (!empty($data['branch_name']) && $regionId) {
                $branch = Branch::where('name', $data['branch_name'])
                    ->where('region_id', $regionId)
                    ->first();

                if (!$branch || !ManagementScopeService::isInScope($actor, $branch)) {
                    return [
                        'success' => false,
                        'message' => "Branch '{$data['branch_name']}' not found in region or outside scope.",
                        'site_id' => null,
                    ];
                }
                $branchId = $branch->id;
            } elseif (!empty($data['branch_name'])) {
                return [
                    'success' => false,
                    'message' => "Branch specified without Region.",
                    'site_id' => null,
                ];
            }

            // 3. Upsert Logic with Scope Check on Existing Records
            $site = Site::where('site_code', $data['site_code'])->first();

            if ($site) {
                // Check if actor can update this existing site
                if (!ManagementScopeService::isInScope($actor, $site)) {
                    return [
                        'success' => false,
                        'message' => "Site '{$data['site_code']}' exists but is outside your management scope. Update denied.",
                        'site_id' => $site->id,
                    ];
                }
                
                $site->update([
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'status' => $data['status'],
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'altitude' => $data['altitude'] ?? null,
                    'company_id' => $companyId,
                    'region_id' => $regionId,
                    'branch_id' => $branchId,
                ]);
                
                AuditService::log('site.import.updated', 'success', $site, ['source' => 'csv_import']);
                
                return ['success' => true, 'message' => 'Updated', 'site_id' => $site->id];
            } else {
                // Create new site
                $newSite = Site::create([
                    'site_code' => $data['site_code'],
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'status' => $data['status'],
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'altitude' => $data['altitude'] ?? null,
                    'company_id' => $companyId,
                    'region_id' => $regionId,
                    'branch_id' => $branchId,
                ]);

                AuditService::log('site.import.created', 'success', $newSite, ['source' => 'csv_import']);

                return ['success' => true, 'message' => 'Created', 'site_id' => $newSite->id];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'System Error: ' . $e->getMessage(),
                'site_id' => null,
            ];
        }
    }
}
