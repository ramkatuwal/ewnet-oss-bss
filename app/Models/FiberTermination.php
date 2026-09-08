<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiberTermination extends Model
{
    use SoftDeletes;

    protected $fillable = ['fiber_core_id', 'company_id', 'network_connection_point_id', 'segment_end', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['metadata' => 'array'];

    public function fiberCore(): BelongsTo
    {
        return $this->belongsTo(FiberCore::class);
    }

    public function networkConnectionPoint(): BelongsTo
    {
        return $this->belongsTo(NetworkConnectionPoint::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function connectionsA(): HasMany
    {
        return $this->hasMany(PhysicalConnection::class, 'termination_a_id');
    }

    public function connectionsB(): HasMany
    {
        return $this->hasMany(PhysicalConnection::class, 'termination_b_id');
    }

    public function portAttachments(): HasMany
    {
        return $this->hasMany(FiberTerminationPortAttachment::class);
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
