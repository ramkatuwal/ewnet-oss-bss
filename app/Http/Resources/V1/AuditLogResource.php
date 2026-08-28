<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actorName = null;
        $actorEmail = null;
        
        if ($this->relationLoaded('actor') && $this->actor) {
            $actorName = $this->actor->name ?? null;
            $actorEmail = $this->actor->email ?? null;
        }

        $targetName = null;
        if ($this->relationLoaded('target') && $this->target) {
            $target = $this->target;
            $targetName = match(true) {
                method_exists($target, 'getNameAttribute') => $target->name ?? null,
                property_exists($target, 'name') => $target->name ?? null,
                property_exists($target, 'email') => $target->email ?? null,
                default => $target->getKey() ? "ID: {$target->getKey()}" : null,
            };
        }

        return [
            'id' => $this->id,
            'action' => $this->action,
            'result' => $this->result,
            'actor' => [
                'type' => $this->actor_type,
                'id' => $this->actor_id,
                'name' => $actorName ?? 'Deleted User',
                'email' => $actorEmail,
            ],
            'target' => [
                'type' => $this->target_type,
                'id' => $this->target_id,
                'name' => $targetName,
            ],
            'organization_context' => $this->organization_context,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'correlation_id' => $this->correlation_id,
            'metadata' => $this->sanitizeMetadata($this->metadata),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function sanitizeMetadata(?array $metadata): ?array
    {
        if (!$metadata) {
            return null;
        }

        $sensitiveKeys = [
            'password', 'password_confirmation', 'token', 'secret', 
            'api_key', 'credentials', 'access_token', 'refresh_token',
            'private_key', 'session_id', 'cookie'
        ];

        return collect($metadata)
            ->filter(fn($value, $key) => !in_array($key, $sensitiveKeys))
            ->map(function ($value, $key) {
                if (is_string($value) && preg_match('/^(sk_|pk_|Bearer |eyJ)/', $value)) {
                    return '[REDACTED]';
                }
                return $value;
            })
            ->toArray();
    }
}
