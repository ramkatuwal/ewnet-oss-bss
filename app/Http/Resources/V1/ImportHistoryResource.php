<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'type' => $this->type,
            'integration_id' => $this->integration_id,
            'integration_name' => $this->whenLoaded('integration', fn () => $this->integration->name ?? null),
            'status' => $this->status,
            'started_by' => $this->started_by,
            'started_by_name' => $this->whenLoaded('startedBy', fn () => $this->startedBy->name ?? null),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'total_records' => $this->total_records,
            'created_records' => $this->created_records,
            'updated_records' => $this->updated_records,
            'skipped_records' => $this->skipped_records,
            'conflict_records' => $this->conflict_records,
            'error_records' => $this->error_records,
            'error_message' => $this->error_message,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
