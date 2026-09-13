<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vlan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'vid',
        'name',
        'description',
        'reserved',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'reserved' => 'boolean',
        'metadata' => 'array',
    ];

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

    public function networkPortVlanMemberships(): HasMany
    {
        return $this->hasMany(NetworkPortVlanMembership::class);
    }

    public function routingL3Interfaces(): HasMany
    {
        return $this->hasMany(RoutingL3Interface::class);
    }

    public function observedVlans(): HasMany
    {
        return $this->hasMany(ObservedVlan::class, 'reconciled_vlan_id');
    }
}
