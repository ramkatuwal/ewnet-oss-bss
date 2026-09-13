<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetInterface extends Model
{
    protected $fillable = [
        'asset_id',
        'name',
        'display_name',
        'description',
        'type',
        'mac_address',
        'speed',
        'status',
        'is_management',
        'provider',
        'integration_id',
        'external_type',
        'external_id',
        'metadata',
        'first_seen_at',
        'last_seen_at',
        'observation_status',
        'reconciled_network_port_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_management' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'speed' => 'integer',
        'reconciled_network_port_id' => 'integer',
        'integration_id' => 'integer',
    ];

    public const OBSERVATION_STATUSES = ['observed', 'stale'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function ipAddresses(): HasMany
    {
        return $this->hasMany(IpAddress::class);
    }

    public function reconciledNetworkPort(): BelongsTo
    {
        return $this->belongsTo(NetworkPort::class, 'reconciled_network_port_id');
    }

    public function managementIp(): ?IpAddress
    {
        return $this->ipAddresses()
            ->where('is_management', true)
            ->orWhere('is_primary', true)
            ->first();
    }
}
