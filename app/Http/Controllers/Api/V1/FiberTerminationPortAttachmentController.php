<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFiberTerminationPortAttachmentRequest;
use App\Http\Resources\V1\FiberTerminationPortAttachmentResource;
use App\Models\FiberTermination;
use App\Models\FiberTerminationPortAttachment;
use App\Models\PassiveOpticalPort;
use App\Services\AuditService;
use App\Services\Fim\FiberTerminationPortAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class FiberTerminationPortAttachmentController extends Controller
{
    public function __construct(protected FiberTerminationPortAttachmentService $attachments) {}

    public function index(Request $request, FiberTermination $fiberTermination)
    {
        $this->authorize('view', $fiberTermination);
        $this->authorize('viewAny', FiberTerminationPortAttachment::class);

        // The attachment policy also requires visibility through the attached port.
        $attachments = $fiberTermination->portAttachments()
            ->with([
                'fiberTermination.fiberCore.fiberSegment.fiberCable',
                'fiberTermination.networkConnectionPoint.asset.site',
                'passiveOpticalPort.asset.site',
                'passiveOpticalPort.networkConnectionPoint.asset.site',
            ])
            ->latest()
            ->get()
            ->filter(fn (FiberTerminationPortAttachment $attachment) => $request->user()->can('view', $attachment))
            ->values();
        $perPage = $request->integer('per_page', 15);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return FiberTerminationPortAttachmentResource::collection(new LengthAwarePaginator(
            $attachments->forPage($page, $perPage),
            $attachments->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        ));
    }

    public function store(StoreFiberTerminationPortAttachmentRequest $request, FiberTermination $fiberTermination)
    {
        $port = PassiveOpticalPort::findOrFail($request->integer('passive_optical_port_id'));
        $this->authorize('create', [FiberTerminationPortAttachment::class, $fiberTermination, $port]);
        $attachment = $this->attachments->attach($fiberTermination, $port->id, $request->user(), $request->validated('metadata'));
        AuditService::log('fim.termination-port-attachment.created', 'success', $attachment, $this->auditMetadata($attachment));

        return (new FiberTerminationPortAttachmentResource($attachment))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, FiberTerminationPortAttachment $fiberTerminationPortAttachment)
    {
        $this->authorize('delete', $fiberTerminationPortAttachment);
        $metadata = $this->auditMetadata($fiberTerminationPortAttachment);
        $this->attachments->detach($fiberTerminationPortAttachment);
        AuditService::log('fim.termination-port-attachment.deleted', 'success', $fiberTerminationPortAttachment, $metadata);

        return response()->json(['message' => 'Fiber termination port attachment detached successfully.']);
    }

    protected function auditMetadata(FiberTerminationPortAttachment $attachment): array
    {
        return $attachment->only(['id', 'fiber_termination_id', 'passive_optical_port_id', 'company_id']);
    }
}
