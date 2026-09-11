<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Lead::class);
        $request->validate(['company_id' => ['nullable', 'integer'], 'status' => ['nullable', 'in:new,qualified,converted,lost'], 'search' => ['nullable', 'string', 'max:255']]);
        $query = ManagementScopeService::applyScopeToQuery(Lead::query(), $request->user(), Lead::class);
        foreach (['company_id', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('lead_code', 'ilike', '%'.$request->string('search').'%')->orWhere('name', 'ilike', '%'.$request->string('search').'%')->orWhere('email', 'ilike', '%'.$request->string('search').'%')->orWhere('phone', 'ilike', '%'.$request->string('search').'%'));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate($request->integer('per_page', 15))]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Lead::class);
        $data = $this->validatedLead($request, true);
        if (! ManagementScopeService::isInScope($request->user(), Company::findOrFail($data['company_id']))) {
            throw ValidationException::withMessages(['company_id' => 'You do not have access to this company.']);
        }
        $lead = DB::transaction(function () use ($data, $request) {
            $lead = Lead::create([...$data, 'status' => 'new', 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $lead->lifecycleHistory()->create(['company_id' => $lead->company_id, 'to_status' => 'new', 'actor_id' => $request->user()->id]);

            return $lead;
        });
        AuditService::log('bss.lead.created', 'success', $lead, ['lead_id' => $lead->id, 'company_id' => $lead->company_id, 'lead_code' => $lead->lead_code]);

        return response()->json(['data' => $lead], 201);
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);

        return response()->json(['data' => $lead->load('lifecycleHistory')]);
    }

    public function qualify(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);
        if ($lead->status !== 'new') {
            throw ValidationException::withMessages(['status' => 'Only new leads can be qualified.']);
        }
        $qualification = $request->validate(['qualification' => ['required', 'array']])['qualification'];
        $lead->update(['status' => 'qualified', 'qualification' => $qualification, 'updated_by' => $request->user()->id]);
        $lead->lifecycleHistory()->create(['company_id' => $lead->company_id, 'from_status' => 'new', 'to_status' => 'qualified', 'context' => ['keys' => array_keys($qualification)], 'actor_id' => $request->user()->id]);

        return response()->json(['data' => $lead->fresh()]);
    }

    public function duplicates(Lead $lead)
    {
        $this->authorize('view', $lead);
        $query = Customer::query()->where('company_id', $lead->company_id);
        $query->where(function ($q) use ($lead) {
            $q->whereRaw('lower(name) = ?', [strtolower($lead->name)]);
            if ($lead->email) {
                $q->orWhereRaw('lower(email) = ?', [strtolower($lead->email)]);
            } if ($lead->phone) {
                $q->orWhere('phone', $lead->phone);
            }
        });

        return response()->json(['data' => $query->limit(20)->get(['id', 'customer_code', 'name', 'email', 'phone'])]);
    }

    public function convert(Request $request, Lead $lead)
    {
        $this->authorize('convert', $lead);
        $data = $request->validate(['customer_code' => ['required', 'string', 'max:64'], 'use_customer_id' => ['nullable', 'integer', 'exists:customers,id']]);
        $customer = DB::transaction(function () use ($lead, $data, $request) {
            $lead = Lead::lockForUpdate()->findOrFail($lead->id);
            if ($lead->status === 'converted') {
                return $lead->convertedCustomer;
            }
            if ($lead->status !== 'qualified') {
                throw ValidationException::withMessages(['status' => 'Only qualified leads can be converted.']);
            }
            $customer = isset($data['use_customer_id']) ? Customer::lockForUpdate()->findOrFail($data['use_customer_id']) : Customer::create(['company_id' => $lead->company_id, 'customer_code' => $data['customer_code'], 'name' => $lead->name, 'type' => 'individual', 'status' => 'active', 'email' => $lead->email, 'phone' => $lead->phone, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            if ($customer->company_id !== $lead->company_id) {
                throw ValidationException::withMessages(['use_customer_id' => 'Customer must belong to the lead company.']);
            }
            $lead->update(['status' => 'converted', 'converted_customer_id' => $customer->id, 'converted_at' => now(), 'updated_by' => $request->user()->id]);
            $lead->lifecycleHistory()->create(['company_id' => $lead->company_id, 'from_status' => 'qualified', 'to_status' => 'converted', 'context' => ['customer_id' => $customer->id], 'actor_id' => $request->user()->id]);

            return $customer;
        });
        AuditService::log('bss.lead.converted', 'success', $lead, ['lead_id' => $lead->id, 'company_id' => $lead->company_id, 'customer_id' => $customer->id]);

        return response()->json(['data' => $customer]);
    }

    private function validatedLead(Request $request, bool $create): array
    {
        return $request->validate(['company_id' => [$create ? 'required' : 'sometimes', 'integer', 'exists:companies,id'], 'source_id' => ['nullable', 'integer', 'exists:bss_sources,id'], 'lead_code' => [$create ? 'required' : 'sometimes', 'string', 'max:64'], 'name' => [$create ? 'required' : 'sometimes', 'string', 'max:255'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:64']]);
    }
}
