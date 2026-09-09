<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkPortFiberTerminationAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = ['network_port_id', 'fiber_termination_id', 'company_id', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['metadata' => 'array'];

    public function networkPort(): BelongsTo
    {
        return $this->belongsTo(NetworkPort::class);
    }

    public function fiberTermination(): BelongsTo
    {
        return $this->belongsTo(FiberTermination::class);
    }
}
