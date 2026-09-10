<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustomerRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Http\Resources\V1\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Services\AuditService;
use App\Services\BssService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly BssService $bss) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);
        $request->validate(['search' => ['nullable', 'string', 'max:255'], 'type' => ['nullable', 'in:individual,organization'], 'status' => ['nullable', 'in:active,inactive,retired'], 'company_id' => ['nullable', 'integer'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $query = ManagementScopeService::applyScopeToQuery(Customer::query(), $request->user(), Customer::class);
        foreach (['type', 'status', 'company_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('customer_code', 'ilike', '%'.$request->string('search').'%')->orWhere('name', 'ilike', '%'.$request->string('search').'%')->orWhere('email', 'ilike', '%'.$request->string('search').'%')->orWhere('phone', 'ilike', '%'.$request->string('search').'%'));
        }

        return CustomerResource::collection($query->orderBy('customer_code')->paginate($request->integer('per_page', 15)));
    }

    public function store(StoreCustomerRequest $request)
    {
        $this->authorize('create', Customer::class);
        $customer = $this->bss->createCustomer($request->validated(), $request->user());
        AuditService::log('bss.customer.created', 'success', $customer, $this->metadata($customer));

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $customer = $this->bss->updateCustomer($customer, $request->validated(), $request->user());
        AuditService::log('bss.customer.updated', 'success', $customer, $this->metadata($customer));

        return new CustomerResource($customer);
    }

    public function destroy(Request $request, Customer $customer)
    {
        $this->authorize('delete', $customer);
        if (CustomerService::where('customer_id', $customer->id)->whereIn('status', CustomerService::LIVE_STATUSES)->exists()) {
            abort(409, 'A customer with live services cannot be retired. Terminate or cancel those services first.');
        }
        $customer->update(['status' => 'retired', 'updated_by' => $request->user()->id]);
        $customer->delete();
        AuditService::log('bss.customer.retired', 'success', $customer, $this->metadata($customer));

        return response()->json(['message' => 'Customer retired successfully.']);
    }

    private function metadata(Customer $customer): array
    {
        return ['customer_id' => $customer->id, 'company_id' => $customer->company_id, 'customer_code' => $customer->customer_code, 'status' => $customer->status];
    }
}
