<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BssService
{
    public function createCustomer(array $attributes, User $actor): Customer
    {
        return $this->createScoped(Customer::class, $attributes, $actor, 'customer_code');
    }

    public function createService(array $attributes, User $actor): Service
    {
        return $this->createScoped(Service::class, $attributes, $actor, 'service_code');
    }

    public function updateCustomer(Customer $customer, array $attributes, User $actor): Customer
    {
        return $this->updateScoped($customer, $attributes, $actor, ['name', 'type', 'status', 'email', 'phone', 'address', 'metadata']);
    }

    public function updateService(Service $service, array $attributes, User $actor): Service
    {
        return $this->updateScoped($service, $attributes, $actor, ['name', 'type', 'status', 'description', 'metadata']);
    }

    public function createCustomerService(Customer $customer, array $attributes, User $actor): CustomerService
    {
        try {
            return DB::transaction(function () use ($customer, $attributes, $actor) {
                $customer = Customer::lockForUpdate()->findOrFail($customer->id);
                $service = Service::lockForUpdate()->findOrFail($attributes['service_id']);
                if ($customer->company_id !== $service->company_id || $customer->status === 'retired' || $service->status !== 'active') {
                    throw ValidationException::withMessages(['service_id' => 'Customer and service must be live and belong to the same company.']);
                }

                return CustomerService::create([
                    ...Arr::only($attributes, ['starts_on', 'ends_on', 'metadata']),
                    'company_id' => $customer->company_id,
                    'customer_id' => $customer->id,
                    'service_id' => $service->id,
                    'status' => 'pending',
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            });
        } catch (QueryException $exception) {
            throw ValidationException::withMessages(['service_id' => 'Unable to create this customer service because of data integrity constraints.']);
        }
    }

    public function updateCustomerService(CustomerService $customerService, array $attributes, User $actor): CustomerService
    {
        return $this->updateScoped($customerService, $attributes, $actor, ['starts_on', 'ends_on', 'metadata']);
    }

    public function transition(CustomerService $customerService, string $status, User $actor): CustomerService
    {
        $allowed = ['pending' => ['active', 'cancelled'], 'active' => ['suspended', 'terminated'], 'suspended' => ['active', 'terminated']];
        if (! in_array($status, $allowed[$customerService->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Cannot transition {$customerService->status} to {$status}."]);
        }

        return DB::transaction(function () use ($customerService, $status, $actor) {
            $record = CustomerService::lockForUpdate()->findOrFail($customerService->id);
            $record->update([
                'status' => $status,
                'activated_at' => $status === 'active' && ! $record->activated_at ? now() : $record->activated_at,
                'terminated_at' => in_array($status, ['terminated', 'cancelled'], true) ? now() : $record->terminated_at,
                'updated_by' => $actor->id,
            ]);

            return $record->fresh();
        });
    }

    private function createScoped(string $model, array $attributes, User $actor, string $code): Customer|Service
    {
        try {
            return DB::transaction(function () use ($model, $attributes, $actor, $code) {
                $company = Company::lockForUpdate()->findOrFail($attributes['company_id']);
                if ($model::where('company_id', $company->id)->where($code, $attributes[$code])->exists()) {
                    throw ValidationException::withMessages([$code => 'This code already exists for this company.']);
                }

                return $model::create([...$attributes, 'company_id' => $company->id, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            });
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([$code => 'This code is invalid or already exists for this company.']);
        }
    }

    private function updateScoped(Customer|Service|CustomerService $model, array $attributes, User $actor, array $fields): Customer|Service|CustomerService
    {
        return DB::transaction(function () use ($model, $attributes, $actor, $fields) {
            $record = $model::lockForUpdate()->findOrFail($model->id);
            $record->update([...Arr::only($attributes, $fields), 'updated_by' => $actor->id]);

            return $record->fresh();
        });
    }
}
