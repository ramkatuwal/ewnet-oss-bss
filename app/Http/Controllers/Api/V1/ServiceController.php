<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreServiceRequest;
use App\Http\Requests\Api\V1\UpdateServiceRequest;
use App\Http\Resources\V1\ServiceResource;
use App\Models\CustomerService;
use App\Models\Service;
use App\Services\AuditService;
use App\Services\BssService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(private readonly BssService $bss) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Service::class);
        $request->validate(['search' => ['nullable', 'string', 'max:255'], 'type' => ['nullable', 'in:internet,voice,iptv,other'], 'status' => ['nullable', 'in:active,inactive,retired'], 'company_id' => ['nullable', 'integer'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $query = ManagementScopeService::applyScopeToQuery(Service::query(), $request->user(), Service::class);
        foreach (['type', 'status', 'company_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        } if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('service_code', 'ilike', '%'.$request->string('search').'%')->orWhere('name', 'ilike', '%'.$request->string('search').'%'));
        }

return ServiceResource::collection($query->orderBy('service_code')->paginate($request->integer('per_page', 15)));
    }

    public function store(StoreServiceRequest $request)
    {
        $this->authorize('create', Service::class);
        $service = $this->bss->createService($request->validated(), $request->user());
        AuditService::log('bss.service.created', 'success', $service, $this->metadata($service));

        return (new ServiceResource($service))->response()->setStatusCode(201);
    }

    public function show(Service $service)
    {
        $this->authorize('view', $service);

        return new ServiceResource($service);
    }

    public function update(UpdateServiceRequest $request, Service $service)
    {
        $this->authorize('update', $service);
        $service = $this->bss->updateService($service, $request->validated(), $request->user());
        AuditService::log('bss.service.updated', 'success', $service, $this->metadata($service));

        return new ServiceResource($service);
    }

    public function destroy(Request $request, Service $service)
    {
        $this->authorize('delete', $service);
        if (CustomerService::where('service_id', $service->id)->whereIn('status', CustomerService::LIVE_STATUSES)->exists()) {
            abort(409, 'A service with live customer services cannot be retired.');
        } $service->update(['status' => 'retired', 'updated_by' => $request->user()->id]);
        $service->delete();
        AuditService::log('bss.service.retired', 'success', $service, $this->metadata($service));

        return response()->json(['message' => 'Service retired successfully.']);
    }

    private function metadata(Service $service): array
    {
        return ['service_id' => $service->id, 'company_id' => $service->company_id, 'service_code' => $service->service_code, 'status' => $service->status];
    }
}
