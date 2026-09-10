<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoutingL3InterfaceAddress extends Model
{
    use SoftDeletes;

    public const ROLES = ['primary', 'secondary'];

    protected $fillable = ['routing_l3_interface_id', 'routing_instance_id', 'asset_id', 'company_id', 'address', 'prefix_length', 'address_role', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['metadata' => 'array', 'prefix_length' => 'integer'];

    public function routingL3Interface(): BelongsTo
    {
        return $this->belongsTo(RoutingL3Interface::class);
    }

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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
