<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_id',
        'asset_tag',
        'serial_number',
        'category',
        'type',
        'manufacturer',
        'model',
        'quantity',
        'unit',
        'status',
        'condition',
        'purchase_date',
        'installation_date',
        'warranty_expiry',
        'specifications',
        'description',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'specifications' => 'array',
        'quantity' => 'integer',
        'purchase_date' => 'date',
        'installation_date' => 'date',
        'warranty_expiry' => 'date',
    ];

    protected $appends = [
        'primary_ip',
        'primary_mac',
    ];

    // Categories
    const CATEGORIES = [
        'POWER',
        'NETWORK',
        'INFRASTRUCTURE',
        'OTHER',
    ];

    // Statuses
    const STATUSES = [
        'OPERATIONAL',
        'SPARE',
        'MAINTENANCE',
        'FAULTY',
        'RETIRED',
        'MISSING',
        'DISPOSED',
    ];

    // Conditions
    const CONDITIONS = [
        'EXCELLENT',
        'GOOD',
        'FAIR',
        'POOR',
        'CRITICAL',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(AssetLifecycleEvent::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(AssetPhoto::class);
    }

    public function externalReferences(): HasMany
    {
        return $this->hasMany(AssetExternalReference::class);
    }

    // New relationships for interfaces and IP addresses
    public function interfaces(): HasMany
    {
        return $this->hasMany(AssetInterface::class);
    }

    public function ipAddresses(): HasManyThrough
    {
        return $this->hasManyThrough(
            IpAddress::class,
            AssetInterface::class,
            'asset_id',          // Foreign key on asset_interfaces
            'asset_interface_id', // Foreign key on ip_addresses
            'id',                // Local key on assets
            'id'                 // Local key on asset_interfaces
        );
    }

    public function getPrimaryIpAttribute(): ?string
    {
        $ips = $this->relationLoaded('ipAddresses') ? $this->ipAddresses : $this->ipAddresses()->get();

        $primary = $ips->first(fn (IpAddress $ip) => $ip->is_primary || $ip->is_management) ?? $ips->first();

        if (! $primary) {
            return null;
        }

        return $primary->ip_address.($primary->prefix_length ? '/'.$primary->prefix_length : '');
    }

    public function getPrimaryMacAttribute(): ?string
    {
        $interfaces = $this->relationLoaded('interfaces') ? $this->interfaces : $this->interfaces()->get();

        foreach ($interfaces as $interface) {
            if (! empty($interface->mac_address)) {
                return $interface->mac_address;
            }
        }

        return null;
    }

    public function managementIp(): ?IpAddress
    {
        return $this->ipAddresses()
            ->where('is_management', true)
            ->orWhere('is_primary', true)
            ->first();
    }
}
