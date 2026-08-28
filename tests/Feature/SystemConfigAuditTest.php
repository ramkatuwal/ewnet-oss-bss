<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class SystemConfigAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_configuration_update_creates_audit_record()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
            'branding' => ['app_name' => 'EWNET AUDIT TEST']
        ]);

        $response->assertStatus(200);

        // Verify audit log was created
        $auditLog = AuditLog::where('action', 'system.configuration.update')
            ->where('result', 'success')
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('success', $auditLog->result);
        $this->assertEquals($user->id, $auditLog->actor_id);
    }

    public function test_audit_metadata_contains_changed_keys()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
            'branding' => ['app_name' => 'EWNET METADATA TEST'],
            'theme' => ['compactness' => 'comfortable']
        ]);

        $response->assertStatus(200);

        $auditLog = AuditLog::where('action', 'system.configuration.update')
            ->where('result', 'success')
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertArrayHasKey('updated_keys', $auditLog->metadata);
        $this->assertContains('app_name', $auditLog->metadata['updated_keys']);
        $this->assertContains('compactness', $auditLog->metadata['updated_keys']);
    }

    public function test_sensitive_values_are_not_logged()
    {
        $user = User::factory()->create();
        $role = Role::where('name', 'Super Admin')->first();
        $user->assignRole($role);

        $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
            'branding' => ['app_name' => 'EWNET SECURE TEST']
        ]);

        $response->assertStatus(200);

        $auditLog = AuditLog::where('action', 'system.configuration.update')
            ->where('result', 'success')
            ->latest()
            ->first();

        $this->assertNotNull($auditLog);

        $metadata = json_encode($auditLog->metadata);
        $sensitivePatterns = [
            'APP_KEY',
            'password',
            'token',
            'secret',
            'credential',
            'private_key'
        ];

        foreach ($sensitivePatterns as $pattern) {
            $this->assertStringNotContainsString(strtolower($pattern), strtolower($metadata));
        }

        // Ensure actual app_name value is NOT in metadata (only keys)
        $this->assertStringNotContainsString('EWNET SECURE TEST', $metadata);
    }

    public function test_unauthorized_update_does_not_create_successful_audit_event()
    {
        $user = User::factory()->create();

        // User does NOT have system.config.manage permission
        $response = $this->actingAs($user)->putJson('/api/v1/system/configuration', [
            'branding' => ['app_name' => 'UNAUTHORIZED TEST']
        ]);

        $response->assertStatus(403);

        // Verify no successful audit log was created
        $auditLog = AuditLog::where('action', 'system.configuration.update')
            ->where('result', 'success')
            ->where('metadata->updated_keys', 'LIKE', '%UNAUTHORIZED%')
            ->first();

        $this->assertNull($auditLog);
    }
}
