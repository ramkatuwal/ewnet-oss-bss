<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerBusinessProfile extends Model
{
    protected $fillable = ['customer_id', 'company_id', 'legal_name', 'registration_number', 'tax_number', 'industry'];
}
