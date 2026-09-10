<?php

namespace App\Services\Network;

use App\Models\RoutingL3Interface;
use App\Models\RoutingL3InterfaceAddress;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoutingL3InterfaceAddressService
{
    public function create(RoutingL3Interface $interface, array $attributes, User $user): RoutingL3InterfaceAddress
    {
        $prefixLength = $this->validateAddress($attributes['address']);

        try {
            return DB::transaction(function () use ($interface, $attributes, $prefixLength, $user) {
                $interface = RoutingL3Interface::withTrashed()->lockForUpdate()->findOrFail($interface->id);
                if ($interface->trashed()) {
                    throw ValidationException::withMessages(['routing_l3_interface' => 'Select a live routing L3 interface.']);
                }
                DB::select('SELECT pg_advisory_xact_lock(?)', [(int) $interface->routing_instance_id]);
                DB::select('SELECT pg_advisory_xact_lock(?)', [(int) $interface->id]);

                return RoutingL3InterfaceAddress::create([
                    'routing_l3_interface_id' => $interface->id,
                    'routing_instance_id' => $interface->routing_instance_id,
                    'asset_id' => $interface->asset_id,
                    'company_id' => $interface->company_id,
                    'prefix_length' => $prefixLength,
                    ...$attributes,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['address' => 'This address conflicts with authoritative routing address constraints.']);
        }
    }

    public function retire(RoutingL3InterfaceAddress $address, User $user): void
    {
        try {
            DB::transaction(function () use ($address, $user) {
                $address = RoutingL3InterfaceAddress::lockForUpdate()->findOrFail($address->id);
                $address->update(['updated_by' => $user->id]);
                $address->delete();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages(['address' => 'Cannot retire this authoritative address due to data integrity constraints.']);
        }
    }

    private function validateAddress(string $address): int
    {
        [$host, $prefix] = explode('/', $address, 2);
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw ValidationException::withMessages(['address' => 'Provide a valid IPv4 or IPv6 address with a prefix length.']);
        }
        $maximumPrefix = str_contains($host, ':') ? 128 : 32;
        if (! ctype_digit($prefix) || (int) $prefix > $maximumPrefix) {
            throw ValidationException::withMessages(['address' => "The prefix length must be between 0 and {$maximumPrefix} for this address family."]);
        }

        return (int) $prefix;
    }
}
