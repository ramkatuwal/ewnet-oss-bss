<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'organization_context',
        'result',
        'ip_address',
        'user_agent',
        'correlation_id',
        'metadata',
    ];

    protected $casts = [
        'organization_context' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function actor(): MorphTo
    {
        return $this->morphTo('actor');
    }

    public function target(): MorphTo
    {
        return $this->morphTo('target');
    }

    // Override to prevent accidental updates
    public function update(array $attributes = [], array $options = [])
    {
        throw new \BadMethodCallException('Audit logs are immutable and cannot be updated.');
    }

    // Override to prevent accidental deletion
    public function delete()
    {
        throw new \BadMethodCallException('Audit logs are immutable and cannot be deleted.');
    }
}
