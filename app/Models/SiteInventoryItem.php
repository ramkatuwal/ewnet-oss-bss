<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SiteInventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_id',
        'category',
        'name',
        'asset_tag',
        'serial_number',
        'manufacturer',
        'model',
        'quantity',
        'unit',
        'status',
        'condition',
        'purchase_date',
        'installation_date',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'purchase_date' => 'date',
        'installation_date' => 'date',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
