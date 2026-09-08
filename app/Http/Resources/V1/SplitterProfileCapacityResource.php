<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SplitterProfileCapacityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'splitter_profile_id' => $this->resource['splitter_profile_id'],
            'total_outputs' => $this->resource['total_outputs'],
            'generated_output_ports' => $this->resource['generated_output_ports'],
            'live_branches' => $this->resource['live_branches'],
            'attached_outputs' => $this->resource['attached_outputs'],
            'unused_generated_outputs' => $this->resource['unused_generated_outputs'],
            'historical_branch_count' => $this->resource['historical_branch_count'],
            'historically_used_output_count' => $this->resource['historically_used_output_count'],
        ];
    }
}
