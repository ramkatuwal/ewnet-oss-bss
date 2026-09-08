<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalConnection extends Model
{
    use SoftDeletes;

    public const TYPES = ['fusion_splice', 'mechanical_splice'];

    protected $fillable = ['company_id', 'termination_a_id', 'termination_b_id', 'connection_type', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['metadata' => 'array'];

    public function terminationA(): BelongsTo
    {
        return $this->belongsTo(FiberTermination::class, 'termination_a_id');
    }

    public function terminationB(): BelongsTo
    {
        return $this->belongsTo(FiberTermination::class, 'termination_b_id');
    }
}
