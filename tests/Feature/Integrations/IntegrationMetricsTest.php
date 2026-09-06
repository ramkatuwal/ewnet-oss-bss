<?php

namespace Tests\Feature\Integrations;

use App\Http\Resources\V1\IntegrationResource;
use App\Models\Company;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\IntegrationSync;
use App\Models\Site;
use App\Models\SiteExternalReference;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationMetricsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        IntegrationResource::resetObjectCountCache();

        $this->company = Company::factory()->create();

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->user->givePermissionTo([
            'integrations.view',
            'integrations.sync',
            'integrations.test',
            'logs.view',
            'integrations.credentials.manage',
            'integration.uisp.import',
        ]);

        $this->viewer = User::factory()->create(['company_id' => $this->company->id]);
        $this->viewer->givePermissionTo('integrations.view');
    }

    private function integration(): Integration
    {
        return Integration::factory()->forCompany($this->company->id)->create([
            'provider' => 'uisp',
            'enabled' => true,
            'configuration' => [
                'api_url' => 'https://uisp.example.test/api',
                'token' => 'super-secret-token',
            ],
        ]);
    }

    public function test_resource_exposes_scope_health_counts_and_redacted_config(): void
    {
        $integration = $this->integration();

        $site = Site::create([
            'site_code' => 'KTM-POP',
            'name' => 'Kathmandu POP',
            'type' => 'pop',
            'status' => 'active',
            'company_id' => $this->company->id,
        ]);
        SiteExternalReference::create([
            'site_id' => $site->id,
            'provider' => 'uisp',
            'external_type' => 'site',
            'external_id' => 'site-1',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/integrations/{$integration->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.company_scope', 'company');
        $response->assertJsonPath('data.provider_type', 'uisp');
        $response->assertJsonPath('data.health_status', 'connected');
        $response->assertJsonPath('data.active_objects_count', 1);
        $response->assertJsonPath('data.configuration.token', '********');
        $response->assertJsonPath('data.configuration.api_url', 'https://uisp.example.test/api');
        $response->assertJsonPath('data.can.view', true);
        $response->assertJsonPath('data.can.sync', true);
        $response->assertJsonPath('data.can.manage_credentials', true);
    }

    public function test_stats_endpoint_aggregates_sync_metrics(): void
    {
        $integration = $this->integration();

        $sync = IntegrationSync::create([
            'integration_id' => $integration->id,
            'operation' => 'full',
            'status' => 'pending',
            'initiated_by' => $this->user->id,
        ]);
        $sync->markRunning();
        $sync->markCompleted([
            'records_processed' => 10,
            'records_created' => 7,
            'records_updated' => 2,
            'records_skipped' => 1,
            'records_failed' => 0,
        ]);

        $failed = IntegrationSync::create([
            'integration_id' => $integration->id,
            'operation' => 'incremental',
            'status' => 'pending',
            'initiated_by' => $this->user->id,
        ]);
        $failed->markRunning();
        $failed->markFailed('boom');
        $failed->update(['started_at' => now()->subMinutes(2), 'finished_at' => now()->addSecond()]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/integrations/{$integration->id}/stats");

        $response->assertStatus(200);
        $response->assertJsonPath('data.syncs_total', 2);
        $response->assertJsonPath('data.syncs_completed', 1);
        $response->assertJsonPath('data.syncs_failed', 1);
        $response->assertJsonPath('data.success_rate', 50);
        $response->assertJsonPath('data.records_created_total', 7);
        $response->assertJsonPath('data.records_skipped_total', 1);
        $response->assertJsonPath('data.records_failed_total', 0);
        $response->assertJsonPath('data.last_sync_status', 'failed');
        $response->assertJsonPath('data.last_error_summary', 'boom');

        $payload = json_decode($response->getContent(), true)['data'];
        $this->assertIsInt($payload['last_sync_duration_seconds']);
        $this->assertIsInt($payload['total_objects']);
    }

    public function test_audit_logs_require_logs_view_permission(): void
    {
        $integration = $this->integration();

        AuditService::log('integration.sync_completed', 'success', $integration, [
            'sync_id' => 123,
            'processed' => 4,
        ]);

        $this->actingAs($this->viewer)
            ->getJson("/api/v1/integrations/{$integration->id}/audit-logs")
            ->assertStatus(403);

        $unrelated = User::factory()->create();
        $this->actingAs($unrelated)
            ->getJson("/api/v1/integrations/{$integration->id}/audit-logs")
            ->assertStatus(403);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/integrations/{$integration->id}/audit-logs");

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.action', 'integration.sync_completed');
        $response->assertJsonPath('data.0.result', 'success');

        $this->assertDatabaseHas('audit_logs', [
            'target_id' => $integration->id,
            'action' => 'integration.sync_completed',
        ]);
    }

    public function test_credential_rotation_deactivates_old_and_creates_new(): void
    {
        $integration = $this->integration();

        $credential = new IntegrationCredential([
            'integration_id' => $integration->id,
            'credential_type' => 'api_token',
            'label' => 'Primary',
            'is_active' => true,
        ]);
        $credential->setSecretValue('old-token');
        $credential->save();

        $oldId = $credential->id;

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/integrations/{$integration->id}/credentials/{$oldId}/rotate", [
                'value' => 'new-token',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('integration_credentials', [
            'id' => $oldId,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('integration_credentials', [
            'credential_type' => 'api_token',
            'label' => 'Primary',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'target_id' => $integration->id,
            'action' => 'integration.credential_rotated',
        ]);

        $newCred = IntegrationCredential::where('integration_id', $integration->id)
            ->where('is_active', true)
            ->first();
        $this->assertNotNull($newCred);
        $this->assertSame($newCred->getSecretValue(), 'new-token');
        $this->assertNotSame($newCred->encrypted_value, 'new-token');
    }

    public function test_rotate_rejects_missing_value_and_foreign_integration(): void
    {
        $integration = $this->integration();

        $other = Integration::factory()->create(['provider' => 'uisp', 'enabled' => true]);
        $foreignCredential = new IntegrationCredential([
            'integration_id' => $other->id,
            'credential_type' => 'api_token',
            'is_active' => true,
        ]);
        $foreignCredential->setSecretValue('foreign');
        $foreignCredential->save();

        $this->actingAs($this->user)
            ->postJson("/api/v1/integrations/{$integration->id}/credentials/{$foreignCredential->id}/rotate", [
                'value' => 'x',
            ])->assertStatus(404);

        $this->actingAs($this->user)
            ->postJson("/api/v1/integrations/{$integration->id}/credentials/{$foreignCredential->id}/rotate", [])
            ->assertStatus(404);
    }
}
