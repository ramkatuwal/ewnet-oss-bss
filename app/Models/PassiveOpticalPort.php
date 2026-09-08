<?php

namespace App\Models;

use Database\Factories\PassiveOpticalPortFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PassiveOpticalPort extends Model
{
    use HasFactory, SoftDeletes;

    public const ROLES = ['generic', 'splitter_input', 'splitter_output'];

    protected $fillable = [
        'asset_id', 'network_connection_point_id', 'company_id', 'port_number',
        'connector_type', 'port_role', 'metadata', 'created_by', 'updated_by',
    ];

    protected $casts = ['metadata' => 'array'];

    protected static function newFactory(): PassiveOpticalPortFactory
    {
        return PassiveOpticalPortFactory::new();
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function networkConnectionPoint(): BelongsTo
    {
        return $this->belongsTo(NetworkConnectionPoint::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
