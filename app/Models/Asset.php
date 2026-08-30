<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
