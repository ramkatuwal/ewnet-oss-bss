<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_sites' => $this['total_sites'],
            'active_sites' => $this['active_sites'],
            'planned_sites' => $this['planned_sites'],
            'maintenance_sites' => $this['maintenance_sites'],
            'inactive_sites' => $this['inactive_sites'],
            'sites_with_assets' => $this['sites_with_assets'],
            'sites_without_coordinates' => $this['sites_without_coordinates'],
            'total_assets' => $this['total_assets'],
            'top_site_by_assets' => $this['top_site_by_assets'],
            'by_type' => $this['by_type'],
        ];
    }
}
