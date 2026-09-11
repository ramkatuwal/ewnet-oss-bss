<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CustomerConfirmationResource;
use App\Models\CustomerConfirmation;
use App\Services\AuditService;
use App\Services\FeasibilityService;
use Illuminate\Http\Request;

class CustomerConfirmationController extends Controller
{
    public function __construct(private readonly FeasibilityService $feasibility) {}

    public function confirm(Request $request, CustomerConfirmation $confirmation)
    {
        $this->authorize('confirm', $confirmation);
        $confirmation = $this->feasibility->confirmCustomerIntent($confirmation, $request->user());
        AuditService::log('bss.confirmation.confirmed', 'success', $confirmation, ['confirmation_id' => $confirmation->id, 'company_id' => $confirmation->company_id, 'feasibility_id' => $confirmation->feasibility_check_id]);

        return new CustomerConfirmationResource($confirmation);
    }

    public function decline(Request $request, CustomerConfirmation $confirmation)
    {
        $this->authorize('decline', $confirmation);
        $confirmation = $this->feasibility->declineCustomerIntent($confirmation, $request->user());
        AuditService::log('bss.confirmation.declined', 'success', $confirmation, ['confirmation_id' => $confirmation->id, 'company_id' => $confirmation->company_id, 'feasibility_id' => $confirmation->feasibility_check_id]);

        return new CustomerConfirmationResource($confirmation);
    }
}
