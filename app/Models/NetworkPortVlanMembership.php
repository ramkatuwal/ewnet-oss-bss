<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkPortVlanMembership extends Model
{
    use SoftDeletes;

    public const TAGGING = ['tagged', 'untagged'];

    protected $fillable = ['network_port_switching_config_id', 'vlan_id', 'company_id', 'tagging', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['metadata' => 'array'];

    public function switchingConfig(): BelongsTo
    {
        return $this->belongsTo(NetworkPortSwitchingConfig::class, 'network_port_switching_config_id');
    }

    public function vlan(): BelongsTo
    {
        return $this->belongsTo(Vlan::class);
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
