<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SplitterBranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'splitter_profile_id' => $this->splitter_profile_id,
            'asset_id' => $this->asset_id,
            'company_id' => $this->company_id,
            'input_port_id' => $this->input_port_id,
            'output_port_id' => $this->output_port_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
