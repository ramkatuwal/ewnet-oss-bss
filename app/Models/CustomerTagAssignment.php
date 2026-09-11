<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTagAssignment extends Model
{
    protected $fillable = ['customer_id', 'tag_id', 'company_id', 'created_by'];
}
