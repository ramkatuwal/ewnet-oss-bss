<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SystemConfigRequest;
use App\Http\Resources\V1\SystemConfigResource;
use App\Services\AuditService;
use App\Services\SystemConfigService;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    public function index(Request $request)
    {
        // Check authorization
        if (!$request->user() || !$request->user()->can('system.info.view')) {
            abort(403, 'Unauthorized to view configuration');
        }

        $this->authorize('viewConfiguration', \App\Models\SystemSetting::class);

        $config = SystemConfigService::getAll();

        return new SystemConfigResource($config);
    }

    public function update(SystemConfigRequest $request)
    {
        $this->authorize('manageConfiguration', \App\Models\SystemSetting::class);

        $validated = $request->validated();

        // Remove any empty groups
        $validated = array_filter($validated, function ($value) {
            return !empty($value);
        });

        if (empty($validated)) {
            return response()->json([
                'message' => 'No valid configuration values provided.',
            ], 422);
        }

        $result = SystemConfigService::update($validated, $request->user()->id);

        // Audit
        AuditService::log('system.configuration.update', 'success', null, [
            'updated_keys' => $result['changed_keys'],
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Configuration updated successfully.',
            'updated' => $result['updated'],
        ]);
    }
}
