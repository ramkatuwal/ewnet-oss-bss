<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservedVlan extends Model
{
    protected $fillable = [
        'integration_id',
        'asset_id',
        'asset_interface_id',
        'vid',
        'name',
        'vlan_type',
        'provider',
        'external_type',
        'external_id',
        'observation_status',
        'metadata',
        'reconciled_vlan_id',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'vid' => 'integer',
        'asset_interface_id' => 'integer',
        'reconciled_vlan_id' => 'integer',
        'metadata' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public const OBSERVATION_STATUSES = ['observed', 'stale'];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assetInterface(): BelongsTo
    {
        return $this->belongsTo(AssetInterface::class, 'asset_interface_id');
    }

    /**
     * The authoritative VLAN this observation was reconciled to (company+vid
     * matched). Read-only linkage; observing never authors `vlans`.
     */
    public function reconciledVlan(): BelongsTo
    {
        return $this->belongsTo(Vlan::class, 'reconciled_vlan_id');
    }
}
