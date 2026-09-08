<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SplitterBranch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'splitter_profile_id', 'asset_id', 'company_id',
        'input_port_id', 'output_port_id', 'created_by',
    ];

    public function splitterProfile(): BelongsTo
    {
        return $this->belongsTo(SplitterProfile::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inputPort(): BelongsTo
    {
        return $this->belongsTo(PassiveOpticalPort::class, 'input_port_id');
    }

    public function outputPort(): BelongsTo
    {
        return $this->belongsTo(PassiveOpticalPort::class, 'output_port_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
