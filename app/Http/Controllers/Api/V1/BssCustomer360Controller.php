<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BssSource;
use App\Models\BssTag;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerBusinessProfile;
use App\Models\CustomerContact;
use App\Models\CustomerContactPerson;
use App\Models\CustomerNote;
use App\Models\CustomerTagAssignment;
use App\Models\CustomerVerification;
use App\Services\AuditService;
use App\Services\ManagementScopeService;
use Illuminate\Http\Request;

class BssCustomer360Controller extends Controller
{
    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);

        return response()->json(['data' => $customer->load(['contacts', 'contactPersons', 'addresses', 'businessProfile', 'verifications', 'notes'])]);
    }

    public function storeContact(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $data = $request->validate(['kind' => ['required', 'in:email,phone,other'], 'value' => ['required', 'string', 'max:255'], 'is_primary' => ['sometimes', 'boolean']]);
        $contact = CustomerContact::create([...$data, 'customer_id' => $customer->id, 'company_id' => $customer->company_id]);
        AuditService::log('bss.customer.contact.created', 'success', $customer, ['customer_id' => $customer->id, 'contact_id' => $contact->id, 'kind' => $contact->kind]);

        return response()->json(['data' => $contact], 201);
    }

    public function storePerson(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'role' => ['nullable', 'string', 'max:128'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:64'], 'is_primary' => ['sometimes', 'boolean']]);

        return response()->json(['data' => CustomerContactPerson::create([...$data, 'customer_id' => $customer->id, 'company_id' => $customer->company_id])], 201);
    }

    public function storeAddress(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $data = $request->validate(['kind' => ['sometimes', 'in:billing,service,other'], 'line1' => ['required', 'string', 'max:255'], 'line2' => ['nullable', 'string', 'max:255'], 'city' => ['nullable', 'string', 'max:128'], 'state' => ['nullable', 'string', 'max:128'], 'postal_code' => ['nullable', 'string', 'max:32'], 'country_code' => ['nullable', 'regex:/^[A-Z]{2}$/'], 'is_primary' => ['sometimes', 'boolean']]);

        return response()->json(['data' => CustomerAddress::create([...$data, 'customer_id' => $customer->id, 'company_id' => $customer->company_id])], 201);
    }

    public function storeBusinessProfile(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $data = $request->validate(['legal_name' => ['nullable', 'string', 'max:255'], 'registration_number' => ['nullable', 'string', 'max:128'], 'tax_number' => ['nullable', 'string', 'max:128'], 'industry' => ['nullable', 'string', 'max:128']]);
        CustomerBusinessProfile::updateOrCreate(['customer_id' => $customer->id], [...$data, 'company_id' => $customer->company_id]);

        return response()->json(['data' => CustomerBusinessProfile::where('customer_id', $customer->id)->first()]);
    }

    public function assignTag(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $tagId = $request->validate(['tag_id' => ['required', 'integer', 'exists:bss_tags,id']])['tag_id'];
        $tag = BssTag::findOrFail($tagId);
        abort_unless($tag->company_id === $customer->company_id, 422, 'Tag must belong to the customer company.');
        CustomerTagAssignment::firstOrCreate(['customer_id' => $customer->id, 'tag_id' => $tag->id], ['company_id' => $customer->company_id, 'created_by' => $request->user()->id]);

        return response()->noContent();
    }

    public function storeVerification(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $data = $request->validate(['kind' => ['required', 'string', 'max:64'], 'status' => ['required', 'in:pending,verified,rejected'], 'reference' => ['nullable', 'string', 'max:128'], 'reason' => ['nullable', 'string', 'max:2000']]);
        $verification = CustomerVerification::create([...$data, 'customer_id' => $customer->id, 'company_id' => $customer->company_id, 'verified_by' => $request->user()->id, 'verified_at' => $data['status'] === 'pending' ? null : now()]);
        AuditService::log('bss.customer.verification.recorded', 'success', $customer, ['customer_id' => $customer->id, 'verification_id' => $verification->id, 'kind' => $verification->kind, 'status' => $verification->status]);

        return response()->json(['data' => $verification], 201);
    }

    public function storeNote(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        return response()->json(['data' => CustomerNote::create([...$data, 'customer_id' => $customer->id, 'company_id' => $customer->company_id, 'created_by' => $request->user()->id])], 201);
    }

    public function sources(Request $request)
    {
        $this->authorize('viewAny', Customer::class);
        $companyId = $request->validate(['company_id' => ['required', 'integer', 'exists:companies,id']])['company_id'];
        abort_unless(ManagementScopeService::isInScope($request->user(), Company::findOrFail($companyId)), 403);

        return response()->json(['data' => BssSource::where('company_id', $companyId)->where('active', true)->orderBy('name')->get()]);
    }

    public function tags(Request $request)
    {
        $this->authorize('viewAny', Customer::class);
        $data = $request->validate(['company_id' => ['required', 'integer', 'exists:companies,id'], 'kind' => ['sometimes', 'in:tag,flag']]);
        abort_unless(ManagementScopeService::isInScope($request->user(), Company::findOrFail($data['company_id'])), 403);

        return response()->json(['data' => BssTag::where('company_id', $data['company_id'])->where('kind', $data['kind'] ?? 'tag')->orderBy('name')->get()]);
    }

    public function storeSource(Request $request)
    {
        $this->authorize('create', Customer::class);
        $data = $request->validate(['company_id' => ['required', 'integer', 'exists:companies,id'], 'code' => ['required', 'string', 'max:64'], 'name' => ['required', 'string', 'max:255']]);
        abort_unless(ManagementScopeService::isInScope($request->user(), Company::findOrFail($data['company_id'])), 403);

        return response()->json(['data' => BssSource::create($data)], 201);
    }

    public function storeTag(Request $request)
    {
        $this->authorize('create', Customer::class);
        $data = $request->validate(['company_id' => ['required', 'integer', 'exists:companies,id'], 'kind' => ['required', 'in:tag,flag'], 'name' => ['required', 'string', 'max:64'], 'color' => ['nullable', 'string', 'max:16']]);
        abort_unless(ManagementScopeService::isInScope($request->user(), Company::findOrFail($data['company_id'])), 403);

        return response()->json(['data' => BssTag::create($data)], 201);
    }
}
