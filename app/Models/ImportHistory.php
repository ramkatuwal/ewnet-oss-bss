<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportHistory extends Model
{
    protected $table = 'import_history';

    protected $fillable = [
        'source',
        'type',
        'integration_id',
        'status',
        'started_by',
        'started_at',
        'completed_at',
        'total_records',
        'created_records',
        'updated_records',
        'skipped_records',
        'conflict_records',
        'error_records',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'total_records' => 'integer',
        'created_records' => 'integer',
        'updated_records' => 'integer',
        'skipped_records' => 'integer',
        'conflict_records' => 'integer',
        'error_records' => 'integer',
        'metadata' => 'array',
    ];

    // ── Constants ──────────────────────────────────────────────

    public const SOURCE_UISP = 'uisp';
    public const SOURCE_LIBRENMS = 'librenms';

    public const TYPE_DEVICE = 'device';
    public const TYPE_SITE = 'site';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const SOURCES = [
        self::SOURCE_UISP,
        self::SOURCE_LIBRENMS,
    ];

    public const TYPES = [
        self::TYPE_DEVICE,
        self::TYPE_SITE,
    ];

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    // ── Relationships ──────────────────────────────────────────

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeForSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // ── Helpers ────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(array $stats = []): void
    {
        $this->update(array_merge([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ], $stats));
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'completed_at' => now(),
            'error_message' => $error,
        ]);
    }

    public function getDurationInSeconds(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        return $this->started_at->diffInSeconds($this->completed_at);
    }
}
