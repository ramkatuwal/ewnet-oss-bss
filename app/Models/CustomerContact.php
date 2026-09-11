<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerContact extends Model
{
    use SoftDeletes;

    protected $fillable = ['customer_id', 'company_id', 'kind', 'value', 'is_primary', 'verified', 'verified_at', 'verified_by'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'verified' => 'boolean', 'verified_at' => 'datetime'];
    }
}
