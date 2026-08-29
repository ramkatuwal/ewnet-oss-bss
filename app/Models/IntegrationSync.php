<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationSync extends Model
{
    protected $fillable = [
        'integration_id',
        'operation',
        'status',
        'started_at',
        'finished_at',
        'records_processed',
        'records_created',
        'records_updated',
        'records_unchanged',
        'records_failed',
        'error_summary',
        'metadata',
        'initiated_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];

    public const OPERATIONS = ['full', 'incremental', 'manual', 'scheduled', 'webhook'];
    public const STATUSES = ['pending', 'running', 'completed', 'failed', 'cancelled'];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function markRunning(): void
    {
        $this->update(['status' => 'running', 'started_at' => now()]);
    }

    public function markCompleted(array $counts = []): void
    {
        $this->update(array_merge([
            'status' => 'completed',
            'finished_at' => now(),
        ], $counts));
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_summary' => mb_substr($error, 0, 2000),
        ]);
    }
}
