<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserManagementScope extends Model
{
    protected $fillable = [
        'user_id',
        'scope_type',
        'scope_id',
        'granted_by',
        'granted_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'scope_id' => 'integer',
    ];

    public const SCOPE_TYPES = ['company', 'region', 'branch', 'department'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Resolve the actual organizational entity this scope points to.
     */
    public function resolveEntity(): ?Model
    {
        return match ($this->scope_type) {
            'company' => Company::find($this->scope_id),
            'region' => Region::find($this->scope_id),
            'branch' => Branch::find($this->scope_id),
            'department' => Department::find($this->scope_id),
            default => null,
        };
    }

    /**
     * Get the human-readable name of the scoped entity.
     */
    public function getScopeNameAttribute(): ?string
    {
        $entity = $this->resolveEntity();
        return $entity?->name ?? null;
    }

    /**
     * Validate that scope_type is valid and scope_id exists.
     */
    public static function validateScope(string $type, int $id): bool
    {
        if (!in_array($type, self::SCOPE_TYPES)) {
            return false;
        }

        $exists = match ($type) {
            'company' => Company::where('id', $id)->exists(),
            'region' => Region::where('id', $id)->exists(),
            'branch' => Branch::where('id', $id)->exists(),
            'department' => Department::where('id', $id)->exists(),
            default => false,
        };

        return $exists;
    }
}
