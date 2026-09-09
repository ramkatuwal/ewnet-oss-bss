<?php

namespace App\Models;

use Database\Factories\NetworkPortFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkPort extends Model
{
    use HasFactory, SoftDeletes;

    public const PORT_DIRECTIONS = ['access', 'uplink', 'downstream', 'upstream'];

    public const STATUSES = ['active', 'inactive', 'maintenance', 'faulty'];

    protected $fillable = [
        'asset_id',
        'company_id',
        'port_key',
        'name',
        'slot',
        'card',
        'port_number',
        'connector_type',
        'port_direction',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function newFactory(): NetworkPortFactory
    {
        return NetworkPortFactory::new();
    }

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

    public function networkConnectionPoints(): HasMany
    {
        return $this->hasMany(NetworkConnectionPoint::class);
    }
}
