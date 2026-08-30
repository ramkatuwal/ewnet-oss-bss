<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'path',
        'title',
        'category',
        'description',
        'taken_at',
        'uploaded_by',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    const CATEGORIES = [
        'asset',
        'documentation',
        'label',
        'installation',
        'other',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
