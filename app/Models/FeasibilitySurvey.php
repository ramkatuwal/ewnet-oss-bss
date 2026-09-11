<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeasibilitySurvey extends Model
{
    const STATUSES = ['requested', 'scheduled', 'in_progress', 'completed', 'cancelled', 'no_access'];

    const INSTALLATION_COMPLEXITIES = ['simple', 'moderate', 'complex', 'unknown'];

    const RECOMMENDED_OUTCOMES = ['feasible', 'conditionally_feasible', 'not_feasible'];

    protected $fillable = [
        'feasibility_check_id', 'company_id', 'assigned_to', 'status',
        'scheduled_at', 'started_at', 'completed_at',
        'location_verified', 'coordinates_verified_lat', 'coordinates_verified_lng',
        'nearest_infrastructure_notes', 'access_path_notes', 'civil_work_required',
        'installation_complexity', 'signal_observations', 'survey_notes', 'findings', 'recommended_outcome',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'location_verified' => 'boolean',
            'coordinates_verified_lat' => 'decimal:7',
            'coordinates_verified_lng' => 'decimal:7',
            'civil_work_required' => 'boolean',
        ];
    }

    public function feasibilityCheck(): BelongsTo
    {
        return $this->belongsTo(FeasibilityCheck::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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
