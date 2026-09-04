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
        'external_type',
        'external_id',
        'metadata',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_management' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'speed' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function ipAddresses(): HasMany
    {
        return $this->hasMany(IpAddress::class);
    }

    public function managementIp(): ?IpAddress
    {
        return $this->ipAddresses()
            ->where('is_management', true)
            ->orWhere('is_primary', true)
            ->first();
    }
}
