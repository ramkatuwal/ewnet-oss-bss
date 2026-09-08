<?php

namespace Tests\Feature\Fim;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\Site;
use App\Models\SplitterBranch;
use App\Models\SplitterProfile;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SplitterBranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('ewnet_test', DB::connection()->getDatabaseName());
        $this->seed();
    }

    /**
     * Create site, INFRASTRUCTURE/SPLITTER asset with company_id, NCP, and two
     * splitter_ports (input + output) via PassiveOpticalPort::factory.
     *
     * @return array{0: Asset, 1: NetworkConnectionPoint, 2: PassiveOpticalPort, 3: PassiveOpticalPort}
     */
    protected function endpoint(Company $company): array
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'SPLITTER',
        ]);
        $point = NetworkConnectionPoint::factory()->create([
            'site_id' => $site->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
        ]);

        $inputPort = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $point->id,
            'port_role' => 'splitter_input',
        ]);

        $outputPort = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $point->id,
            'port_role' => 'splitter_output',
        ]);

        return [$asset, $point, $inputPort, $outputPort];
    }

    protected function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);
        $this->assertFalse($user->hasRole('Super Admin'));

        return $user->refresh();
    }

    protected function permissions(): array
    {
        return [
            'fim.splitter-branches.view',
            'fim.splitter-branches.create',
            'fim.splitter-branches.delete',
            'fim.passive-optical-ports.create',
            'fim.passive-optical-ports.view',
            'assets.view',
            'fim.connection-points.view',
            'fim.splitter-profiles.view',
            'fim.splitter-profiles.create',
        ];
    }

    protected function profile(Asset $asset): SplitterProfile
    {
        return SplitterProfile::create([
            'asset_id' => $asset->id,
            'company_id' => $asset->company_id,
            'input_port_count' => 1,
            'output_port_count' => 2,
        ])->refresh();
    }

    protected function branch(SplitterProfile $profile, PassiveOpticalPort $input, PassiveOpticalPort $output): SplitterBranch
    {
        return SplitterBranch::create([
            'splitter_profile_id' => $profile->id,
            'asset_id' => $profile->asset_id,
            'company_id' => $profile->company_id,
            'input_port_id' => $input->id,
            'output_port_id' => $output->id,
        ])->refresh();
    }

    protected function assertDatabaseRejected(callable $write, string $sqlState = '23514'): void
    {
        try {
            DB::transaction($write);
            $this->fail('Expected PostgreSQL to reject the contract violation.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        }
    }

    public function test_valid_input_output_branch(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
                'input_port_id' => $inputPort->id,
                'output_port_id' => $outputPort->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.splitter_profile_id', $profile->id)
            ->assertJsonPath('data.asset_id', $asset->id)
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.input_port_id', $inputPort->id)
            ->assertJsonPath('data.output_port_id', $outputPort->id)
            ->assertJsonPath('data.created_by', $user->id)
            ->assertJsonStructure([
                'data' => ['id', 'splitter_profile_id', 'asset_id', 'company_id', 'input_port_id', 'output_port_id', 'created_by', 'created_at'],
            ]);

        $this->assertDatabaseHas('splitter_branches', [
            'splitter_profile_id' => $profile->id,
            'input_port_id' => $inputPort->id,
            'output_port_id' => $outputPort->id,
        ]);
    }

    public function test_one_input_to_multiple_outputs(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPortA] = $this->endpoint($company);
        $outputPortB = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $point->id,
            'port_role' => 'splitter_output',
        ]);
        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        $acting = $this->actingAs($user, 'sanctum');

        $acting->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
            'input_port_id' => $inputPort->id,
            'output_port_id' => $outputPortA->id,
        ])->assertCreated();

        $acting->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
            'input_port_id' => $inputPort->id,
            'output_port_id' => $outputPortB->id,
        ])->assertCreated();

        $this->assertDatabaseCount('splitter_branches', 2);
    }

    public function test_multiple_inputs_supported(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPortA, $outputPortA] = $this->endpoint($company);

        $inputPortB = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $point->id,
            'port_role' => 'splitter_input',
        ]);
        $outputPortB = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $point->id,
            'port_role' => 'splitter_output',
        ]);

        $profile = SplitterProfile::create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'input_port_count' => 2,
            'output_port_count' => 2,
        ])->refresh();

        $user = $this->user($company, $this->permissions());
        $acting = $this->actingAs($user, 'sanctum');

        $acting->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
            'input_port_id' => $inputPortA->id,
            'output_port_id' => $outputPortA->id,
        ])->assertCreated();

        $acting->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
            'input_port_id' => $inputPortB->id,
            'output_port_id' => $outputPortB->id,
        ])->assertCreated();

        $this->assertDatabaseCount('splitter_branches', 2);
    }

    public function test_same_asset_company_required(): void
    {
        $company = Company::factory()->create();
        $foreignCompany = Company::factory()->create();

        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        [$foreignAsset, $foreignPoint, $foreignInput, $foreignOutput] = $this->endpoint($foreignCompany);

        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        $acting = $this->actingAs($user, 'sanctum');
        $url = "/api/v1/fim/splitter-profiles/{$profile->id}/branches";

        // Cross-company input port
        $acting->postJson($url, [
            'input_port_id' => $foreignInput->id,
            'output_port_id' => $outputPort->id,
        ])->assertUnprocessable();

        // Cross-company output port
        $acting->postJson($url, [
            'input_port_id' => $inputPort->id,
            'output_port_id' => $foreignOutput->id,
        ])->assertUnprocessable();

        // Cross-asset ports from the same company
        $otherSite = Site::factory()->create(['company_id' => $company->id]);
        $otherAsset = Asset::factory()->create([
            'site_id' => $otherSite->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'SPLITTER',
        ]);
        $otherPoint = NetworkConnectionPoint::factory()->create([
            'site_id' => $otherSite->id,
            'asset_id' => $otherAsset->id,
            'company_id' => $company->id,
        ]);
        $crossAssetPort = PassiveOpticalPort::factory()->create([
            'asset_id' => $otherAsset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $otherPoint->id,
            'port_role' => 'splitter_output',
        ]);

        $acting->postJson($url, [
            'input_port_id' => $inputPort->id,
            'output_port_id' => $crossAssetPort->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('splitter_branches', 0);
    }

    public function test_correct_roles_required(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        // Generic port rejected
        $genericPort = PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'network_connection_point_id' => $point->id,
            'port_role' => 'generic',
        ]);

        $acting = $this->actingAs($user, 'sanctum');
        $url = "/api/v1/fim/splitter-profiles/{$profile->id}/branches";

        $acting->postJson($url, [
            'input_port_id' => $genericPort->id,
            'output_port_id' => $outputPort->id,
        ])->assertUnprocessable();

        $acting->postJson($url, [
            'input_port_id' => $inputPort->id,
            'output_port_id' => $genericPort->id,
        ])->assertUnprocessable();

        // Swapped roles rejected
        $acting->postJson($url, [
            'input_port_id' => $outputPort->id,
            'output_port_id' => $inputPort->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('splitter_branches', 0);
    }

    public function test_deleted_ports_rejected(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        $inputPort->delete();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
                'input_port_id' => $inputPort->id,
                'output_port_id' => $outputPort->id,
            ])->assertUnprocessable();

        $this->assertDatabaseCount('splitter_branches', 0);
    }

    public function test_deleted_profile_rejected(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        $profile->delete();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
                'input_port_id' => $inputPort->id,
                'output_port_id' => $outputPort->id,
            ])->assertNotFound();

        $this->assertDatabaseCount('splitter_branches', 0);
    }

    public function test_one_live_branch_per_output(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        $this->actingAs($user, 'sanctum');

        $this->branch($profile, $inputPort, $outputPort);

        $this->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
            'input_port_id' => $inputPort->id,
            'output_port_id' => $outputPort->id,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('splitter_branches', 1);
    }

    public function test_soft_delete_and_recreate_gets_new_id(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        $this->actingAs($user, 'sanctum');

        $first = $this->branch($profile, $inputPort, $outputPort);
        $firstId = $first->id;

        $this->deleteJson("/api/v1/fim/splitter-branches/{$firstId}")->assertOk();
        $this->assertSoftDeleted($first);

        $second = $this->branch($profile, $inputPort, $outputPort);
        $this->assertNotSame($firstId, $second->id);
        $this->assertDatabaseCount('splitter_branches', 2);
    }

    public function test_historical_rows_preserved(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        $this->actingAs($user, 'sanctum');

        $branch = $this->branch($profile, $inputPort, $outputPort);
        $this->deleteJson("/api/v1/fim/splitter-branches/{$branch->id}")->assertOk();

        $this->assertDatabaseCount('splitter_branches', 1);
        $this->assertSoftDeleted($branch);
        $this->assertDatabaseHas('splitter_branches', [
            'input_port_id' => $inputPort->id,
            'output_port_id' => $outputPort->id,
        ]);
    }

    public function test_branch_history_blocks_both_port_deletions(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);

        $this->branch($profile, $inputPort, $outputPort);

        $this->assertDatabaseRejected(fn () => DB::table('passive_optical_ports')
            ->where('id', $inputPort->id)
            ->update(['deleted_at' => now()]));
        $this->assertDatabaseRejected(fn () => DB::table('passive_optical_ports')
            ->where('id', $outputPort->id)
            ->update(['deleted_at' => now()]));

        $this->assertNull($inputPort->fresh()->deleted_at);
        $this->assertNull($outputPort->fresh()->deleted_at);
    }

    public function test_live_branch_blocks_profile_retirement(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);

        $this->branch($profile, $inputPort, $outputPort);

        $this->assertDatabaseRejected(fn () => DB::table('splitter_profiles')
            ->where('id', $profile->id)
            ->update(['deleted_at' => now()]));

        $this->assertNull($profile->fresh()->deleted_at);
    }

    public function test_retired_branch_allows_profile_retirement(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        $this->actingAs($user, 'sanctum');

        $branch = $this->branch($profile, $inputPort, $outputPort);
        $this->deleteJson("/api/v1/fim/splitter-branches/{$branch->id}")->assertOk();

        DB::table('splitter_profiles')->where('id', $profile->id)->update(['deleted_at' => now()]);
        $this->assertSoftDeleted($profile);
    }

    public function test_direct_db_bypass_rejected(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);

        // Wrong asset
        $otherSite = Site::factory()->create(['company_id' => $company->id]);
        $otherAsset = Asset::factory()->create([
            'site_id' => $otherSite->id,
            'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE',
            'type' => 'SPLITTER',
        ]);

        $this->assertDatabaseRejected(fn () => DB::table('splitter_branches')->insert([
            'splitter_profile_id' => $profile->id,
            'asset_id' => $otherAsset->id,
            'company_id' => $company->id,
            'input_port_id' => $inputPort->id,
            'output_port_id' => $outputPort->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        // Wrong company
        $foreignCompany = Company::factory()->create();
        $this->assertDatabaseRejected(fn () => DB::table('splitter_branches')->insert([
            'splitter_profile_id' => $profile->id,
            'asset_id' => $asset->id,
            'company_id' => $foreignCompany->id,
            'input_port_id' => $inputPort->id,
            'output_port_id' => $outputPort->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->assertDatabaseCount('splitter_branches', 0);
    }

    public function test_endpoint_visibility_required(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);

        $this->branch($profile, $inputPort, $outputPort);

        // User without splitter-branches.view cannot list branches
        $noBranchView = $this->user($company, array_values(array_diff($this->permissions(), ['fim.splitter-branches.view'])));
        $this->actingAs($noBranchView, 'sanctum')
            ->getJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches")
            ->assertForbidden();

        // User without splitter-profiles.view cannot create branches (create policy requires viewing profile)
        $noProfileView = $this->user($company, array_values(array_diff($this->permissions(), ['fim.splitter-profiles.view'])));
        $this->actingAs($noProfileView, 'sanctum')
            ->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
                'input_port_id' => $inputPort->id,
                'output_port_id' => $outputPort->id,
            ])->assertForbidden();

        $this->assertDatabaseCount('splitter_branches', 1);
    }

    public function test_parent_mutation_permission_not_required(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);

        $perms = array_values(array_diff($this->permissions(), ['assets.update']));
        $user = $this->user($company, $perms);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
                'input_port_id' => $inputPort->id,
                'output_port_id' => $outputPort->id,
            ])->assertCreated();

        $this->assertDatabaseCount('splitter_branches', 1);
    }

    public function test_nested_list_does_not_leak_hidden_endpoints(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);

        // Create branch with full-permission user
        $creator = $this->user($company, $this->permissions());
        $this->actingAs($creator, 'sanctum');
        $this->branch($profile, $inputPort, $outputPort);

        // List with user who cannot view passive-optical-ports → both ports hidden → branch filtered out
        $noPortView = $this->user($company, array_values(array_diff($this->permissions(), ['fim.passive-optical-ports.view'])));
        $response = $this->actingAs($noPortView, 'sanctum')
            ->getJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches");

        $response->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_audit_compact(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company, $this->permissions());

        $this->actingAs($user, 'sanctum');

        $createResponse = $this->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches", [
            'input_port_id' => $inputPort->id,
            'output_port_id' => $outputPort->id,
        ])->assertCreated();

        $branchId = $createResponse->json('data.id');

        $this->deleteJson("/api/v1/fim/splitter-branches/{$branchId}")->assertOk();

        $logs = AuditLog::where('target_type', SplitterBranch::class)
            ->where('target_id', $branchId)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $logs);
        $this->assertSame('fim.splitter-branch.created', $logs[0]->action);
        $this->assertSame('success', $logs[0]->result);
        $this->assertEquals($user->id, $logs[0]->actor_id);
        $this->assertArrayHasKey('id', $logs[0]->metadata);
        $this->assertArrayHasKey('company_id', $logs[0]->metadata);
        $this->assertArrayHasKey('splitter_profile_id', $logs[0]->metadata);
        $this->assertArrayHasKey('input_port_id', $logs[0]->metadata);
        $this->assertArrayHasKey('output_port_id', $logs[0]->metadata);

        $this->assertSame('fim.splitter-branch.deleted', $logs[1]->action);
        $this->assertSame('success', $logs[1]->result);
        $this->assertEquals($user->id, $logs[1]->actor_id);
        $this->assertArrayHasKey('id', $logs[1]->metadata);
        $this->assertArrayHasKey('company_id', $logs[1]->metadata);
        $this->assertArrayHasKey('splitter_profile_id', $logs[1]->metadata);
        $this->assertArrayHasKey('input_port_id', $logs[1]->metadata);
        $this->assertArrayHasKey('output_port_id', $logs[1]->metadata);
    }

    public function test_no_topology_inferred_without_splitter_branch(): void
    {
        $company = Company::factory()->create();
        [$asset, $point, $inputPort, $outputPort] = $this->endpoint($company);
        $profile = $this->profile($asset);

        $this->assertDatabaseCount('splitter_branches', 0);

        $user = $this->user($company, $this->permissions());
        $this->actingAs($user, 'sanctum');

        $this->getJson("/api/v1/fim/splitter-profiles/{$profile->id}/branches")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.last_page', 1);
    }
}
