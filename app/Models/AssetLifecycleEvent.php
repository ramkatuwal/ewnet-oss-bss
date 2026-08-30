<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetLifecycleEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'event_type',
        'status_before',
        'status_after',
        'from_site_id',
        'to_site_id',
        'notes',
        'metadata',
        'created_by',
        'event_date',
    ];

    protected $casts = [
        'metadata' => 'array',
        'event_date' => 'datetime',
    ];

    const EVENT_TYPES = [
        'RECEIVED',
        'INSTALLED',
        'STATUS_CHANGED',
        'MAINTENANCE_STARTED',
        'MAINTENANCE_COMPLETED',
        'TRANSFERRED',
        'RETIRED',
        'DISPOSED',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'from_site_id');
    }

    public function toSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'to_site_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
