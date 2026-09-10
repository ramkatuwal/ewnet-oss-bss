<?php

namespace App\Services\Network;

use App\Models\Asset;
use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Models\StaticRoute;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoutingInstanceService
{
    public function create(Asset $asset, array $attributes, User $user): RoutingInstance
    {
        try {
            return DB::transaction(function () use ($asset, $attributes, $user) {
                $asset = Asset::withTrashed()->lockForUpdate()->findOrFail($asset->id);
                $this->validateAsset($asset);

                if (RoutingInstance::where('asset_id', $asset->id)->where('name', $attributes['name'])->exists()) {
                    throw ValidationException::withMessages(['name' => 'This routing instance name already exists on the asset.']);
                }
                if ($attributes['kind'] === 'default' && RoutingInstance::where('asset_id', $asset->id)->where('kind', 'default')->exists()) {
                    throw ValidationException::withMessages(['kind' => 'This asset already has a default routing instance.']);
                }

                return RoutingInstance::create([
                    'asset_id' => $asset->id,
                    'company_id' => $asset->company_id,
                    'name' => $attributes['name'],
                    'kind' => $attributes['kind'],
                    'metadata' => $attributes['metadata'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['routing_instance' => 'Cannot create this routing instance due to data integrity constraints.']);
        }
    }

    public function retire(RoutingInstance $routingInstance, User $user): void
    {
        try {
            DB::transaction(function () use ($routingInstance, $user) {
                $routingInstance = RoutingInstance::lockForUpdate()->findOrFail($routingInstance->id);
                $asset = Asset::withTrashed()->lockForUpdate()->findOrFail($routingInstance->asset_id);
                $this->validateAsset($asset);
                if (RoutingL3Interface::where('routing_instance_id', $routingInstance->id)->exists()) {
                    throw ValidationException::withMessages(['routing_instance' => 'Retire live L3 interfaces first.']);
                }
                if (StaticRoute::where('routing_instance_id', $routingInstance->id)->exists()) {
                    throw ValidationException::withMessages(['routing_instance' => 'Retire static routes first.']);
                }
                $routingInstance->update(['updated_by' => $user->id]);
                $routingInstance->delete();
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['routing_instance' => 'Cannot retire this routing instance due to data integrity constraints.']);
        }
    }

    private function validateAsset(Asset $asset): void
    {
        if ($asset->trashed() || strtoupper($asset->category) !== 'NETWORK' || $asset->company_id === null) {
            throw ValidationException::withMessages(['asset' => 'Select a live company-owned NETWORK asset.']);
        }
    }
}
