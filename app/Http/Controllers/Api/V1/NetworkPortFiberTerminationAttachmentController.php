<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNetworkPortFiberTerminationAttachmentRequest;
use App\Http\Resources\V1\NetworkPortFiberTerminationAttachmentResource;
use App\Models\FiberTermination;
use App\Models\NetworkPort;
use App\Models\NetworkPortFiberTerminationAttachment;
use App\Services\AuditService;
use App\Services\Network\NetworkPortFiberTerminationAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NetworkPortFiberTerminationAttachmentController extends Controller
{
    public function __construct(protected NetworkPortFiberTerminationAttachmentService $attachments) {}

    public function index(Request $request, NetworkPort $networkPort)
    {
        $this->authorize('view', $networkPort);
        $this->authorize('viewAny', NetworkPortFiberTerminationAttachment::class);
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        // At most one live attachment per port; filter before totals and serialization.
        $visible = NetworkPortFiberTerminationAttachment::where('network_port_id', $networkPort->id)
            ->with(['networkPort.asset.site', 'fiberTermination.fiberCore.fiberSegment.fiberCable', 'fiberTermination.networkConnectionPoint.asset.site'])
            ->get()->filter(fn ($attachment) => $request->user()->can('view', $attachment))->values();
        $perPage = $request->integer('per_page', 15);
        $page = $request->integer('page', 1);

        return NetworkPortFiberTerminationAttachmentResource::collection(new LengthAwarePaginator(
            $visible->forPage($page, $perPage)->values(), $visible->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }

    public function store(StoreNetworkPortFiberTerminationAttachmentRequest $request, NetworkPort $networkPort)
    {
        $termination = FiberTermination::findOrFail($request->integer('fiber_termination_id'));
        $this->authorize('create', [NetworkPortFiberTerminationAttachment::class, $networkPort, $termination]);
        $attachment = $this->attachments->attach($networkPort, $termination->id, $request->user(), $request->validated('metadata'));
        AuditService::log('net.network-port-fiber-attachment.created', 'success', $attachment, $attachment->only(['id', 'network_port_id', 'fiber_termination_id', 'company_id']));

        return (new NetworkPortFiberTerminationAttachmentResource($attachment))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, NetworkPortFiberTerminationAttachment $attachment)
    {
        $this->authorize('delete', $attachment);
        $this->attachments->disconnect($attachment, $request->user());
        AuditService::log('net.network-port-fiber-attachment.disconnected', 'success', $attachment, $attachment->only(['id', 'network_port_id', 'fiber_termination_id', 'company_id']));

        return response()->json(['message' => 'Network port fiber attachment disconnected successfully.']);
    }
}
