<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeasibilityCheck extends Model
{
    use SoftDeletes;

    // Application-level controlled vocabulary; PostgreSQL CHECK constraints enforce these server-side.
    const STATUSES = ['requested', 'reviewing', 'survey_required', 'survey_scheduled', 'surveyed', 'feasible', 'conditionally_feasible', 'not_feasible', 'cancelled', 'expired'];

    const ACTIVE_STATUSES = ['requested', 'reviewing', 'survey_required', 'survey_scheduled', 'surveyed'];

    const OUTCOMES = ['feasible', 'conditionally_feasible', 'not_feasible'];

    const ASSESSMENT_METHODS = ['desk_review', 'gis_review', 'network_review', 'field_survey', 'hybrid', 'other'];

    protected $fillable = [
        'company_id', 'feasibility_code', 'lead_id', 'customer_id', 'customer_address_id',
        'requested_service_summary', 'requested_location_summary',
        'requested_location_lat', 'requested_location_lng',
        'status', 'outcome', 'assessment_method', 'assigned_assessor_user_id',
        'requested_at', 'assessment_started_at', 'assessed_at', 'valid_until',
        'conditions_summary', 'estimated_work_summary', 'internal_notes', 'customer_safe_summary',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_location_lat' => 'decimal:7',
            'requested_location_lng' => 'decimal:7',
            'requested_at' => 'datetime',
            'assessment_started_at' => 'datetime',
            'assessed_at' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    public function assignedAssessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_assessor_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(FeasibilityEvidence::class);
    }

    public function survey(): HasOne
    {
        return $this->hasOne(FeasibilitySurvey::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(FeasibilityCondition::class);
    }

    public function confirmation(): HasOne
    {
        return $this->hasOne(CustomerConfirmation::class);
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(CustomerConfirmation::class);
    }

    public function lifecycleHistory(): HasMany
    {
        return $this->hasMany(FeasibilityLifecycleHistory::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['feasible', 'conditionally_feasible', 'not_feasible', 'cancelled', 'expired'], true);
    }

    public function confirmsAllowedOutcome(): bool
    {
        return in_array($this->outcome, ['feasible', 'conditionally_feasible'], true);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }
}
