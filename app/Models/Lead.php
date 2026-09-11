<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = ['company_id', 'source_id', 'lead_code', 'name', 'email', 'phone', 'status', 'qualification', 'converted_customer_id', 'converted_at', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['qualification' => 'array', 'converted_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(BssSource::class, 'source_id');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function lifecycleHistory(): HasMany
    {
        return $this->hasMany(LeadLifecycleHistory::class);
    }
}
