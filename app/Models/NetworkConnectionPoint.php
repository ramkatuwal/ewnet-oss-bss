<?php

namespace App\Models;

use Database\Factories\NetworkConnectionPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkConnectionPoint extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'point_type',
        'name',
        'description',
        'site_id',
        'asset_id',
        'asset_interface_id',
        'company_id',
        'status',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function newFactory(): NetworkConnectionPointFactory
    {
        return NetworkConnectionPointFactory::new();
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assetInterface(): BelongsTo
    {
        return $this->belongsTo(AssetInterface::class, 'asset_interface_id');
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

    public function passiveOpticalPorts(): HasMany
    {
        return $this->hasMany(PassiveOpticalPort::class);
    }
}
