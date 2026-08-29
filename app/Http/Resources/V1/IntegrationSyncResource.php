<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationSyncResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'integration_id' => $this->integration_id,
            'operation' => $this->operation,
            'status' => $this->status,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'records_processed' => $this->records_processed,
            'records_created' => $this->records_created,
            'records_updated' => $this->records_updated,
            'records_unchanged' => $this->records_unchanged,
            'records_failed' => $this->records_failed,
            'error_summary' => $this->error_summary,
            'initiated_by' => $this->whenLoaded('initiator', fn () => $this->initiator->name ?? null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
