<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SystemConfigRequest;
use App\Http\Requests\Api\V1\UploadBrandingRequest;
use App\Http\Resources\V1\SystemConfigResource;
use App\Models\SystemSetting;
use App\Services\AuditService;
use App\Services\SystemConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SystemConfigController extends Controller
{
    public function index(Request $request)
    {
        // Check authorization
        if (! $request->user() || ! $request->user()->can('system.info.view')) {
            abort(403, 'Unauthorized to view configuration');
        }

        $this->authorize('viewConfiguration', SystemSetting::class);

        $config = SystemConfigService::getAll();

        return new SystemConfigResource($config);
    }

    public function update(SystemConfigRequest $request)
    {
        $this->authorize('manageConfiguration', SystemSetting::class);

        $validated = $request->validated();

        // Remove any empty groups
        $validated = array_filter($validated, function ($value) {
            return ! empty($value);
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

    public function uploadBranding(UploadBrandingRequest $request)
    {
        $this->authorize('manageConfiguration', SystemSetting::class);

        $type = $request->input('type');
        $key = $type === 'favicon' ? 'favicon_path' : 'logo_path';
        $directory = $type === 'favicon' ? 'favicons' : 'logos';

        // Remove the previous stored file (only within the branding directory)
        $previous = SystemSetting::where('key', $key)->value('value');
        if ($previous && is_string($previous)) {
            $previous = ltrim((string) str_replace(['storage/app/public/', 'public/', 'storage/'], '', $previous), '/');
            if (str_starts_with($previous, $directory.'/') && Storage::disk('public')->exists($previous)) {
                Storage::disk('public')->delete($previous);
            }
        }

        $file = $request->file('file');
        $storedPath = $file->store($directory, 'public');

        SystemConfigService::update(['branding' => [$key => $storedPath]], $request->user()->id);

        AuditService::log('system.branding.upload', 'success', null, [
            'type' => $type,
            'path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => ucfirst($type).' uploaded successfully.',
            'type' => $type,
            'path' => $storedPath,
            'url' => Storage::disk('public')->url($storedPath),
        ]);
    }
}
