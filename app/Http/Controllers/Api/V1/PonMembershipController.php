<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePonMembershipRequest;
use App\Http\Resources\V1\PonMembershipResource;
use App\Models\Asset;
use App\Models\PonDomain;
use App\Models\PonMembership;
use App\Services\AuditService;
use App\Services\Network\PonMembershipService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PonMembershipController extends Controller
{
    public function __construct(protected PonMembershipService $memberships) {}

    public function index(Request $request, PonDomain $ponDomain)
    {
        $this->authorize('view', $ponDomain);
        $this->authorize('viewAny', PonMembership::class);
        $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $visible = PonMembership::where('pon_domain_id', $ponDomain->id)
            ->with(['ponDomain.oltPort.asset.site', 'onuAsset.site'])
            ->get()->filter(fn ($m) => $request->user()->can('view', $m))->values();
        $perPage = $request->integer('per_page', 15);
        $page = $request->integer('page', 1);

        return PonMembershipResource::collection(new LengthAwarePaginator(
            $visible->forPage($page, $perPage)->values(), $visible->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }

    public function store(StorePonMembershipRequest $request, PonDomain $ponDomain)
    {
        $onuAsset = Asset::findOrFail($request->validated('onu_asset_id'));
        $this->authorize('create', [PonMembership::class, $ponDomain, $onuAsset]);
        $membership = $this->memberships->create(
            $ponDomain,
            $request->validated('onu_asset_id'),
            $request->user(),
            $request->validated('onu_id'),
            $request->validated('metadata'),
        );
        AuditService::log('net.pon-membership.created', 'success', $membership, $membership->only(['id', 'pon_domain_id', 'onu_asset_id', 'onu_id', 'company_id']));

        return (new PonMembershipResource($membership))->response()->setStatusCode(201);
    }

    public function show(PonMembership $ponMembership)
    {
        $this->authorize('view', $ponMembership);

        return new PonMembershipResource($ponMembership);
    }

    public function destroy(Request $request, PonMembership $ponMembership)
    {
        $this->authorize('delete', $ponMembership);
        $this->memberships->delete($ponMembership, $request->user());
        AuditService::log('net.pon-membership.disconnected', 'success', $ponMembership, $ponMembership->only(['id', 'pon_domain_id', 'onu_asset_id', 'onu_id', 'company_id']));

        return response()->json(['message' => 'PON membership retired successfully.']);
    }
}
