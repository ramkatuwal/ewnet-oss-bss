<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteExternalReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'provider',
        'external_type',
        'external_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
