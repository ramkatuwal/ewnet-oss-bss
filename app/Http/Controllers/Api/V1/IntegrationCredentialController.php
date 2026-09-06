<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IntegrationCredentialRequest;
use App\Http\Resources\V1\IntegrationCredentialResource;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegrationCredentialController extends Controller
{
    public function index(Integration $integration)
    {
        $this->authorize('manageCredentials', $integration);

        $credentials = $integration->credentials()->orderByDesc('is_active')->get();

        return IntegrationCredentialResource::collection($credentials);
    }

    public function store(IntegrationCredentialRequest $request, Integration $integration)
    {
        $this->authorize('manageCredentials', $integration);

        $credential = new IntegrationCredential([
            'integration_id' => $integration->id,
            'credential_type' => $request->validated('credential_type'),
            'label' => $request->validated('label'),
            'metadata' => $request->validated('metadata'),
            'is_active' => true,
        ]);

        $secretValue = $request->validated('value');
        if ($secretValue) {
            $credential->setSecretValue($secretValue);
        } else {
            $credential->encrypted_value = encrypt('');
            $credential->masked_hint = '********';
        }

        $credential->save();

        AuditService::log('integration.credential_changed', 'success', $integration, [
            'credential_type' => $credential->credential_type,
            'label' => $credential->label,
        ]);

        return new IntegrationCredentialResource($credential);
    }

    public function destroy(Integration $integration, IntegrationCredential $credential)
    {
        $this->authorize('manageCredentials', $integration);

        if ($credential->integration_id !== $integration->id) {
            abort(404);
        }

        $credential->delete();

        AuditService::log('integration.credential_changed', 'success', $integration, [
            'action' => 'deleted',
            'credential_type' => $credential->credential_type,
        ]);

        return response()->json(null, 204);
    }

    public function rotate(Request $request, Integration $integration, IntegrationCredential $credential)
    {
        $this->authorize('manageCredentials', $integration);

        if ($credential->integration_id !== $integration->id) {
            abort(404);
        }

        $request->validate(['value' => 'required|string']);

        DB::transaction(function () use ($request, $integration, $credential) {
            $new = new IntegrationCredential([
                'integration_id' => $integration->id,
                'credential_type' => $credential->credential_type,
                'label' => $credential->label,
                'is_active' => true,
            ]);
            $new->setSecretValue($request->input('value'));
            $new->save();

            $credential->update(['is_active' => false]);

            AuditService::log('integration.credential_rotated', 'success', $integration, [
                'credential_type' => $new->credential_type,
                'label' => $new->label,
                'previous_credential_id' => $credential->id,
            ]);
        });

        return response()->json(['success' => true]);
    }
}
