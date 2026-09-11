<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerContactPerson extends Model
{
    use SoftDeletes;

    protected $table = 'customer_contact_persons';

    protected $fillable = ['customer_id', 'company_id', 'name', 'role', 'email', 'phone', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
