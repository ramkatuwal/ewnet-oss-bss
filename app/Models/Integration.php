<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'type',
        'description',
        'enabled',
        'status',
        'configuration',
        'last_health_check_at',
        'last_sync_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'configuration' => 'array',
        'enabled' => 'boolean',
        'last_health_check_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    public const TYPES = [
        'monitoring',
        'aaa',
        'network_device',
        'access_network',
        'dns',
        'dhcp',
        'logging',
        'authentication',
        'billing',
        'other',
    ];

    public const STATUSES = [
        'unknown',
        'pending',
        'connected',
        'degraded',
        'failed',
        'disabled',
    ];

    public function credentials(): HasMany
    {
        return $this->hasMany(IntegrationCredential::class);
    }

    public function syncs(): HasMany
    {
        return $this->hasMany(IntegrationSync::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activeCredentials(): HasMany
    {
        return $this->credentials()->where('is_active', true);
    }
}
