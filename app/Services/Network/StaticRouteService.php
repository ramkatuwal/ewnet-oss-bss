<?php

namespace App\Services\Network;

use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Models\StaticRoute;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaticRouteService
{
    public function create(RoutingInstance $instance, array $attributes, User $user): StaticRoute
    {
        $this->validateNetwork($attributes['destination'], 'destination');
        if (isset($attributes['gateway'])) {
            $this->validateGateway($attributes['gateway']);
        }

        try {
            return DB::transaction(function () use ($instance, $attributes, $user) {
                $instance = RoutingInstance::withTrashed()->lockForUpdate()->findOrFail($instance->id);
                if ($instance->trashed()) {
                    throw ValidationException::withMessages(['routing_instance' => 'Select a live routing instance.']);
                }
                DB::select('SELECT pg_advisory_xact_lock(?)', [(int) $instance->id]);
                $interface = isset($attributes['routing_l3_interface_id']) ? RoutingL3Interface::withTrashed()->lockForUpdate()->find($attributes['routing_l3_interface_id']) : null;
                if (isset($attributes['routing_l3_interface_id']) && (! $interface || $interface->trashed() || (int) $interface->routing_instance_id !== (int) $instance->id || (int) $interface->asset_id !== (int) $instance->asset_id || (int) $interface->company_id !== (int) $instance->company_id)) {
                    throw ValidationException::withMessages(['routing_l3_interface_id' => 'Select a live L3 interface in this routing instance.']);
                }

                return StaticRoute::create(['routing_instance_id' => $instance->id, 'asset_id' => $instance->asset_id, 'company_id' => $instance->company_id, ...$attributes, 'created_by' => $user->id, 'updated_by' => $user->id]);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['destination' => 'This destination conflicts with authoritative static route constraints.']);
        }
    }

    public function retire(StaticRoute $route, User $user): void
    {
        try {
            DB::transaction(function () use ($route, $user) {
                $route = StaticRoute::lockForUpdate()->findOrFail($route->id);
                $route->update(['updated_by' => $user->id]);
                $route->delete();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['static_route' => 'Cannot retire this authoritative static route due to data integrity constraints.']);
        }
    }

    private function validateNetwork(string $value, string $field): void
    {
        [$address, $prefix] = explode('/', $value, 2);
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            throw ValidationException::withMessages([$field => 'Provide a valid IPv4 or IPv6 network with a prefix length.']);
        }
        $maximum = str_contains($address, ':') ? 128 : 32;
        if (! ctype_digit($prefix) || (int) $prefix > $maximum || ! $this->isCanonicalNetwork($address, (int) $prefix)) {
            throw ValidationException::withMessages([$field => 'Provide a canonical network address with a valid prefix length.']);
        }
    }

    private function validateGateway(string $value): void
    {
        [$address, $prefix] = explode('/', $value, 2);
        if (filter_var($address, FILTER_VALIDATE_IP) === false || ! ctype_digit($prefix) || (int) $prefix !== (str_contains($address, ':') ? 128 : 32)) {
            throw ValidationException::withMessages(['gateway' => 'Provide an IPv4 /32 or IPv6 /128 gateway host address.']);
        }
    }

    private function isCanonicalNetwork(string $address, int $prefix): bool
    {
        $packed = inet_pton($address);
        $bits = strlen($packed) * 8;
        for ($bit = $prefix; $bit < $bits; $bit++) {
            if ((ord($packed[intdiv($bit, 8)]) & (1 << (7 - ($bit % 8)))) !== 0) {
                return false;
            }
        }

        return true;
    }
}
