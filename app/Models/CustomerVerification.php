<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerVerification extends Model
{
    protected $fillable = ['customer_id', 'company_id', 'kind', 'status', 'reference', 'reason', 'verified_by', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }
}
