<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAssetLifecycleEventRequest;
use App\Http\Requests\Api\V1\TransferAssetRequest;
use App\Http\Resources\V1\AssetLifecycleEventResource;
use App\Models\Asset;
use App\Models\Site;
use App\Services\AssetLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AssetLifecycleController extends Controller
{
    protected AssetLifecycleService $lifecycleService;

    public function __construct(AssetLifecycleService $lifecycleService)
    {
        $this->lifecycleService = $lifecycleService;
    }

    public function index(Asset $asset, Request $request)
    {
        $this->authorize("viewLifecycle", $asset);

        $events = $this->lifecycleService->getHistory($asset);

        return AssetLifecycleEventResource::collection($events);
    }

    public function store(StoreAssetLifecycleEventRequest $request, Asset $asset)
    {
        $this->authorize("createLifecycle", $asset);

        $event = $this->lifecycleService->createEvent($asset, $request->event_type, [
            "status_before" => $request->status_before,
            "status_after" => $request->status_after,
            "notes" => $request->notes,
            "metadata" => $request->metadata,
            "created_by" => $request->user()->id,
            "event_date" => $request->event_date,
        ]);

        return new AssetLifecycleEventResource($event);
    }

    public function transfer(TransferAssetRequest $request, Asset $asset)
    {
        $this->authorize("transfer", $asset);

        $toSite = Site::findOrFail($request->to_site_id);
        $this->authorize("view", $toSite);

        $event = $this->lifecycleService->transfer($asset, $toSite, $request->user(), $request->notes);

        return response()->json([
            "message" => "Asset transferred successfully.",
            "data" => new AssetLifecycleEventResource($event),
        ]);
    }

    public function retire(Request $request, Asset $asset)
    {
        $this->authorize("retire", $asset);

        $request->validate([
            "notes" => ["nullable", "string", "max:1000"],
        ]);

        try {
            $event = $this->lifecycleService->retire($asset, $request->user(), $request->notes);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                "status" => [$e->getMessage()],
            ]);
        }

        return response()->json([
            "message" => "Asset retired successfully.",
            "data" => new AssetLifecycleEventResource($event),
        ]);
    }

    public function dispose(Request $request, Asset $asset)
    {
        $this->authorize("dispose", $asset);

        $request->validate([
            "notes" => ["nullable", "string", "max:1000"],
        ]);

        try {
            $event = $this->lifecycleService->dispose($asset, $request->user(), $request->notes);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                "status" => [$e->getMessage()],
            ]);
        }

        return response()->json([
            "message" => "Asset disposed successfully.",
            "data" => new AssetLifecycleEventResource($event),
        ]);
    }
}
