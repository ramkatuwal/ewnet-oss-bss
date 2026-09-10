<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaticRoute extends Model
{
    use SoftDeletes;

    public const TYPES = ['forward', 'discard', 'reject'];

    protected $fillable = ['routing_instance_id', 'routing_l3_interface_id', 'asset_id', 'company_id', 'destination', 'gateway', 'route_type', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['metadata' => 'array'];

    public function routingInstance(): BelongsTo
    {
        return $this->belongsTo(RoutingInstance::class);
    }

    public function routingL3Interface(): BelongsTo
    {
        return $this->belongsTo(RoutingL3Interface::class);
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
