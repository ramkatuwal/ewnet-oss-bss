<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePhotoRequest;
use App\Http\Resources\V1\PhotoResource;
use App\Models\Asset;
use App\Models\AssetPhoto;
use App\Models\Site;
use App\Models\SitePhoto;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoController extends Controller
{
    protected AuditService $audit;

    public function __construct(AuditService $audit)
    {
        $this->audit = $audit;
    }

    // Site Photos
    public function sitePhotos(Site $site, Request $request)
    {
        $this->authorize('view', $site);

        $photos = $site->photos()
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();

        return PhotoResource::collection($photos);
    }

    public function storeSitePhoto(StorePhotoRequest $request, Site $site)
    {
        $this->authorize('update', $site);

        $path = $request->file('photo')->store('site-photos', 'public');

        $photo = SitePhoto::create([
            'site_id' => $site->id,
            'path' => $path,
            'title' => $request->title,
            'category' => $request->category ?? 'site',
            'description' => $request->description,
            'taken_at' => $request->taken_at,
            'uploaded_by' => $request->user()->id,
        ]);

        $this->audit->log('site.photo.uploaded', 'success', $site, [
            'photo_id' => $photo->id,
            'path' => $path,
        ]);

        return new PhotoResource($photo->load('uploader'));
    }

    public function deleteSitePhoto(Site $site, SitePhoto $photo, Request $request)
    {
        $this->authorize('update', $site);

        if ($photo->site_id !== $site->id) {
            abort(404);
        }

        Storage::disk('public')->delete($photo->path);

        $this->audit->log('site.photo.deleted', 'success', $site, [
            'photo_id' => $photo->id,
            'path' => $photo->path,
        ]);

        $photo->delete();

        return response()->json(['message' => 'Photo deleted successfully.']);
    }

    // Asset Photos
    public function assetPhotos(Asset $asset, Request $request)
    {
        $this->authorize('view', $asset);

        $photos = $asset->photos()
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();

        return PhotoResource::collection($photos);
    }

    public function storeAssetPhoto(StorePhotoRequest $request, Asset $asset)
    {
        $this->authorize('update', $asset);

        $path = $request->file('photo')->store('asset-photos', 'public');

        $photo = AssetPhoto::create([
            'asset_id' => $asset->id,
            'path' => $path,
            'title' => $request->title,
            'category' => $request->category ?? 'asset',
            'description' => $request->description,
            'taken_at' => $request->taken_at,
            'uploaded_by' => $request->user()->id,
        ]);

        $this->audit->log('asset.photo.uploaded', 'success', $asset, [
            'photo_id' => $photo->id,
            'path' => $path,
        ]);

        return new PhotoResource($photo->load('uploader'));
    }

    public function deleteAssetPhoto(Asset $asset, AssetPhoto $photo, Request $request)
    {
        $this->authorize('update', $asset);

        if ($photo->asset_id !== $asset->id) {
            abort(404);
        }

        Storage::disk('public')->delete($photo->path);

        $this->audit->log('asset.photo.deleted', 'success', $asset, [
            'photo_id' => $photo->id,
            'path' => $photo->path,
        ]);

        $photo->delete();

        return response()->json(['message' => 'Photo deleted successfully.']);
    }
}
