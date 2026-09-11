<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\FeasibilityCheck;
use App\Models\Lead;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BssFeasibilityTest extends TestCase
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

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        return $user;
    }

    private function qualifiedLead(Company $company, array $overrides = []): Lead
    {
        return Lead::create(array_merge(['company_id' => $company->id, 'lead_code' => 'LEAD-'.strtoupper(bin2hex(random_bytes(4))), 'name' => 'Prospect', 'status' => 'qualified'], $overrides));
    }

    private function feasibilityPayload(array $overrides = []): array
    {
        return array_merge(['requested_service_summary' => '100 Mbps FTTH broadband connection', 'requested_location_summary' => 'Baneshwor, Kathmandu', 'requested_location_lat' => 27.7001, 'requested_location_lng' => 85.3301], $overrides);
    }

    public function test_qualified_lead_feasibility_creation_is_scoped_and_syncs_lead(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.leads.view', 'bss.feasibility.create', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $leadId = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->assertCreated()->json('data');

        $this->assertStringStartsWith('FC-', $leadId['feasibility_code']);
        $this->assertSame('requested', $leadId['status']);
        $this->assertSame($company->id, $leadId['company_id']);
        $this->assertSame('feasibility_pending', $lead->fresh()->status);
        $this->assertDatabaseHas('feasibility_lifecycle_history', ['to_status' => 'requested']);
        $this->assertDatabaseHas('lead_lifecycle_history', ['to_status' => 'feasibility_pending']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bss.feasibility.created']);
    }

    public function test_feasibility_creation_requires_permission(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.leads.view']);
        $lead = $this->qualifiedLead($company);
        $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->assertForbidden();
    }

    public function test_feasibility_creation_rejects_non_qualified_leads(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create']);
        $lead = Lead::create(['company_id' => $company->id, 'lead_code' => 'LEAD-NEW', 'name' => 'Open', 'status' => 'new']);
        $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->assertUnprocessable();
    }

    public function test_cross_company_lead_is_denied(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create']);
        $lead = $this->qualifiedLead($other);
        $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->assertUnprocessable();
    }

    public function test_cross_company_assessor_assign_is_denied(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.assign', 'bss.feasibility.view']);
        $foreign = User::factory()->create(['company_id' => $other->id]);
        $lead = $this->qualifiedLead($company);
        $feasibility = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data');

        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$feasibility['id']}/assign", ['assigned_assessor_user_id' => $foreign->id])->assertUnprocessable();
    }

    public function test_full_lifecycle_requested_to_feasible(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.update', 'bss.feasibility.assign', 'bss.feasibility.survey.update', 'bss.feasibility.decide', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');

        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/start-assessment")->assertOk()->assertJsonPath('data.status', 'reviewing');
        $survey = $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/start-survey", ['scheduled_at' => '2026-09-20 10:00:00'])->assertOk()->json('data');
        $this->assertSame('survey_scheduled', $this->actingAs($user)->getJson("/api/v1/bss/feasibility-checks/{$id}")->json('data.status'));
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/complete-survey", ['location_verified' => true, 'civil_work_required' => false, 'findings' => 'Line of sight available', 'recommended_outcome' => 'feasible'])->assertOk();
        $this->assertSame('surveyed', $this->actingAs($user)->getJson("/api/v1/bss/feasibility-checks/{$id}")->json('data.status'));

        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/decide", ['outcome' => 'feasible', 'valid_until' => '2026-12-31', 'conditions_summary' => 'None'])->assertOk()->assertJsonPath('data.status', 'feasible');
        $this->assertSame('feasible', $lead->fresh()->status);
        $this->assertDatabaseHas('feasibility_lifecycle_history', ['feasibility_check_id' => $id, 'to_status' => 'surveyed']);

        $check = FeasibilityCheck::findOrFail($id);
        $this->assertSame($survey['id'], $check->survey->id);
        $this->assertTrue($check->survey->location_verified);
        $this->assertSame('completed', $check->survey->status);
    }

    public function test_direct_outcome_from_reviewing(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.update', 'bss.feasibility.decide', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/start-assessment")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/decide", ['outcome' => 'not_feasible'])->assertOk()->assertJsonPath('data.status', 'not_feasible');
        $this->assertSame('not_feasible', $lead->fresh()->status);
    }

    public function test_outcome_cannot_skip_transition_stages(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.decide', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/decide", ['outcome' => 'feasible'])->assertUnprocessable();
        $this->assertSame('feasibility_pending', $lead->fresh()->status);
    }

    public function test_conditions_lifecycle(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.update', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');
        $condition = $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/conditions", ['condition_type' => 'pole_permission', 'description' => 'Require municipal approval for pole attachment', 'is_mandatory' => true])->assertCreated()->json('data');
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-conditions/{$condition['id']}/resolve", ['status' => 'resolved', 'resolution_notes' => 'Approval received'])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->actingAs($user)->getJson("/api/v1/bss/feasibility-checks/{$id}/conditions")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_evidence_reference_same_company_allowed_cross_company_denied(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.evidence.create', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');

        $site = Site::factory()->create(['company_id' => $company->id]);
        $foreignSite = Site::factory()->create(['company_id' => $other->id]);

        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/evidence", ['evidence_type' => 'site_observation', 'referenced_entity_type' => 'Site', 'referenced_entity_id' => $foreignSite->id, 'observation_summary' => 'Foreign'])->assertUnprocessable();
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/evidence", ['evidence_type' => 'site_observation', 'referenced_entity_type' => 'Site', 'referenced_entity_id' => $site->id, 'observation_summary' => 'Site reachable'])->assertCreated();
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/evidence", ['evidence_type' => 'manual_observation', 'observation_summary' => 'No reference required'])->assertCreated();
        $this->actingAs($user)->getJson("/api/v1/bss/feasibility-checks/{$id}/evidence")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_evidence_reference_type_with_missing_id_is_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.evidence.create']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/evidence", ['evidence_type' => 'site_observation', 'referenced_entity_type' => 'Site', 'observation_summary' => 'Missing id'])->assertUnprocessable();
    }

    public function test_confirmation_requires_feasible_outcome_and_is_single(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.update', 'bss.feasibility.decide', 'bss.feasibility.survey.update', 'bss.feasibility.evidence.create', 'bss.confirmation.create', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');

        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/confirmations", ['channel' => 'phone', 'presented_summary' => '100 Mbps for NPR 4000'])->assertUnprocessable();

        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/start-assessment")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/decide", ['outcome' => 'feasible', 'valid_until' => '2026-12-31'])->assertOk();
        $confirmation = $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/confirmations", ['channel' => 'phone', 'presented_summary' => '100 Mbps for NPR 4000'])->assertCreated()->json('data');
        $this->assertStringStartsWith('CONF-', $confirmation['reference_code']);
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/confirmations", ['channel' => 'email', 'presented_summary' => 'Again'])->assertUnprocessable();
    }

    public function test_confirmation_confirm_and_decline_sync_lead(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.update', 'bss.feasibility.decide', 'bss.confirmation.create', 'bss.confirmation.confirm', 'bss.confirmation.decline', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/start-assessment")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/decide", ['outcome' => 'feasible', 'valid_until' => '2026-12-31'])->assertOk();
        $confirmationId = $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/confirmations", ['channel' => 'phone', 'presented_summary' => 'Offer'])->json('data.id');

        $this->assertSame('feasible', $lead->fresh()->status);
        $this->actingAs($user)->postJson("/api/v1/bss/confirmations/{$confirmationId}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertSame('confirmed', $lead->fresh()->status);
    }

    public function test_confirmation_decline_syncs_lead_lost(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.update', 'bss.feasibility.decide', 'bss.confirmation.create', 'bss.confirmation.confirm', 'bss.confirmation.decline', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/start-assessment")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/decide", ['outcome' => 'feasible', 'valid_until' => '2026-12-31'])->assertOk();
        $confirmationId = $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/confirmations", ['channel' => 'email', 'presented_summary' => 'Offer'])->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/bss/confirmations/{$confirmationId}/decline")->assertOk()->assertJsonPath('data.status', 'declined');
        $this->assertSame('lost', $lead->fresh()->status);
    }

    public function test_expired_feasibility_cannot_be_confirmed(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.update', 'bss.feasibility.decide', 'bss.confirmation.create', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/start-assessment")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/decide", ['outcome' => 'feasible', 'valid_until' => now()->subDay()->toDateString()])->assertUnprocessable();
    }

    public function test_duplicate_active_feasibility_for_lead_is_rejected(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->assertUnprocessable();
        $this->assertSame('feasibility_pending', $lead->fresh()->status);
    }

    public function test_terminal_feasibility_cannot_be_edited_or_assigned(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.update', 'bss.feasibility.assign', 'bss.feasibility.decide', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/start-assessment")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/decide", ['outcome' => 'not_feasible'])->assertOk();
        $this->actingAs($user)->patchJson("/api/v1/bss/feasibility-checks/{$id}", ['requested_service_summary' => 'Changed'])->assertUnprocessable();
        $this->actingAs($user)->postJson("/api/v1/bss/feasibility-checks/{$id}/assign", ['assigned_assessor_user_id' => $user->id])->assertUnprocessable();
    }

    public function test_cross_company_view_is_denied_and_index_is_scoped(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $owner = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.view']);
        $foreign = $this->user($other, ['bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $id = $this->actingAs($owner)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->json('data.id');

        $this->actingAs($foreign)->getJson("/api/v1/bss/feasibility-checks/{$id}")->assertForbidden();
        $this->actingAs($foreign)->getJson('/api/v1/bss/feasibility-checks')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner)->getJson('/api/v1/bss/feasibility-checks')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_super_admin_sees_all_companies(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $owner = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.view']);
        $admin = $this->superAdmin();
        $admin->givePermissionTo('bss.feasibility.view');
        $lead = $this->qualifiedLead($company);
        $this->actingAs($owner)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->assertCreated();
        $otherLead = $this->qualifiedLead($other);
        $this->actingAs($admin)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $otherLead->id]))->assertCreated();
        $this->actingAs($admin)->getJson('/api/v1/bss/feasibility-checks')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_read_only_view_cannot_create_or_decide(): void
    {
        $company = Company::factory()->create();
        $viewer = $this->user($company, ['bss.feasibility.view', 'bss.confirmation.view']);
        $lead = $this->qualifiedLead($company);
        $this->actingAs($viewer)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->assertForbidden();
    }

    public function test_customer_direct_feasibility_cross_company_address_rejected(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create']);
        $customer = Customer::create(['company_id' => $company->id, 'customer_code' => 'CUS-1', 'name' => 'Customer', 'type' => 'individual']);
        $foreignCustomer = Customer::create(['company_id' => $other->id, 'customer_code' => 'CUS-2', 'name' => 'Other', 'type' => 'individual']);
        $foreignAddressId = DB::table('customer_addresses')->insertGetId(['customer_id' => $foreignCustomer->id, 'company_id' => $other->id, 'kind' => 'billing', 'line1' => 'Foreign']);

        $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['customer_id' => $customer->id, 'company_id' => $company->id, 'customer_address_id' => $foreignAddressId]))->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['customer_id' => $customer->id, 'company_id' => $company->id]))->assertCreated();
    }

    public function test_feasibility_index_avoids_n_plus_one(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.view']);
        foreach (range(1, 3) as $i) {
            $lead = $this->qualifiedLead($company);
            $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id, 'requested_service_summary' => "Service {$i}"]))->assertCreated();
        }

        DB::enableQueryLog();
        $this->actingAs($user)->getJson('/api/v1/bss/feasibility-checks')->assertOk()->assertJsonCount(3, 'data');
        $this->assertLessThan(20, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }

    public function test_feasibility_only_touches_bss_tables(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.feasibility.create', 'bss.feasibility.view']);
        $lead = $this->qualifiedLead($company);
        $this->actingAs($user)->postJson('/api/v1/bss/feasibility-checks', $this->feasibilityPayload(['lead_id' => $lead->id]))->assertCreated();

        $this->assertDatabaseCount('network_ports', 0);
        $this->assertDatabaseHas('feasibility_checks', ['lead_id' => $lead->id]);
        $this->assertDatabaseMissing('feasibility_checks', ['id' => 99999]);
    }
}
