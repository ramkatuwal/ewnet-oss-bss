<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePonDomainRequest;
use App\Http\Resources\V1\PonDomainResource;
use App\Models\NetworkPort;
use App\Models\PonDomain;
use App\Services\AuditService;
use App\Services\Network\PonDomainService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PonDomainController extends Controller
{
    public function __construct(protected PonDomainService $domains) {}

    public function index(Request $request, NetworkPort $networkPort)
    {
        $this->authorize('view', $networkPort);
        $this->authorize('viewAny', PonDomain::class);
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $visible = PonDomain::where('olt_port_id', $networkPort->id)
            ->with(['oltPort.asset.site'])
            ->get()->filter(fn ($domain) => $request->user()->can('view', $domain))->values();
        $perPage = $request->integer('per_page', 15);
        $page = $request->integer('page', 1);

        return PonDomainResource::collection(new LengthAwarePaginator(
            $visible->forPage($page, $perPage)->values(), $visible->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }

    public function store(StorePonDomainRequest $request, NetworkPort $networkPort)
    {
        $this->authorize('create', [PonDomain::class, $networkPort]);
        $domain = $this->domains->create($networkPort, $request->user(), $request->validated('metadata'));
        AuditService::log('net.pon-domain.created', 'success', $domain, $domain->only(['id', 'olt_port_id', 'company_id']));

        return (new PonDomainResource($domain))->response()->setStatusCode(201);
    }

    public function show(PonDomain $ponDomain)
    {
        $this->authorize('view', $ponDomain);

        return new PonDomainResource($ponDomain);
    }

    public function destroy(Request $request, PonDomain $ponDomain)
    {
        $this->authorize('delete', $ponDomain);
        $this->domains->delete($ponDomain, $request->user());
        AuditService::log('net.pon-domain.disconnected', 'success', $ponDomain, $ponDomain->only(['id', 'olt_port_id', 'company_id']));

        return response()->json(['message' => 'PON domain retired successfully.']);
    }
}
