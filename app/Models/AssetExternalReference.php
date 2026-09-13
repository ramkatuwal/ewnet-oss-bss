<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetExternalReference extends Model
{
    protected $fillable = [
        'asset_id',
        'provider',
        'external_type',
        'external_id',
        'metadata',
        'integration_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'integration_id' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id');
    }
}
