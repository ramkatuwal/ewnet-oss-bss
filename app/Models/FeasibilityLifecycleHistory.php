<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeasibilityLifecycleHistory extends Model
{
    protected $table = 'feasibility_lifecycle_history';

    protected $fillable = ['feasibility_check_id', 'company_id', 'from_status', 'to_status', 'from_outcome', 'to_outcome', 'context', 'actor_id'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
