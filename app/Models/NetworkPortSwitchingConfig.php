<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkPortSwitchingConfig extends Model
{
    use SoftDeletes;

    public const MODES = ['access', 'trunk'];

    protected $fillable = ['network_port_id', 'company_id', 'mode', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['metadata' => 'array'];

    public function networkPort(): BelongsTo
    {
        return $this->belongsTo(NetworkPort::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(NetworkPortVlanMembership::class);
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
