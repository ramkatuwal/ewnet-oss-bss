<?php

namespace App\Services\Network;

use App\Models\Company;
use App\Models\User;
use App\Models\Vlan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VlanService
{
    public function create(array $attributes, User $user): Vlan
    {
        try {
            return DB::transaction(function () use ($attributes, $user) {
                $company = Company::lockForUpdate()->findOrFail($attributes['company_id']);

                if (Vlan::where('company_id', $company->id)->where('vid', $attributes['vid'])->exists()) {
                    throw ValidationException::withMessages(['vid' => 'This VLAN ID already exists for this company.']);
                }

                return Vlan::create([
                    ...$attributes,
                    'company_id' => $company->id,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['vid' => 'This VLAN ID is invalid or already exists for this company.']);
        }
    }

    public function update(Vlan $vlan, array $attributes, User $user): Vlan
    {
        try {
            return DB::transaction(function () use ($vlan, $attributes, $user) {
                $vlan = Vlan::lockForUpdate()->findOrFail($vlan->id);
                $vlan->update([
                    ...Arr::only($attributes, ['name', 'description', 'reserved', 'metadata']),
                    'updated_by' => $user->id,
                ]);

                return $vlan->fresh();
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['vlan' => 'Cannot modify this VLAN due to data integrity constraints.']);
        }
    }

    public function delete(Vlan $vlan, User $user): void
    {
        try {
            DB::transaction(function () use ($vlan, $user) {
                $vlan = Vlan::lockForUpdate()->findOrFail($vlan->id);
                $vlan->update(['updated_by' => $user->id]);
                $vlan->delete();
            });
        } catch (QueryException $e) {
            throw ValidationException::withMessages(['vlan' => 'Cannot retire this VLAN due to data integrity constraints.']);
        }
    }
}
