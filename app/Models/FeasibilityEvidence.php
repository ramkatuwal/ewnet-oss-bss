<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeasibilityEvidence extends Model
{
    const EVIDENCE_TYPES = ['site_observation', 'asset_observation', 'port_observation', 'fiber_observation', 'pon_observation', 'gis_analysis', 'network_analysis', 'provider_observation', 'manual_observation', 'other'];

    // Entity types that may be referenced as read-only infrastructure evidence.
    const REFERENCED_ENTITY_TYPES = ['Site', 'Asset', 'NetworkPort', 'PonDomain', 'PassiveOpticalPort', 'FiberTermination', 'NetworkConnectionPoint', 'FiberCable', 'FiberSegment'];

    protected $fillable = ['feasibility_check_id', 'company_id', 'evidence_type', 'referenced_entity_type', 'referenced_entity_id', 'observation_summary', 'recorded_by', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    public function feasibilityCheck(): BelongsTo
    {
        return $this->belongsTo(FeasibilityCheck::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
