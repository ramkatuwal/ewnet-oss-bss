<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerConfirmation extends Model
{
    const STATUSES = ['pending', 'confirmed', 'declined', 'expired', 'cancelled'];

    const CHANNELS = ['phone', 'office', 'email', 'portal', 'sales_agent', 'signed_document', 'other'];

    protected $fillable = ['feasibility_check_id', 'company_id', 'lead_id', 'customer_id', 'status', 'channel', 'reference_code', 'presented_summary', 'notes', 'confirmed_at', 'declined_at', 'recorded_by'];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'declined_at' => 'datetime'];
    }

    public function feasibilityCheck(): BelongsTo
    {
        return $this->belongsTo(FeasibilityCheck::class);
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

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
