<?php

namespace App\Services\Fim;

use App\Models\Asset;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\SplitterProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SplitterProfileService
{
    public function create(Asset $asset, array $attributes, User $user): SplitterProfile
    {
        return DB::transaction(function () use ($asset, $attributes, $user) {
            $asset = Asset::lockForUpdate()->findOrFail($asset->id);
            Gate::forUser($user)->authorize('create', [SplitterProfile::class, $asset]);
            $this->ensureEligible($asset);

            if (SplitterProfile::withTrashed()->where('asset_id', $asset->id)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['asset_id' => 'This asset already has splitter profile history.']);
            }

            return SplitterProfile::create([
                ...Arr::only($attributes, ['input_port_count', 'output_port_count', 'split_ratio', 'metadata']),
                'asset_id' => $asset->id,
                'company_id' => $asset->company_id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ])->refresh();
        });
    }

    public function update(SplitterProfile $profile, array $attributes, User $user): SplitterProfile
    {
        return DB::transaction(function () use ($profile, $attributes, $user) {
            $profile = $this->lockedProfile($profile, $user, 'update');
            foreach (['asset_id', 'company_id'] as $field) {
                if (array_key_exists($field, $attributes)) {
                    throw ValidationException::withMessages([$field => 'Splitter profile ownership cannot be changed.']);
                }
            }

            $profile->fill(Arr::only($attributes, ['input_port_count', 'output_port_count', 'split_ratio', 'metadata']));
            if ($profile->isDirty(['input_port_count', 'output_port_count'])
                && PassiveOpticalPort::withTrashed()->where('asset_id', $profile->asset_id)
                    ->whereIn('port_role', ['splitter_input', 'splitter_output'])->exists()) {
                throw ValidationException::withMessages(['input_port_count' => 'Input and output counts cannot change after splitter port history exists.']);
            }

            $profile->updated_by = $user->id;
            $profile->save();

            return $profile;
        });
    }

    public function delete(SplitterProfile $profile, User $user): SplitterProfile
    {
        return DB::transaction(function () use ($profile, $user) {
            $profile = $this->lockedProfile($profile, $user, 'delete');
            $profile->updated_by = $user->id;
            $profile->save();
            $profile->delete();

            return $profile;
        });
    }

    public function generate(SplitterProfile $profile, array $attributes, User $user): array
    {
        return DB::transaction(function () use ($profile, $attributes, $user) {
            $profile = $this->lockedProfile($profile, $user, 'generatePorts');
            $desired = [];
            foreach (['inputs' => 'splitter_input', 'outputs' => 'splitter_output'] as $key => $role) {
                foreach ($attributes[$key] as $port) {
                    $desired[] = [
                        'port_number' => $port['port_number'],
                        'network_connection_point_id' => (int) $port['network_connection_point_id'],
                        'port_role' => $role,
                    ];
                }
            }

            $labels = array_column($desired, 'port_number');
            $existingQuery = PassiveOpticalPort::withTrashed()->where('asset_id', $profile->asset_id)
                ->where(fn ($query) => $query->whereIn('port_number', $labels)
                    ->orWhereIn('port_role', ['splitter_input', 'splitter_output']));

            // Include historical/mismatched endpoints before revealing any matching information.
            // The asset lock serializes generation and ordinary passive-port creation.
            $pointIds = array_unique([
                ...array_column($desired, 'network_connection_point_id'),
                ...(clone $existingQuery)->pluck('network_connection_point_id')->all(),
            ]);
            $points = NetworkConnectionPoint::withTrashed()->whereIn('id', $pointIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($pointIds as $id) {
                $point = $points->get($id);
                abort_unless($point !== null, 403);
                Gate::forUser($user)->authorize('view', $point);
            }

            $existing = $existingQuery->orderBy('id')->lockForUpdate()->get();
            foreach ($existing as $port) {
                // Fail closed if an out-of-band identity change raced the endpoint snapshot.
                abort_unless($points->has($port->network_connection_point_id), 403);
            }

            if (count($attributes['inputs']) !== $profile->input_port_count
                || count($attributes['outputs']) !== $profile->output_port_count) {
                throw ValidationException::withMessages(['inputs' => 'Inputs and outputs must exactly match the profile port counts.']);
            }
            if (count(array_unique($labels, SORT_STRING)) !== count($labels)) {
                throw ValidationException::withMessages(['inputs' => 'Port numbers must be unique across inputs and outputs.']);
            }
            foreach ($desired as $port) {
                $point = $points->get($port['network_connection_point_id']);
                if ($point->trashed() || (int) $point->company_id !== (int) $profile->company_id) {
                    throw ValidationException::withMessages(['inputs' => 'Every requested connection point must be active and belong to the splitter company.']);
                }
            }

            foreach ($existing as $port) {
                if (in_array($port->port_role, ['splitter_input', 'splitter_output'], true)
                    && ! in_array($port->port_number, $labels, true)) {
                    throw ValidationException::withMessages(['inputs' => 'Splitter port history exists outside the requested complete port set.']);
                }
            }

            $byLabel = $existing->keyBy('port_number');
            foreach ($desired as $port) {
                $match = $byLabel->get($port['port_number']);
                if ($match && ($match->trashed()
                    || (int) $match->company_id !== (int) $profile->company_id
                    || (int) $match->network_connection_point_id !== $port['network_connection_point_id']
                    || $match->port_role !== $port['port_role'])) {
                    throw ValidationException::withMessages(['inputs' => 'A requested port number has deleted or conflicting port history.']);
                }
            }

            // No writes occur until the entire requested set has passed validation.
            $ports = collect();
            $createdCount = 0;
            foreach ($desired as $port) {
                $match = $byLabel->get($port['port_number']);
                if ($match) {
                    $ports->push($match);
                } else {
                    $ports->push(PassiveOpticalPort::create([
                        ...$port,
                        'asset_id' => $profile->asset_id,
                        'company_id' => $profile->company_id,
                        'connector_type' => null,
                        'metadata' => null,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]));
                    $createdCount++;
                }
            }

            return [
                'ports' => $ports->sort(fn ($a, $b) => strcmp($a->port_role, $b->port_role) ?: strcmp($a->port_number, $b->port_number))->values(),
                'created_count' => $createdCount,
                'matched_existing_count' => $ports->count() - $createdCount,
            ];
        });
    }

    private function lockedProfile(SplitterProfile $profile, User $user, string $ability): SplitterProfile
    {
        $asset = Asset::lockForUpdate()->findOrFail($profile->asset_id);
        $profile = SplitterProfile::lockForUpdate()->findOrFail($profile->id);
        $profile->setRelation('asset', $asset);
        Gate::forUser($user)->authorize($ability, $profile);
        $this->ensureEligible($asset);

        return $profile;
    }

    private function ensureEligible(Asset $asset): void
    {
        if ($asset->trashed() || $asset->company_id === null
            || $asset->category !== 'INFRASTRUCTURE' || strtoupper((string) $asset->type) !== 'SPLITTER') {
            throw ValidationException::withMessages(['asset_id' => 'The asset must be an active infrastructure splitter with an explicit company.']);
        }
    }
}
