<?php

namespace App\Models;

use Database\Factories\FiberTerminationPortAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiberTerminationPortAttachment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fiber_termination_id', 'passive_optical_port_id', 'company_id', 'created_by', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    protected static function newFactory(): FiberTerminationPortAttachmentFactory
    {
        return FiberTerminationPortAttachmentFactory::new();
    }

    public function fiberTermination(): BelongsTo
    {
        return $this->belongsTo(FiberTermination::class);
    }

    public function passiveOpticalPort(): BelongsTo
    {
        return $this->belongsTo(PassiveOpticalPort::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
