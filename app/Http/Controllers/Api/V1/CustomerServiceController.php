<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustomerServiceRequest;
use App\Http\Requests\Api\V1\TransitionCustomerServiceRequest;
use App\Http\Requests\Api\V1\UpdateCustomerServiceRequest;
use App\Http\Resources\V1\CustomerServiceResource;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Services\AuditService;
use App\Services\BssService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class CustomerServiceController extends Controller
{
    public function __construct(private readonly BssService $bss) {}

    public function index(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);
        $this->authorize('viewAny', CustomerService::class);
        $request->validate(['status' => ['nullable', 'in:pending,active,suspended,terminated,cancelled'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $query = ManagementScopeService::applyScopeToQuery(CustomerService::with('service'), $request->user(), CustomerService::class)->where('customer_id', $customer->id);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

return CustomerServiceResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(StoreCustomerServiceRequest $request, Customer $customer)
    {
        $this->authorize('view', $customer);
        $this->authorize('create', CustomerService::class);
        $record = $this->bss->createCustomerService($customer, $request->validated(), $request->user());
        AuditService::log('bss.customer-service.created', 'success', $record, $this->metadata($record));

        return (new CustomerServiceResource($record->load('service')))->response()->setStatusCode(201);
    }

    public function show(CustomerService $customerService)
    {
        $this->authorize('view', $customerService);

        return new CustomerServiceResource($customerService->load(['customer', 'service']));
    }

    public function update(UpdateCustomerServiceRequest $request, CustomerService $customerService)
    {
        $this->authorize('update', $customerService);
        $record = $this->bss->updateCustomerService($customerService, $request->validated(), $request->user());
        AuditService::log('bss.customer-service.updated', 'success', $record, $this->metadata($record));

        return new CustomerServiceResource($record->load('service'));
    }

    public function transition(TransitionCustomerServiceRequest $request, CustomerService $customerService)
    {
        $this->authorize('update', $customerService);
        $record = $this->bss->transition($customerService, $request->string('status')->toString(), $request->user());
        AuditService::log('bss.customer-service.transitioned', 'success', $record, $this->metadata($record));

        return new CustomerServiceResource($record->load('service'));
    }

    public function destroy(Request $request, CustomerService $customerService)
    {
        $this->authorize('delete', $customerService);
        if (in_array($customerService->status, CustomerService::LIVE_STATUSES, true)) {
            abort(409, 'A live customer service must be terminated or cancelled before retirement.');
        } $customerService->update(['updated_by' => $request->user()->id]);
        $customerService->delete();
        AuditService::log('bss.customer-service.retired', 'success', $customerService, $this->metadata($customerService));

        return response()->json(['message' => 'Customer service retired successfully.']);
    }

    private function metadata(CustomerService $record): array
    {
        return ['customer_service_id' => $record->id, 'company_id' => $record->company_id, 'customer_id' => $record->customer_id, 'service_id' => $record->service_id, 'status' => $record->status];
    }
}
