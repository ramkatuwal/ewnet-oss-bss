<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GenerateSplitterPortsRequest;
use App\Http\Requests\Api\V1\StoreSplitterProfileRequest;
use App\Http\Requests\Api\V1\UpdateSplitterProfileRequest;
use App\Http\Resources\V1\PassiveOpticalPortResource;
use App\Http\Resources\V1\SplitterProfileResource;
use App\Models\Asset;
use App\Models\SplitterProfile;
use App\Services\AuditService;
use App\Services\Fim\SplitterProfileService;
use Illuminate\Http\Request;

class SplitterProfileController extends Controller
{
    public function __construct(protected SplitterProfileService $profiles) {}

    public function show(Asset $asset)
    {
        $this->authorize('viewForAsset', [SplitterProfile::class, $asset]);

        return new SplitterProfileResource($asset->splitterProfile()->firstOrFail());
    }

    public function store(StoreSplitterProfileRequest $request, Asset $asset)
    {
        $profile = $this->profiles->create($asset, $request->validated(), $request->user());
        AuditService::log('fim.splitter-profile.created', 'success', $profile, $this->auditMetadata($profile));

        return (new SplitterProfileResource($profile))->response()->setStatusCode(201);
    }

    public function update(UpdateSplitterProfileRequest $request, SplitterProfile $splitterProfile)
    {
        $profile = $this->profiles->update($splitterProfile, $request->validated(), $request->user());
        AuditService::log('fim.splitter-profile.updated', 'success', $profile, [
            ...$this->auditMetadata($profile),
            'input_port_count_changed' => $profile->wasChanged('input_port_count'),
            'output_port_count_changed' => $profile->wasChanged('output_port_count'),
            'split_ratio_changed' => $profile->wasChanged('split_ratio'),
            'metadata_changed' => $profile->wasChanged('metadata'),
        ]);

        return new SplitterProfileResource($profile);
    }

    public function destroy(Request $request, SplitterProfile $splitterProfile)
    {
        $this->authorize('delete', $splitterProfile);
        $profile = $this->profiles->delete($splitterProfile, $request->user());
        AuditService::log('fim.splitter-profile.deleted', 'success', $profile, $this->auditMetadata($profile));

        return response()->json(['message' => 'Splitter profile deleted successfully.']);
    }

    public function generate(GenerateSplitterPortsRequest $request, SplitterProfile $splitterProfile)
    {
        $result = $this->profiles->generate($splitterProfile, $request->validated(), $request->user());
        $meta = [
            'created_count' => $result['created_count'],
            'matched_existing_count' => $result['matched_existing_count'],
        ];
        AuditService::log('fim.splitter-profile.ports-generated', 'success', $splitterProfile, [
            ...$splitterProfile->only(['id', 'asset_id', 'company_id']),
            ...$meta,
        ]);

        return response()->json([
            'data' => PassiveOpticalPortResource::collection($result['ports'])->resolve($request),
            'meta' => $meta,
        ], 201);
    }

    private function auditMetadata(SplitterProfile $profile): array
    {
        return $profile->only(['id', 'asset_id', 'company_id', 'input_port_count', 'output_port_count']);
    }
}
