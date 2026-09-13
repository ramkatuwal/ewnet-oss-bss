<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAssetCategoryRequest;
use App\Http\Requests\Api\V1\StoreAssetDeviceTypeRequest;
use App\Http\Requests\Api\V1\StoreAssetUnitRequest;
use App\Http\Requests\Api\V1\UpdateAssetCategoryRequest;
use App\Http\Requests\Api\V1\UpdateAssetDeviceTypeRequest;
use App\Http\Requests\Api\V1\UpdateAssetUnitRequest;
use App\Models\AssetCategory;
use App\Models\AssetDeviceType;
use App\Models\AssetUnit;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetModelSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware(function (Request $request, Closure $next) {
            abort_unless($request->user()->isSuperAdmin() || $request->user()->can('system.config.manage'), 403);

            return $next($request);
        });
    }

    // ──────────── Categories ────────────

    public function indexCategories(): JsonResponse
    {
        $categories = AssetCategory::withCount('assets')
            ->ordered()
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function storeCategory(StoreAssetCategoryRequest $request): JsonResponse
    {
        $category = AssetCategory::create($request->validated());

        return response()->json(['data' => $category], 201);
    }

    public function showCategory(AssetCategory $category): JsonResponse
    {
        $category->loadCount('assets');

        return response()->json(['data' => $category]);
    }

    public function updateCategory(UpdateAssetCategoryRequest $request, AssetCategory $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json(['data' => $category]);
    }

    public function destroyCategory(AssetCategory $category): JsonResponse
    {
        if ($category->assets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category that is referenced by assets. Deactivate it instead.',
            ], 409);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    // ──────────── Device Types ────────────

    public function indexDeviceTypes(Request $request): JsonResponse
    {
        $query = AssetDeviceType::with('category')->ordered();

        if ($request->has('category_id')) {
            $query->forCategory($request->integer('category_id'));
        }

        $types = $query->get();

        return response()->json(['data' => $types]);
    }

    public function storeDeviceType(StoreAssetDeviceTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = strtoupper($data['code']);

        $existing = AssetDeviceType::where('category_id', $data['category_id'])
            ->where('code', $data['code'])
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => "Device type '{$data['code']}' already exists in this category.",
            ], 409);
        }

        $type = AssetDeviceType::create($data);

        return response()->json(['data' => $type], 201);
    }

    public function showDeviceType(AssetDeviceType $deviceType): JsonResponse
    {
        $deviceType->load('category');
        $deviceType->loadCount('assets');

        return response()->json(['data' => $deviceType]);
    }

    public function updateDeviceType(UpdateAssetDeviceTypeRequest $request, AssetDeviceType $deviceType): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $deviceType->update($data);

        return response()->json(['data' => $deviceType]);
    }

    public function destroyDeviceType(AssetDeviceType $deviceType): JsonResponse
    {
        if ($deviceType->assets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete device type that is referenced by assets. Deactivate it instead.',
            ], 409);
        }

        $deviceType->delete();

        return response()->json(['message' => 'Device type deleted']);
    }

    // ──────────── Units ────────────

    public function indexUnits(): JsonResponse
    {
        $units = AssetUnit::withCount('assets')
            ->ordered()
            ->get();

        return response()->json(['data' => $units]);
    }

    public function storeUnit(StoreAssetUnitRequest $request): JsonResponse
    {
        $unit = AssetUnit::create($request->validated());

        return response()->json(['data' => $unit], 201);
    }

    public function showUnit(AssetUnit $unit): JsonResponse
    {
        $unit->loadCount('assets');

        return response()->json(['data' => $unit]);
    }

    public function updateUnit(UpdateAssetUnitRequest $request, AssetUnit $unit): JsonResponse
    {
        $unit->update($request->validated());

        return response()->json(['data' => $unit]);
    }

    public function destroyUnit(AssetUnit $unit): JsonResponse
    {
        if ($unit->assets()->exists()) {
            return response()->json([
                'message' => 'Cannot delete unit that is referenced by assets. Deactivate it instead.',
            ], 409);
        }

        $unit->delete();

        return response()->json(['message' => 'Unit deleted']);
    }
}
