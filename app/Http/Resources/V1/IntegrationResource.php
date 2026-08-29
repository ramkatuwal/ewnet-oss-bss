<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'provider' => $this->provider,
            'type' => $this->type,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'status' => $this->status,
            'configuration' => $this->configuration,
            'last_health_check_at' => $this->last_health_check_at?->toISOString(),
            'last_sync_at' => $this->last_sync_at?->toISOString(),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator->name ?? null),
            'updated_by' => $this->whenLoaded('updater', fn () => $this->updater->name ?? null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
