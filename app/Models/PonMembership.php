<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PonMembership extends Model
{
    use SoftDeletes;

    protected $fillable = ['pon_domain_id', 'onu_asset_id', 'onu_id', 'company_id', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['metadata' => 'array'];

    public function ponDomain(): BelongsTo
    {
        return $this->belongsTo(PonDomain::class);
    }

    public function onuAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'onu_asset_id');
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
