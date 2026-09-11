<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeasibilityCondition extends Model
{
    const TYPES = ['fiber_construction', 'pole_permission', 'equipment_requirement', 'capacity_upgrade', 'additional_survey', 'commercial_approval', 'civil_work', 'permits', 'other'];

    const STATUSES = ['pending', 'in_progress', 'resolved', 'waived', 'not_applicable'];

    protected $fillable = ['feasibility_check_id', 'company_id', 'condition_type', 'description', 'is_mandatory', 'status', 'resolved_at', 'resolved_by', 'resolution_notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'resolved_at' => 'datetime',
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

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
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
