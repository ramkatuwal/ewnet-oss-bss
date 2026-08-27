<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $companyAdmin;
    protected $regularUser;
    protected $companyA;
    protected $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $permissions = ['users.view', 'users.update', 'roles.view', 'roles.update'];
        foreach ($permissions as $perm) Permission::firstOrCreate(['name' => $perm]);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdminRole->givePermissionTo($permissions);

        $companyAdminRole = Role::firstOrCreate(['name' => 'Company Admin']);
        $companyAdminRole->givePermissionTo($permissions);

        $this->companyA = Company::factory()->create(['name' => 'Company A']);
        $this->companyB = Company::factory()->create(['name' => 'Company B']);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->companyAdmin = User::factory()->create(['company_id' => $this->companyA->id]);
        $this->companyAdmin->assignRole('Company Admin');

        $this->regularUser = User::factory()->create(['company_id' => $this->companyB->id]);
    }

    public function test_failed_login_generates_audit_event()
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ])->assertStatus(401);

        // Use latest() to get the most recent failure log from THIS request
        $log = AuditLog::where('action', 'auth.login.failure')->latest('id')->first();
        $this->assertNotNull($log, 'Failed login audit log not found');
        $this->assertEquals('failure', $log->result);
        $this->assertStringContainsString('nonexistent@example.com', $log->metadata['email']);
    }

    public function test_successful_login_generates_audit_event()
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->companyAdmin->email,
            'password' => 'password'
        ])->assertStatus(200);

        $log = AuditLog::where('action', 'auth.login.success')->latest('id')->first();
        $this->assertNotNull($log, 'Successful login audit log not found');
        $this->assertEquals('success', $log->result);
        $this->assertEquals($this->companyAdmin->id, $log->actor_id);
    }

    public function test_organization_boundary_violation_is_audited()
    {
        $this->actingAs($this->companyAdmin)->putJson("/api/v1/organization/users/{$this->regularUser->id}", [
            'name' => 'Hacked Name'
        ])->assertStatus(403);

        $log = AuditLog::where('action', 'user.update.attempt')
            ->where('result', 'failure')
            ->latest('id')
            ->first();
        $this->assertNotNull($log, 'Boundary violation audit log not found');
        $this->assertEquals($this->companyAdmin->id, $log->actor_id);
        $this->assertEquals('boundary_violation', $log->metadata['reason']);
    }

    public function test_role_assignment_generates_audit_event()
    {
        $targetUser = User::factory()->create(['company_id' => $this->companyA->id]);
        $role = Role::where('name', 'Company Admin')->first();

        $this->actingAs($this->superAdmin)->putJson("/api/v1/organization/users/{$targetUser->id}", [
            'roles' => [$role->id]
        ])->assertStatus(200);

        // Use latest() to ensure we get the log from THIS specific request
        $log = AuditLog::where('action', 'user.role.assign')
            ->where('target_id', $targetUser->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($log, 'Role assignment audit log not found');
        $this->assertEquals('success', $log->result);
        $this->assertEquals($this->superAdmin->id, $log->actor_id);
        $this->assertContains($role->name, $log->metadata['roles']);
    }

    public function test_super_admin_assignment_attempt_by_non_admin_is_audited_and_blocked()
    {
        $targetUser = User::factory()->create(['company_id' => $this->companyA->id]);
        $superAdminRole = Role::where('name', 'Super Admin')->first();

        $this->actingAs($this->companyAdmin)->putJson("/api/v1/organization/users/{$targetUser->id}", [
            'roles' => [$superAdminRole->id]
        ])->assertStatus(403);

        $log = AuditLog::where('action', 'user.role.assign.attempt')
            ->where('result', 'failure')
            ->latest('id')
            ->first();
        $this->assertNotNull($log, 'Super admin assignment attempt audit log not found');
        $this->assertEquals($this->companyAdmin->id, $log->actor_id);
        $this->assertStringContainsString('Super Admin', $log->metadata['role_name']);
    }

    public function test_audit_records_cannot_be_modified_by_unauthorized_users()
    {
        $log = AuditLog::create([
            'action' => 'test.action',
            'result' => 'success',
            'actor_type' => 'App\Models\User',
            'actor_id' => $this->superAdmin->id,
        ]);

        // Test model-level protection directly
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Audit logs are immutable and cannot be updated.');
        $log->update(['result' => 'failure']);
    }

    public function test_sensitive_credentials_are_never_written_to_audit_records()
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => $this->companyAdmin->email,
            'password' => 'password'
        ])->assertStatus(200);

        $log = AuditLog::where('action', 'auth.login.attempt')->latest('id')->first();

        $this->assertNotNull($log, 'Login attempt audit log not found');

        // CRITICAL: Verify that the 'password' key is explicitly stripped from metadata
        $this->assertArrayNotHasKey('password', $log->metadata, 'Password should never be stored in audit metadata');

        // Also verify no raw password string is accidentally serialized
        $this->assertStringNotContainsString('password', json_encode($log->metadata));
    }
}
