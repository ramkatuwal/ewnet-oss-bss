<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'company_id' => $this->company_id,
            'asset_tag' => $this->asset_tag,
            'device_name' => $this->device_name,
            'management_ip' => $this->management_ip,
            'serial_number' => $this->serial_number,
            'category' => $this->category,
            'type' => $this->type,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'status' => $this->status,
            'condition' => $this->condition,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'installation_date' => $this->installation_date?->toDateString(),
            'warranty_expiry' => $this->warranty_expiry?->toDateString(),
            'specifications' => $this->specifications,
            'description' => $this->description,
            'notes' => $this->notes,
            'ip_address' => $this->primary_ip,
            'mac_address' => $this->primary_mac,
            // Provider observation fields (extracted from specifications for convenience)
            'provider_observations' => $this->extractObservations(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Eager-loaded relationships
            'site' => new SiteResource($this->whenLoaded('site')),
        ];
    }

    /**
     * Extract provider observation fields from specifications.
     * These are OBSERVED data — they never override authoritative Asset fields.
     */
    protected function extractObservations(): ?array
    {
        $specs = $this->specifications ?? [];
        $source = $specs['source'] ?? null;
        if (! $source) {
            return null;
        }

        return [
            'provider' => $source,
            'external_id' => $specs['external_id'] ?? null,
            'integration_id' => $specs['integration_id'] ?? null,
            'observed_display' => $specs['observed_display'] ?? null,
            'observed_sys_name' => $specs['observed_sys_name'] ?? null,
            'provider_type' => $specs['provider_type'] ?? null,
            'provider_status' => $specs['provider_status'] ?? null,
            'observed_hostname' => $specs['observed_hostname'] ?? null,
            'observed_os' => $specs['observed_os'] ?? null,
            'observed_hardware' => $specs['observed_hardware'] ?? null,
            'observed_version' => $specs['observed_version'] ?? null,
            'observed_uptime' => $specs['observed_uptime'] ?? null,
            'ip_address' => $specs['ip_address'] ?? null,
            'serial_number' => $specs['serial_number'] ?? null,
            'mac_address' => $specs['mac_address'] ?? null,
            'last_observed_at' => $specs['last_observed_at'] ?? null,
            'last_synced' => $specs['last_synced'] ?? null,
            'last_poll' => $specs['last_poll'] ?? null,
        ];
    }
}
