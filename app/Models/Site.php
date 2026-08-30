<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_code',
        'name',
        'type',
        'status',
        'description',
        'notes',
        'metadata',
        'latitude',
        'longitude',
        'altitude',
        'province',
        'district',
        'municipality',
        'ward',
        'tole',
        'address',
        'postal_code',
        'company_id',
        'region_id',
        'branch_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'altitude' => 'decimal:2',
    ];

    // Site Types
    const TYPES = [
        'pop',
        'tower',
        'office',
        'warehouse',
        'datacenter',
        'customer_premises',
        'solar_site',
        'repeater_site',
        'other',
    ];

    // Site Statuses
    const STATUSES = [
        'planned',
        'active',
        'maintenance',
        'inactive',
        'decommissioned',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function externalReferences(): HasMany
    {
        return $this->hasMany(SiteExternalReference::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(SiteInventoryItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SitePhoto::class);
    }
}
