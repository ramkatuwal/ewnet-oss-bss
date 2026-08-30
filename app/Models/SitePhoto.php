<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
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

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
