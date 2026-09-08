<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SplitterProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id', 'company_id', 'input_port_count', 'output_port_count',
        'split_ratio', 'metadata', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'input_port_count' => 'integer',
        'output_port_count' => 'integer',
        'metadata' => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
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

    public function branches(): HasMany
    {
        return $this->hasMany(SplitterBranch::class);
    }
}
