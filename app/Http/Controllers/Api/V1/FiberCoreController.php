<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GenerateFiberCoresRequest;
use App\Http\Requests\Api\V1\StoreFiberCoreRequest;
use App\Http\Requests\Api\V1\UpdateFiberCoreRequest;
use App\Http\Resources\V1\FiberCoreResource;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Services\AuditService;
use App\Services\Fim\FiberCoreService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class FiberCoreController extends Controller
{
    public function __construct(protected FiberCoreService $cores) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', FiberCore::class);
        $query = ManagementScopeService::applyScopeToQuery(FiberCore::query(), $request->user(), FiberCore::class);

        foreach (['fiber_segment_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        return FiberCoreResource::collection($query->orderBy('fiber_segment_id')->orderBy('core_number')->paginate($request->input('per_page', 15)));
    }

    public function segmentCores(Request $request, FiberSegment $fiberSegment)
    {
        $this->authorize('viewAny', FiberCore::class);
        $this->authorize('view', $fiberSegment);
        $this->ensureSegmentInScope($request, $fiberSegment);

        return FiberCoreResource::collection(FiberCore::query()
            ->where('fiber_segment_id', $fiberSegment->id)
            ->orderBy('core_number')
            ->paginate($request->input('per_page', 15)));
    }

    public function store(StoreFiberCoreRequest $request)
    {
        $segment = FiberSegment::findOrFail($request->validated('fiber_segment_id'));
        $this->authorize('create', $segment);
        $core = $this->cores->create($request->validated(), $request->user());
        AuditService::log('fim.fiber-core.created', 'success', $core, $this->safeAuditMetadata($request->validated()));

        return (new FiberCoreResource($core))->response()->setStatusCode(201);
    }

    public function show(FiberCore $fiberCore)
    {
        $this->authorize('view', $fiberCore);

        return new FiberCoreResource($fiberCore);
    }

    public function update(UpdateFiberCoreRequest $request, FiberCore $fiberCore)
    {
        $this->authorize('update', $fiberCore);
        $before = $this->safeAuditMetadata($fiberCore->only(['core_number', 'status', 'color_code']));
        $core = $this->cores->update($fiberCore, $request->validated(), $request->user());
        AuditService::log('fim.fiber-core.updated', 'success', $core, [
            'before' => $before,
            'changes' => $this->safeAuditMetadata($request->validated()),
        ]);

        return new FiberCoreResource($core);
    }

    public function destroy(FiberCore $fiberCore)
    {
        $this->authorize('delete', $fiberCore);
        $metadata = $this->safeAuditMetadata($fiberCore->only(['fiber_segment_id', 'core_number', 'status', 'color_code']));
        $this->cores->delete($fiberCore);
        AuditService::log('fim.fiber-core.deleted', 'success', $fiberCore, $metadata);

        return response()->json(['message' => 'Fiber core deleted successfully.']);
    }

    public function generate(GenerateFiberCoresRequest $request, FiberSegment $fiberSegment)
    {
        $this->authorize('create', $fiberSegment);
        $this->ensureSegmentInScope($request, $fiberSegment);
        $result = $this->cores->generate($fiberSegment, $request->integer('start_core_number'), $request->integer('count'), $request->user());
        AuditService::log('fim.fiber-core.bulk-generated', 'success', $fiberSegment, [
            'start_core_number' => $request->integer('start_core_number'),
            'count' => $request->integer('count'),
            'created_count' => $result['created_count'],
            'skipped_existing_count' => $result['skipped_existing_count'],
        ]);

        return response()->json([
            'data' => FiberCoreResource::collection($result['cores'])->resolve($request),
            'meta' => [
                'created_count' => $result['created_count'],
                'skipped_existing_count' => $result['skipped_existing_count'],
            ],
        ], 201);
    }

    protected function ensureSegmentInScope(Request $request, FiberSegment $segment): void
    {
        abort_unless(ManagementScopeService::isInScope($request->user(), $segment), 403);
    }

    protected function safeAuditMetadata(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip([
            'fiber_segment_id', 'core_number', 'status', 'color_code',
        ]));
    }
}
