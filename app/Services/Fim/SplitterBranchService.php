<?php

namespace App\Services\Fim;

use App\Models\Asset;
use App\Models\PassiveOpticalPort;
use App\Models\SplitterBranch;
use App\Models\SplitterProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SplitterBranchService
{
    public function create(SplitterProfile $profile, array $attributes, User $user): SplitterBranch
    {
        return DB::transaction(function () use ($profile, $attributes, $user) {
            $inputPortId = (int) $attributes['input_port_id'];
            $outputPortId = (int) $attributes['output_port_id'];

            $asset = Asset::lockForUpdate()->findOrFail($profile->asset_id);
            Gate::forUser($user)->authorize('create', [SplitterBranch::class, $profile]);

            $profile = SplitterProfile::lockForUpdate()->findOrFail($profile->id);
            if ($profile->trashed()) {
                throw ValidationException::withMessages(['splitter_profile_id' => 'Splitter profile must be live.']);
            }
            if ((int) $profile->asset_id !== (int) $asset->id || (int) $profile->company_id !== (int) $asset->company_id) {
                throw ValidationException::withMessages(['splitter_profile_id' => 'Splitter profile asset/company mismatch.']);
            }

            $portIds = $inputPortId < $outputPortId ? [$inputPortId, $outputPortId] : [$outputPortId, $inputPortId];
            $ports = PassiveOpticalPort::lockForUpdate()->whereIn('id', $portIds)->get()->keyBy('id');

            $inputPort = $ports->get($inputPortId);
            $outputPort = $ports->get($outputPortId);

            if (! $inputPort || $inputPort->trashed()) {
                throw ValidationException::withMessages(['input_port_id' => 'Input port must be live.']);
            }
            if ((int) $inputPort->asset_id !== (int) $asset->id || (int) $inputPort->company_id !== (int) $asset->company_id) {
                throw ValidationException::withMessages(['input_port_id' => 'Input port asset/company mismatch.']);
            }
            if ($inputPort->port_role !== 'splitter_input') {
                throw ValidationException::withMessages(['input_port_id' => 'Input port must have splitter_input role.']);
            }

            if (! $outputPort || $outputPort->trashed()) {
                throw ValidationException::withMessages(['output_port_id' => 'Output port must be live.']);
            }
            if ((int) $outputPort->asset_id !== (int) $asset->id || (int) $outputPort->company_id !== (int) $asset->company_id) {
                throw ValidationException::withMessages(['output_port_id' => 'Output port asset/company mismatch.']);
            }
            if ($outputPort->port_role !== 'splitter_output') {
                throw ValidationException::withMessages(['output_port_id' => 'Output port must have splitter_output role.']);
            }

            if ($inputPortId === $outputPortId) {
                throw ValidationException::withMessages(['input_port_id' => 'Input and output ports must differ.']);
            }

            if (SplitterBranch::where('output_port_id', $outputPortId)->whereNull('deleted_at')->exists()) {
                throw ValidationException::withMessages(['output_port_id' => 'Output port is already attached to a live branch.']);
            }

            $branch = SplitterBranch::create([
                'splitter_profile_id' => $profile->id,
                'asset_id' => $asset->id,
                'company_id' => $asset->company_id,
                'input_port_id' => $inputPortId,
                'output_port_id' => $outputPortId,
                'created_by' => $user->id,
            ]);

            return $branch->refresh();
        });
    }

    public function delete(SplitterBranch $branch, User $user): SplitterBranch
    {
        return DB::transaction(function () use ($branch, $user) {
            Gate::forUser($user)->authorize('delete', $branch);
            $branch->delete();

            return $branch;
        });
    }
}
