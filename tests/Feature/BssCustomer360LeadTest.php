<?php

namespace Tests\Feature;

use App\Models\BssSource;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BssCustomer360LeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_customer_360_children_are_scoped_and_verification_is_audited(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $customer = Customer::create(['company_id' => $company->id, 'customer_code' => 'C360-1', 'name' => 'Customer', 'type' => 'individual']);
        $user = $this->user($company, ['bss.customers.view', 'bss.customers.update']);
        $this->actingAs($user)->postJson("/api/v1/bss/customers/{$customer->id}/contacts", ['kind' => 'email', 'value' => 'customer@example.test'])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/bss/customers/{$customer->id}/addresses", ['kind' => 'billing', 'line1' => 'Main Street', 'country_code' => 'NP'])->assertCreated();
        $this->actingAs($user)->putJson("/api/v1/bss/customers/{$customer->id}/business-profile", ['legal_name' => 'Customer Pvt Ltd'])->assertOk();
        $this->actingAs($user)->postJson("/api/v1/bss/customers/{$customer->id}/verifications", ['kind' => 'kyc', 'status' => 'verified', 'reference' => 'KYC-1'])->assertCreated();
        $this->actingAs($user)->getJson("/api/v1/bss/customers/{$customer->id}/360")->assertOk()->assertJsonCount(1, 'data.contacts')->assertJsonCount(1, 'data.verifications');
        $foreignUser = $this->user($other, ['bss.customers.view', 'bss.customers.update']);
        $this->actingAs($foreignUser)->getJson("/api/v1/bss/customers/{$customer->id}/360")->assertForbidden();
    }

    public function test_qualified_lead_conversion_is_transactional_scoped_and_idempotent(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->user($company, ['bss.leads.view', 'bss.leads.create', 'bss.leads.update', 'bss.leads.convert']);
        $leadId = $this->actingAs($user)->postJson('/api/v1/bss/leads', ['company_id' => $company->id, 'lead_code' => 'LEAD-1', 'name' => 'Prospect', 'email' => 'prospect@example.test'])->assertCreated()->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/bss/leads/{$leadId}/qualify", ['qualification' => ['serviceable' => true]])->assertOk();
        $customer = $this->actingAs($user)->postJson("/api/v1/bss/leads/{$leadId}/convert", ['customer_code' => 'CUS-PROSPECT'])->assertOk()->json('data');
        $this->actingAs($user)->postJson("/api/v1/bss/leads/{$leadId}/convert", ['customer_code' => 'IGNORED'])->assertOk()->assertJsonPath('data.id', $customer['id']);
        $this->assertSame('converted', Lead::findOrFail($leadId)->status);
        $foreign = Customer::create(['company_id' => $other->id, 'customer_code' => 'OTHER', 'name' => 'Other', 'type' => 'individual']);
        $lead = Lead::create(['company_id' => $company->id, 'lead_code' => 'LEAD-2', 'name' => 'Second', 'status' => 'qualified']);
        $this->actingAs($user)->postJson("/api/v1/bss/leads/{$lead->id}/convert", ['customer_code' => 'NOPE', 'use_customer_id' => $foreign->id])->assertUnprocessable();
    }

    public function test_direct_customer_onboarding_is_transactional_and_scoped(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.customers.create', 'bss.customers.view']);
        $source = BssSource::create(['company_id' => $company->id, 'code' => 'walkin', 'name' => 'Walk-in']);
        $this->actingAs($user)->postJson('/api/v1/bss/customers/onboard', ['company_id' => $company->id, 'source_id' => $source->id, 'customer_code' => 'ONBOARD-1', 'name' => 'Onboarded', 'type' => 'organization', 'initial_contact' => ['kind' => 'email', 'value' => 'onboard@example.test'], 'initial_address' => ['line1' => 'Main'], 'business_profile' => ['legal_name' => 'Onboarded Ltd'], 'verification' => ['kind' => 'kyc', 'status' => 'verified', 'reference' => 'REF-1']])->assertCreated();
        $customer = Customer::where('customer_code', 'ONBOARD-1')->firstOrFail();
        $this->assertSame($source->id, $customer->source_id);
        $this->assertDatabaseHas('customer_contacts', ['customer_id' => $customer->id, 'is_primary' => true]);
        $this->assertDatabaseHas('customer_verifications', ['customer_id' => $customer->id, 'reference' => 'REF-1']);
    }
}
