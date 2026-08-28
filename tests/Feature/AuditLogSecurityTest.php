<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $companyManager;
    protected User $otherCompanyUser;
    protected Company $company1;
    protected Company $company2;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any existing audit logs
        AuditLog::query()->delete();

        $perm = Permission::firstOrCreate(['name' => 'system.debug.view', 'guard_name' => 'web']);

        $superRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superRole->syncPermissions([$perm]);

        $managerRole = Role::firstOrCreate(['name' => 'AuditManager', 'guard_name' => 'web']);
        $managerRole->syncPermissions([$perm]);

        $this->company1 = Company::factory()->create();
        $this->company2 = Company::factory()->create();

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->companyManager = User::factory()->create(['company_id' => $this->company1->id]);
        $this->companyManager->assignRole('AuditManager');

        $this->otherCompanyUser = User::factory()->create(['company_id' => $this->company2->id]);

        // Create audit logs for testing
        AuditLog::create([
            'actor_type' => User::class,
            'actor_id' => $this->companyManager->id,
            'action' => 'test.action',
            'result' => 'success',
            'organization_context' => ['company_id' => $this->company1->id],
        ]);

        AuditLog::create([
            'actor_type' => User::class,
            'actor_id' => $this->otherCompanyUser->id,
            'action' => 'test.action',
            'result' => 'success',
            'organization_context' => ['company_id' => $this->company2->id],
        ]);
    }

    public function test_unauthorized_user_cannot_access_audit_logs(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->getJson('/api/v1/security/audit-logs')
            ->assertStatus(403);
    }

    public function test_authorized_user_can_access_audit_logs(): void
    {
        $this->actingAs($this->companyManager)
            ->getJson('/api/v1/security/audit-logs')
            ->assertStatus(200);
    }

    public function test_super_admin_can_see_all_audit_logs(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/security/audit-logs?per_page=100')
            ->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_scoped_user_only_sees_own_scope_logs(): void
    {
        $response = $this->actingAs($this->companyManager)
            ->getJson('/api/v1/security/audit-logs')
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_audit_log_detail_respects_scope(): void
    {
        $otherLog = AuditLog::where('actor_id', $this->otherCompanyUser->id)->first();

        $this->actingAs($this->companyManager)
            ->getJson("/api/v1/security/audit-logs/{$otherLog->id}")
            ->assertStatus(404);
    }

    public function test_audit_log_detail_excludes_sensitive_metadata(): void
    {
        $log = AuditLog::create([
            'actor_type' => User::class,
            'actor_id' => $this->superAdmin->id,
            'action' => 'test.sensitive',
            'result' => 'success',
            'metadata' => [
                'password' => 'secret123',
                'token' => 'abc123',
                'safe_field' => 'visible',
            ],
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/v1/security/audit-logs/{$log->id}")
            ->assertStatus(200);

        $metadata = $response->json('data.metadata');
        $this->assertArrayNotHasKey('password', $metadata);
        $this->assertArrayNotHasKey('token', $metadata);
        $this->assertArrayHasKey('safe_field', $metadata);
    }

    public function test_audit_logs_are_paginated(): void
    {
        // Clear existing logs first
        AuditLog::query()->delete();
        
        for ($i = 0; $i < 30; $i++) {
            AuditLog::create([
                'actor_type' => User::class,
                'actor_id' => $this->superAdmin->id,
                'action' => 'test.pagination',
                'result' => 'success',
            ]);
        }

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/security/audit-logs?per_page=10')
            ->assertStatus(200);

        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(30, $response->json('meta.total'));
    }

    public function test_audit_logs_can_be_filtered_by_action(): void
    {
        AuditLog::create([
            'actor_type' => User::class,
            'actor_id' => $this->superAdmin->id,
            'action' => 'unique.action',
            'result' => 'success',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/security/audit-logs?action=unique.action&per_page=100')
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_audit_logs_can_be_searched(): void
    {
        AuditLog::create([
            'actor_type' => User::class,
            'actor_id' => $this->superAdmin->id,
            'action' => 'searchable.action',
            'result' => 'success',
            'ip_address' => '192.168.1.100',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/security/audit-logs?search=192.168.1.100&per_page=100')
            ->assertStatus(200);

        $this->assertCount(1, $response->json('data'));
    }
}
