<?php

namespace App\Models;

use Database\Factories\FiberCableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiberCable extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): FiberCableFactory
    {
        return FiberCableFactory::new();
    }

    protected $fillable = [
        'company_id',
        'cable_code',
        'name',
        'cable_type',
        'fiber_count',
        'status',
        'start_site_id',
        'end_site_id',
        'length_meters',
        'installation_date',
        'survey_source',
        'surveyed_at',
        'surveyed_by',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fiber_count' => 'integer',
        'length_meters' => 'decimal:2',
        'installation_date' => 'date',
        'surveyed_at' => 'datetime',
        'metadata' => 'array',
    ];

    // Cable classification. Application-level constants; no PostgreSQL enum.
    const CABLE_TYPES = [
        'backbone',
        'distribution',
        'drop',
        'other',
    ];

    // Lifecycle/status. `planned` still requires a known planned/design route.
    const STATUSES = [
        'planned',
        'installed',
        'active',
        'faulty',
        'retired',
    ];

    /**
     * No Eloquent-level geometry handling is needed on the request path,
     * because FiberCableService writes route_geometry with bound
     * parameters (ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)).
     *
     * Factories/seeds populate the transient `route_geometry_geojson`
     * attribute, which is consumed by FiberCableFactory::configure() via
     * a separate parameterized DB::update call after the row exists.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function startSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'start_site_id');
    }

    public function endSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'end_site_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function surveyedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyed_by');
    }
}
