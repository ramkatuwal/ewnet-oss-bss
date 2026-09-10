<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoutingL3Interface extends Model
{
    use SoftDeletes;

    public const KINDS = ['physical', 'svi', 'subinterface', 'loopback'];

    protected $fillable = ['routing_instance_id', 'asset_id', 'company_id', 'name', 'kind', 'network_port_id', 'vlan_id', 'parent_routing_l3_interface_id', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['metadata' => 'array'];

    public function routingInstance(): BelongsTo
    {
        return $this->belongsTo(RoutingInstance::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function networkPort(): BelongsTo
    {
        return $this->belongsTo(NetworkPort::class);
    }

    public function vlan(): BelongsTo
    {
        return $this->belongsTo(Vlan::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_routing_l3_interface_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(RoutingL3InterfaceAddress::class);
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
