<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TopologyTraversalRequest;
use App\Http\Resources\V1\TopologicalPathResource;
use App\Models\FiberTermination;
use App\Models\PassiveOpticalPort;
use App\Services\Fim\TopologyTraversalService;

class TopologyTraversalController extends Controller
{
    public function __construct(protected TopologyTraversalService $traversal) {}

    public function fromTermination(FiberTermination $fiberTermination, TopologyTraversalRequest $request)
    {
        $this->authorize('view', $fiberTermination);

        $path = $this->traversal->traverseFromTermination(
            $fiberTermination,
            $request->validated('direction', 'both'),
            $request->validated('max_depth', 20),
            $request->validated('include_containment', true),
        );

        return new TopologicalPathResource($path);
    }

    public function fromPort(PassiveOpticalPort $passiveOpticalPort, TopologyTraversalRequest $request)
    {
        $this->authorize('view', $passiveOpticalPort);

        $path = $this->traversal->traverseFromPort(
            $passiveOpticalPort,
            $request->validated('direction', 'both'),
            $request->validated('max_depth', 20),
            $request->validated('include_containment', true),
        );

        return new TopologicalPathResource($path);
    }
}
