<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BssTag extends Model
{
    use SoftDeletes;

    protected $table = 'bss_tags';

    protected $fillable = ['company_id', 'kind', 'name', 'color'];
}
