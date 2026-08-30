<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibreNmsObject extends Model
{
    protected $table = 'librenms_objects';

    protected $fillable = [
        'integration_id',
        'object_type',
        'external_id',
        'external_parent_id',
        'data',
        'display_name',
        'status',
        'last_synced_at',
    ];

    protected $casts = [
        'data' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public const TYPES = ['device', 'port', 'alert', 'poller'];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /**
     * Upsert a LibreNMS object by external identity.
     * Returns 'created', 'updated', or 'unchanged'.
     */
    public static function upsertObject(
        int $integrationId,
        string $objectType,
        string $externalId,
        array $data,
        ?string $displayName = null,
        ?string $status = null,
        ?string $externalParentId = null
    ): string {
        $existing = static::where('integration_id', $integrationId)
            ->where('object_type', $objectType)
            ->where('external_id', $externalId)
            ->first();

        if (!$existing) {
            static::create([
                'integration_id' => $integrationId,
                'object_type' => $objectType,
                'external_id' => $externalId,
                'external_parent_id' => $externalParentId,
                'data' => $data,
                'display_name' => $displayName,
                'status' => $status,
                'last_synced_at' => now(),
            ]);
            return 'created';
        }

        // Check if data actually changed
        if ($existing->data === $data && $existing->display_name === $displayName && $existing->status === $status) {
            $existing->touch('last_synced_at');
            return 'unchanged';
        }

        $existing->update([
            'data' => $data,
            'display_name' => $displayName,
            'status' => $status,
            'external_parent_id' => $externalParentId ?? $existing->external_parent_id,
            'last_synced_at' => now(),
        ]);
        return 'updated';
    }
}
