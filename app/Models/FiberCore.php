<?php

namespace App\Models;

use Database\Factories\FiberCoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiberCore extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['available', 'damaged', 'unknown'];

    protected $fillable = [
        'fiber_segment_id',
        'company_id',
        'core_number',
        'status',
        'color_code',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'core_number' => 'integer',
        'metadata' => 'array',
    ];

    protected static function newFactory(): FiberCoreFactory
    {
        return FiberCoreFactory::new();
    }

    public function fiberSegment(): BelongsTo
    {
        return $this->belongsTo(FiberSegment::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
