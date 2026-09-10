<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Service;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BssFoundationTest extends TestCase
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
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    private function customer(Company $company, array $attributes = []): Customer
    {
        return Customer::create(['company_id' => $company->id, 'customer_code' => 'CUS-001', 'name' => 'Acme', 'type' => 'organization', ...$attributes]);
    }

    private function service(Company $company, array $attributes = []): Service
    {
        return Service::create(['company_id' => $company->id, 'service_code' => 'INT-001', 'name' => 'Internet', 'type' => 'internet', ...$attributes]);
    }

    public function test_customer_and_service_are_company_scoped_and_codes_are_unique_per_live_company(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->user($company, ['bss.customers.create', 'bss.services.create', 'bss.customers.view']);
        $customer = $this->actingAs($user)->postJson('/api/v1/bss/customers', ['company_id' => $company->id, 'customer_code' => 'CUS-001', 'name' => 'Acme', 'type' => 'organization'])->assertCreated()->json('data');
        $this->actingAs($user)->postJson('/api/v1/bss/customers', ['company_id' => $company->id, 'customer_code' => 'CUS-001', 'name' => 'Duplicate', 'type' => 'organization'])->assertUnprocessable();
        $this->actingAs($user)->postJson('/api/v1/bss/services', ['company_id' => $other->id, 'service_code' => 'INT-001', 'name' => 'Foreign', 'type' => 'internet'])->assertUnprocessable()->assertJsonValidationErrors('company_id');
        $this->actingAs($user)->getJson('/api/v1/bss/customers')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $customer['id']);
    }

    public function test_customer_service_requires_same_company_and_transition_is_deterministic(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->user($company, ['bss.customers.view', 'bss.customer-services.create', 'bss.customer-services.update']);
        $customer = $this->customer($company);
        $foreignService = $this->service($other);
        $this->actingAs($user)->postJson("/api/v1/bss/customers/{$customer->id}/services", ['service_id' => $foreignService->id])->assertUnprocessable();
        $service = $this->service($company);
        $id = $this->actingAs($user)->postJson("/api/v1/bss/customers/{$customer->id}/services", ['service_id' => $service->id])->assertCreated()->json('data.id');
        $this->actingAs($user)->postJson("/api/v1/bss/customer-services/{$id}/transition", ['status' => 'suspended'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->actingAs($user)->postJson("/api/v1/bss/customer-services/{$id}/transition", ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->actingAs($user)->postJson("/api/v1/bss/customer-services/{$id}/transition", ['status' => 'terminated'])->assertOk()->assertJsonPath('data.status', 'terminated');
    }

    public function test_customer_retirement_returns_conflict_for_live_customer_service_and_audits_safe_fields(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, ['bss.customers.retire', 'bss.customer-services.create', 'bss.customers.view']);
        $customer = $this->customer($company);
        $service = $this->service($company);
        CustomerService::create(['company_id' => $company->id, 'customer_id' => $customer->id, 'service_id' => $service->id, 'status' => 'active']);
        $this->actingAs($user)->deleteJson("/api/v1/bss/customers/{$customer->id}")->assertConflict();
        CustomerService::where('customer_id', $customer->id)->update(['status' => 'terminated']);
        $this->actingAs($user)->deleteJson("/api/v1/bss/customers/{$customer->id}")->assertOk();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $audit = AuditLog::where('action', 'bss.customer.retired')->firstOrFail();
        $keys = array_keys($audit->metadata);
        sort($keys);
        $this->assertSame(['company_id', 'customer_code', 'customer_id', 'status'], $keys);
    }

    public function test_database_rejects_cross_company_relation_and_history_restore(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $customer = $this->customer($company);
        $service = $this->service($other);
        try {
            DB::transaction(fn () => DB::table('customer_services')->insert(['company_id' => $company->id, 'customer_id' => $customer->id, 'service_id' => $service->id, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
            $this->fail('Cross-company customer service was accepted.');
        } catch (QueryException $e) {
            $this->assertSame('23503', $e->errorInfo[0]);
        }
        $customer->delete();
        $this->expectException(QueryException::class);
        DB::table('customers')->where('id', $customer->id)->update(['deleted_at' => null]);
    }
}
