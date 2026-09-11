<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feasibility_check_id' => $this->feasibility_check_id,
            'company_id' => $this->company_id,
            'lead_id' => $this->lead_id,
            'customer_id' => $this->customer_id,
            'status' => $this->status,
            'channel' => $this->channel,
            'reference_code' => $this->reference_code,
            'presented_summary' => $this->presented_summary,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'recorded_by_name' => $this->recorded_by ? User::query()->whereKey($this->recorded_by)->value('name') : null,
            'confirmed_at' => $this->confirmed_at,
            'declined_at' => $this->declined_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
