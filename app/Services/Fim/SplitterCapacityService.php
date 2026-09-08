<?php

namespace App\Services\Fim;

use App\Models\PassiveOpticalPort;
use App\Models\SplitterBranch;
use App\Models\SplitterProfile;

class SplitterCapacityService
{
    /**
     * Compute splitter capacity metrics.
     *
     * @return array<string, mixed>
     */
    public function capacity(SplitterProfile $profile): array
    {
        $totalOutputs = $profile->output_port_count;

        $generatedOutputPorts = PassiveOpticalPort::whereNull('deleted_at')
            ->where('asset_id', $profile->asset_id)
            ->where('port_role', 'splitter_output')
            ->count();

        $liveBranches = SplitterBranch::whereNull('deleted_at')
            ->where('splitter_profile_id', $profile->id)
            ->count();

        $attachedOutputs = $liveBranches;

        $unusedGeneratedOutputs = $generatedOutputPorts - $attachedOutputs;

        $historicalBranchCount = SplitterBranch::withTrashed()
            ->where('splitter_profile_id', $profile->id)
            ->count();

        $historicallyUsedOutputCount = SplitterBranch::withTrashed()
            ->where('splitter_profile_id', $profile->id)
            ->distinct('output_port_id')
            ->count('output_port_id');

        return [
            'splitter_profile_id' => $profile->id,
            'total_outputs' => $totalOutputs,
            'generated_output_ports' => $generatedOutputPorts,
            'live_branches' => $liveBranches,
            'attached_outputs' => $attachedOutputs,
            'unused_generated_outputs' => $unusedGeneratedOutputs,
            'historical_branch_count' => $historicalBranchCount,
            'historically_used_output_count' => $historicallyUsedOutputCount,
        ];
    }
}
