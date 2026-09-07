<?php

namespace App\Models;

use Database\Factories\FiberSegmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiberSegment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['planned', 'installed', 'active', 'faulty', 'retired'];

    protected $fillable = [
        'fiber_cable_id',
        'endpoint_a_id',
        'endpoint_b_id',
        'company_id',
        'sequence',
        'length_meters',
        'status',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'length_meters' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function newFactory(): FiberSegmentFactory
    {
        return FiberSegmentFactory::new();
    }

    public function fiberCable(): BelongsTo
    {
        return $this->belongsTo(FiberCable::class);
    }

    public function endpointA(): BelongsTo
    {
        return $this->belongsTo(NetworkConnectionPoint::class, 'endpoint_a_id');
    }

    public function endpointB(): BelongsTo
    {
        return $this->belongsTo(NetworkConnectionPoint::class, 'endpoint_b_id');
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
