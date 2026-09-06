<?php

namespace Tests\Feature\Integrations;

use App\Models\Company;
use App\Models\ImportHistory;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function companyScopedUser(array $permissions): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);

        return [$user, $company];
    }

    private function history(Integration $integration, User $user, string $source, string $type = 'device'): ImportHistory
    {
        return ImportHistory::create([
            'source' => $source,
            'type' => $type,
            'integration_id' => $integration->id,
            'status' => ImportHistory::STATUS_COMPLETED,
            'started_by' => $user->id,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'total_records' => 3,
            'created_records' => 1,
            'updated_records' => 1,
            'skipped_records' => 1,
            'error_records' => 0,
        ]);
    }

    public function test_authenticated_user_can_read_history_with_pagination(): void
    {
        [$user, $company] = $this->companyScopedUser(['librenms.import']);
        $integration = Integration::factory()->forCompany($company->id)->create(['provider' => 'librenms']);
        $this->history($integration, $user, 'librenms', 'device');

        $response = $this->actingAs($user)->getJson('/api/v1/import/history');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [],
            'meta' => ['total'],
        ]);
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.source', 'librenms');
        $response->assertJsonPath('data.0.started_by_name', $user->name);
    }

    public function test_history_scoped_to_own_company(): void
    {
        [$userA, $companyA] = $this->companyScopedUser(['librenms.import']);
        [$userB, $companyB] = $this->companyScopedUser(['librenms.import']);

        $integrationA = Integration::factory()->forCompany($companyA->id)->create(['provider' => 'librenms']);
        $integrationB = Integration::factory()->forCompany($companyB->id)->create(['provider' => 'librenms']);

        $this->history($integrationA, $userA, 'librenms', 'device');
        $this->history($integrationB, $userB, 'uisp', 'site');

        $response = $this->actingAs($userA)->getJson('/api/v1/import/history');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertNotContains($integrationB->id, array_column($response->json('data'), 'integration_id'));
    }

    public function test_history_can_be_filtered_by_source_and_type(): void
    {
        [$user, $company] = $this->companyScopedUser(['librenms.import', 'integration.uisp.import']);
        $integration = Integration::factory()->forCompany($company->id)->create(['provider' => 'librenms']);

        $this->history($integration, $user, 'librenms', 'device');
        $this->history($integration, $user, 'librenms', 'site');

        $response = $this->actingAs($user)->getJson('/api/v1/import/history?source=librenms&type=site');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('site', $response->json('data.0.type'));
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/import/history');
        $response->assertStatus(401);
    }

    public function test_user_without_access_returns_403(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/import/history');
        $response->assertStatus(403);
    }

    public function test_super_admin_sees_all_history(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->givePermissionTo('logs.view');
        $superAdmin->assignRole(Role::findOrCreate('Super Admin', 'web'));

        [$userA, $companyA] = $this->companyScopedUser(['librenms.import']);
        [$userB, $companyB] = $this->companyScopedUser(['librenms.import']);

        $integrationA = Integration::factory()->forCompany($companyA->id)->create(['provider' => 'librenms']);
        $integrationB = Integration::factory()->forCompany($companyB->id)->create(['provider' => 'librenms']);

        $this->history($integrationA, $userA, 'librenms', 'device');
        $this->history($integrationB, $userB, 'uisp', 'device');

        $response = $this->actingAs($superAdmin)->getJson('/api/v1/import/history');

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('meta.total'));
    }
}
