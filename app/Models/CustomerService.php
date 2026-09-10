<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerService extends Model
{
    use HasFactory, SoftDeletes;

    public const LIVE_STATUSES = ['pending', 'active', 'suspended'];

    protected $fillable = ['company_id', 'customer_id', 'service_id', 'status', 'starts_on', 'ends_on', 'activated_at', 'terminated_at', 'metadata', 'created_by', 'updated_by'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'activated_at' => 'datetime', 'terminated_at' => 'datetime', 'metadata' => 'array'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
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
