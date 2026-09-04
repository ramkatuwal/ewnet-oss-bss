<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpAddress extends Model
{
    protected $table = 'ip_addresses';

    protected $fillable = [
        'asset_interface_id',
        'ip_address',
        'family',
        'prefix_length',
        'is_primary',
        'is_management',
        'provider',
        'external_type',
        'external_id',
        'metadata',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_management' => 'boolean',
        'family' => 'integer',
        'prefix_length' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function interface(): BelongsTo
    {
        return $this->belongsTo(AssetInterface::class, 'asset_interface_id');
    }

    public function getFullIpAttribute(): string
    {
        return $this->ip_address . ($this->prefix_length ? '/' . $this->prefix_length : '');
    }
}
