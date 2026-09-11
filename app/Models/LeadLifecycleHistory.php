<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadLifecycleHistory extends Model
{
    protected $table = 'lead_lifecycle_history';

    protected $fillable = ['lead_id', 'company_id', 'from_status', 'to_status', 'context', 'actor_id'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }
}
