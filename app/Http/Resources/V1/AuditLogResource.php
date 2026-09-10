<?php

namespace App\Http\Resources\V1;

use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Models\RoutingL3InterfaceAddress;
use App\Models\Vlan;
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
            $targetName = match (true) {
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
            'actor_name' => $actorName ?? 'system',
            'target' => [
                'type' => $this->target_type,
                'id' => $this->target_id,
                'name' => $targetName,
            ],
            'organization_context' => $this->organization_context,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'correlation_id' => $this->correlation_id,
            'metadata' => $this->sanitizeMetadata($this->metadata, $request),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    protected function sanitizeMetadata(?array $metadata, Request $request): ?array
    {
        if (! $metadata) {
            return null;
        }

        $sensitiveKeys = [
            'password', 'password_confirmation', 'token', 'secret',
            'api_key', 'credentials', 'access_token', 'refresh_token',
            'private_key', 'session_id', 'cookie',
        ];

        $metadata = collect($metadata)
            ->filter(fn ($value, $key) => ! in_array($key, $sensitiveKeys))
            ->map(function ($value, $key) {
                if (is_string($value) && preg_match('/^(sk_|pk_|Bearer |eyJ)/', $value)) {
                    return '[REDACTED]';
                }

                return $value;
            })
            ->toArray();

        if (in_array($this->action, [
            'net.port-switching-configured',
            'net.port-switching-config-replaced',
            'net.port-switching-config-retired',
        ], true) && isset($metadata['memberships']) && is_array($metadata['memberships'])) {
            $vlans = Vlan::whereIn('id', collect($metadata['memberships'])->pluck('vlan_id')->filter()->all())->get()->keyBy('id');
            $metadata['memberships'] = array_values(array_filter($metadata['memberships'], fn (array $membership) => isset($membership['vlan_id']) && isset($vlans[$membership['vlan_id']]) && $request->user()?->can('view', $vlans[$membership['vlan_id']])));
        }

        if (in_array($this->action, ['net.routing-instance-created', 'net.routing-instance-retired'], true)) {
            $instance = isset($metadata['routing_instance_id']) ? RoutingInstance::withTrashed()->find($metadata['routing_instance_id']) : null;
            if (! $instance || ! $request->user()?->can('view', $instance)) {
                unset($metadata['asset_id'], $metadata['company_id'], $metadata['routing_instance_id']);
            }
        }

        if (in_array($this->action, ['net.routing-l3-interface-created', 'net.routing-l3-interface-retired'], true)) {
            $interface = isset($metadata['routing_l3_interface_id']) ? RoutingL3Interface::withTrashed()->find($metadata['routing_l3_interface_id']) : null;
            if (! $interface || ! $request->user()?->can('view', $interface)) {
                unset($metadata['asset_id'], $metadata['company_id'], $metadata['network_port_id'], $metadata['vlan_id'], $metadata['parent_routing_l3_interface_id'], $metadata['routing_instance_id'], $metadata['routing_l3_interface_id']);
            }
        }

        if (in_array($this->action, ['net.routing-l3-interface-address-created', 'net.routing-l3-interface-address-retired'], true)) {
            $address = isset($metadata['routing_l3_interface_address_id']) ? RoutingL3InterfaceAddress::withTrashed()->find($metadata['routing_l3_interface_address_id']) : null;
            if (! $address || ! $request->user()?->can('view', $address)) {
                unset($metadata['asset_id'], $metadata['company_id'], $metadata['routing_instance_id'], $metadata['routing_l3_interface_id'], $metadata['routing_l3_interface_address_id']);
            }
        }

        return $metadata;
    }
}
