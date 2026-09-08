<?php

namespace Tests\Feature\Fim;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\FiberCable;
use App\Models\FiberCore;
use App\Models\FiberSegment;
use App\Models\FiberTermination;
use App\Models\NetworkConnectionPoint;
use App\Models\PassiveOpticalPort;
use App\Models\Site;
use App\Models\SplitterProfile;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SplitterProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('ewnet_test', DB::connection()->getDatabaseName());
        $this->seed();
    }

    /** @return array{0: Asset, 1: NetworkConnectionPoint} */
    protected function endpoint(Company $company): array
    {
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create(['site_id' => $site->id, 'company_id' => $company->id, 'category' => 'INFRASTRUCTURE', 'type' => 'SPLITTER']);
        $point = NetworkConnectionPoint::factory()->create(['site_id' => $site->id, 'asset_id' => $asset->id, 'company_id' => $company->id]);

        return [$asset, $point];
    }

    protected function permissions(): array
    {
        return [
            'assets.view', 'fim.connection-points.view',
            'fim.splitter-profiles.view', 'fim.splitter-profiles.create',
            'fim.splitter-profiles.update', 'fim.splitter-profiles.delete',
            'fim.splitter-profiles.generate-ports',
            'fim.passive-optical-ports.create', 'fim.passive-optical-ports.view',
        ];
    }

    protected function user(Company $company, ?array $permissions = null): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions ?? $this->permissions());
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);
        $this->assertFalse($user->hasRole('Super Admin'));

        return $user->refresh();
    }

    protected function profile(Asset $asset, array $attributes = []): SplitterProfile
    {
        return SplitterProfile::create([
            'asset_id' => $asset->id, 'company_id' => $asset->company_id,
            'input_port_count' => 1, 'output_port_count' => 2, ...$attributes,
        ])->refresh();
    }

    protected function batch(NetworkConnectionPoint $point): array
    {
        return [
            'inputs' => [['port_number' => 'FEED-A', 'network_connection_point_id' => $point->id]],
            'outputs' => [
                ['port_number' => 'DROP-02', 'network_connection_point_id' => $point->id],
                ['port_number' => 'DROP-01', 'network_connection_point_id' => $point->id],
            ],
        ];
    }

    protected function port(Asset $asset, NetworkConnectionPoint $point, array $attributes = []): PassiveOpticalPort
    {
        return PassiveOpticalPort::factory()->create([
            'asset_id' => $asset->id, 'company_id' => $asset->company_id,
            'network_connection_point_id' => $point->id,
            'port_number' => 'FEED-A', 'port_role' => 'splitter_input', ...$attributes,
        ])->refresh();
    }

    protected function assertDatabaseRejected(callable $write, string $sqlState = '23514'): void
    {
        try {
            // Roll back the failed statement's savepoint so subsequent assertions can query PostgreSQL.
            DB::transaction($write);
            $this->fail('Expected PostgreSQL to reject the contract violation.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0]);
        }
    }

    public function test_create_show_and_update_preserve_multi_input_counts_and_descriptive_metadata(): void
    {
        $company = Company::factory()->create();
        [$asset] = $this->endpoint($company);
        $user = $this->user($company);
        $url = "/api/v1/assets/{$asset->id}/splitter-profile";
        $this->actingAs($user, 'sanctum')->getJson($url)->assertNotFound();
        $created = $this->postJson($url, [
            'input_port_count' => 2, 'output_port_count' => 3,
            'split_ratio' => 'vendor-described asymmetric split',
            'metadata' => ['survey' => ['note' => 'private profile marker'], 'loss_db' => 3.5],
        ])->assertCreated()->assertJsonPath('data.asset_id', $asset->id)
            ->assertJsonPath('data.company_id', $company->id)
            ->assertJsonPath('data.input_port_count', 2)->assertJsonPath('data.output_port_count', 3)
            ->assertJsonPath('data.split_ratio', 'vendor-described asymmetric split')
            ->assertJsonPath('data.metadata.survey.note', 'private profile marker')
            ->assertJsonPath('data.created_by', $user->id)->assertJsonPath('data.updated_by', $user->id);
        $id = $created->json('data.id');
        $this->getJson($url)->assertOk()->assertExactJson(['data' => $created->json('data')]);
        $editor = $this->user($company);
        $this->actingAs($editor, 'sanctum')->patchJson("/api/v1/fim/splitter-profiles/{$id}", [
            'input_port_count' => 3, 'output_port_count' => 5, 'split_ratio' => null, 'metadata' => null,
        ])->assertOk()->assertJsonPath('data.input_port_count', 3)->assertJsonPath('data.output_port_count', 5)
            ->assertJsonPath('data.split_ratio', null)->assertJsonPath('data.metadata', null)
            ->assertJsonPath('data.created_by', $user->id)->assertJsonPath('data.updated_by', $editor->id);
        $this->assertDatabaseCount('passive_optical_ports', 0);
    }

    public static function ineligibleAssets(): array
    {
        return [
            'wrong category' => [['category' => 'NETWORK'], 422],
            'wrong type' => [['type' => 'ODF'], 422],
            'implicit company is not enough' => [['company_id' => null], 403],
            'soft deleted' => [['deleted_at' => '2026-09-01 00:00:00'], 404],
        ];
    }

    #[DataProvider('ineligibleAssets')]
    public function test_only_live_explicit_company_infrastructure_splitters_are_eligible(array $attributes, int $status): void
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id]);
        $asset = Asset::factory()->create([
            'site_id' => $site->id, 'company_id' => $company->id,
            'category' => 'INFRASTRUCTURE', 'type' => 'SPLITTER', ...$attributes,
        ]);
        $this->actingAs($this->user($company), 'sanctum')->postJson("/api/v1/assets/{$asset->id}/splitter-profile", [
            'input_port_count' => 1, 'output_port_count' => 2,
        ])->assertStatus($status);
        $this->assertDatabaseRejected(fn () => DB::table('splitter_profiles')->insert([
            'asset_id' => $asset->id, 'company_id' => $company->id, 'input_port_count' => 1, 'output_port_count' => 2,
        ]));
        $this->assertDatabaseCount('splitter_profiles', 0);
    }

    public static function invalidProfileFields(): array
    {
        return [
            'zero inputs' => ['input_port_count', 0],
            'negative outputs' => ['output_port_count', -1],
            'fractional inputs' => ['input_port_count', 1.5],
            'null outputs' => ['output_port_count', null],
            'overflow' => ['output_port_count', 2147483648],
            'invalid metadata' => ['metadata', 'not an array'],
            'long ratio' => ['split_ratio', str_repeat('x', 256)],
            'asset ownership' => ['asset_id', 999999],
            'company ownership' => ['company_id', 999999],
        ];
    }

    #[DataProvider('invalidProfileFields')]
    public function test_create_and_update_validate_counts_metadata_and_ownership(string $field, mixed $value): void
    {
        $company = Company::factory()->create();
        [$asset] = $this->endpoint($company);
        $this->actingAs($this->user($company), 'sanctum')->postJson("/api/v1/assets/{$asset->id}/splitter-profile", [
            'input_port_count' => 1, 'output_port_count' => 2, $field => $value,
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('splitter_profiles', 0);
        $profile = $this->profile($asset);
        $before = $profile->getAttributes();
        $this->patchJson("/api/v1/fim/splitter-profiles/{$profile->id}", [$field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame($before, $profile->fresh()->getAttributes());
    }

    public function test_database_enforces_positive_counts_and_immutable_ownership(): void
    {
        $company = Company::factory()->create();
        [$asset] = $this->endpoint($company);
        [$otherAsset] = $this->endpoint($company);
        $otherCompany = Company::factory()->create();
        foreach (['input_port_count', 'output_port_count'] as $field) {
            foreach ([0, -1] as $value) {
                $this->assertDatabaseRejected(fn () => DB::table('splitter_profiles')->insert([
                    'asset_id' => $asset->id, 'company_id' => $company->id,
                    'input_port_count' => 1, 'output_port_count' => 2, $field => $value,
                ]));
            }
        }
        $this->assertDatabaseRejected(fn () => DB::table('splitter_profiles')->insert([
            'asset_id' => $asset->id, 'company_id' => $otherCompany->id, 'input_port_count' => 1, 'output_port_count' => 2,
        ]));
        $profile = $this->profile($asset);
        foreach (['input_port_count' => 0, 'output_port_count' => -1, 'asset_id' => $otherAsset->id, 'company_id' => $otherCompany->id] as $field => $value) {
            $this->assertDatabaseRejected(fn () => DB::table('splitter_profiles')->where('id', $profile->id)->update([$field => $value]));
        }
        $this->assertSame($profile->getAttributes(), $profile->fresh()->getAttributes());
    }

    public function test_profile_uniqueness_includes_retired_history_and_retirement_is_terminal(): void
    {
        $company = Company::factory()->create();
        [$asset] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $this->actingAs($this->user($company), 'sanctum');
        foreach ([false, true] as $retired) {
            if ($retired) {
                $this->deleteJson("/api/v1/fim/splitter-profiles/{$profile->id}")->assertOk();
            }
            $this->postJson("/api/v1/assets/{$asset->id}/splitter-profile", ['input_port_count' => 2, 'output_port_count' => 4])
                ->assertUnprocessable()->assertJsonValidationErrors('asset_id');
            $this->assertDatabaseRejected(fn () => DB::table('splitter_profiles')->insert([
                'asset_id' => $asset->id, 'company_id' => $company->id, 'input_port_count' => 2, 'output_port_count' => 4,
            ]), '23505');
        }
        $this->assertDatabaseRejected(fn () => DB::table('splitter_profiles')->where('id', $profile->id)->update(['deleted_at' => null]));
        $this->assertDatabaseCount('splitter_profiles', 1);
        $this->assertSoftDeleted($profile);
    }

    public function test_counts_can_change_with_only_generic_history_including_direct_database_writes(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $generic = $this->port($asset, $point, ['port_role' => 'generic']);
        $this->actingAs($this->user($company), 'sanctum')->patchJson("/api/v1/fim/splitter-profiles/{$profile->id}", [
            'input_port_count' => 2, 'output_port_count' => 3,
        ])->assertOk()->assertJsonPath('data.input_port_count', 2)->assertJsonPath('data.output_port_count', 3);
        $generic->delete();
        DB::table('splitter_profiles')->where('id', $profile->id)->update(['input_port_count' => 3, 'output_port_count' => 4]);
        $this->assertSame(3, $profile->fresh()->input_port_count);
        $this->assertSame(4, $profile->fresh()->output_port_count);
        $this->assertSoftDeleted($generic);
    }

    public static function roleHistory(): array
    {
        return [
            'live input' => ['splitter_input', false],
            'deleted input' => ['splitter_input', true],
            'live output' => ['splitter_output', false],
            'deleted output' => ['splitter_output', true],
        ];
    }

    #[DataProvider('roleHistory')]
    public function test_any_splitter_role_history_freezes_both_counts_but_not_metadata(string $role, bool $deleted): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        $port = $this->port($asset, $point, ['port_role' => $role]);
        if ($deleted) {
            $port->delete();
        }
        $profile = $this->profile($asset);
        $this->actingAs($this->user($company), 'sanctum');
        foreach (['input_port_count' => 2, 'output_port_count' => 3] as $field => $value) {
            $this->patchJson("/api/v1/fim/splitter-profiles/{$profile->id}", [$field => $value])->assertUnprocessable();
            $this->assertDatabaseRejected(fn () => DB::table('splitter_profiles')->where('id', $profile->id)->update([$field => $value]));
        }
        $this->patchJson("/api/v1/fim/splitter-profiles/{$profile->id}", [
            'input_port_count' => 1, 'output_port_count' => 2, 'split_ratio' => 'description only', 'metadata' => ['note' => 'updated'],
        ])->assertOk()->assertJsonPath('data.input_port_count', 1)->assertJsonPath('data.output_port_count', 2)
            ->assertJsonPath('data.split_ratio', 'description only')->assertJsonPath('data.metadata.note', 'updated');
    }

    public function test_generation_uses_explicit_labels_and_per_port_ncps_and_exact_retries_reuse_ids(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        [$otherAsset, $otherPoint] = $this->endpoint($company);
        $profile = $this->profile($asset, ['input_port_count' => 2]);
        $batch = $this->batch($point);
        $batch['inputs'][] = ['port_number' => 'FEED-B', 'network_connection_point_id' => $otherPoint->id];
        $batch['outputs'][0]['network_connection_point_id'] = $otherPoint->id;
        $generic = $this->port($asset, $point, ['port_number' => 'SERVICE', 'port_role' => 'generic', 'metadata' => ['keep' => true]]);
        $deletedGeneric = $this->port($asset, $point, ['port_number' => 'OLD-SERVICE', 'port_role' => 'generic']);
        $deletedGeneric->delete();
        $deletedGenericBefore = $deletedGeneric->getAttributes();
        $otherPort = $this->port($otherAsset, $otherPoint);
        $genericBefore = $generic->getAttributes();
        $otherBefore = $otherPort->getAttributes();
        $user = $this->user($company);
        foreach (['assets.create', 'assets.update', 'assets.delete', 'fim.connection-points.create', 'fim.connection-points.update', 'fim.connection-points.delete'] as $permission) {
            $this->assertFalse($user->hasPermissionTo($permission));
        }
        $url = "/api/v1/fim/splitter-profiles/{$profile->id}/ports/generate";
        $first = $this->actingAs($user, 'sanctum')->postJson($url, $batch)->assertCreated()
            ->assertJsonCount(4, 'data')->assertJsonPath('meta.created_count', 4)->assertJsonPath('meta.matched_existing_count', 0);
        $this->assertSame(['FEED-A', 'FEED-B', 'DROP-01', 'DROP-02'], array_column($first->json('data'), 'port_number'));
        foreach (['inputs' => 'splitter_input', 'outputs' => 'splitter_output'] as $key => $role) {
            foreach ($batch[$key] as $expected) {
                $this->assertDatabaseHas('passive_optical_ports', [
                    ...$expected, 'asset_id' => $asset->id, 'company_id' => $company->id, 'port_role' => $role,
                    'connector_type' => null, 'metadata' => null, 'created_by' => $user->id, 'updated_by' => $user->id,
                ]);
            }
        }
        $retry = $this->postJson($url, $batch)->assertCreated()->assertJsonPath('meta.created_count', 0)->assertJsonPath('meta.matched_existing_count', 4);
        $this->assertSame($first->json('data'), $retry->json('data'));
        $this->assertDatabaseCount('passive_optical_ports', 7);
        $this->assertSame($genericBefore, $generic->fresh()->getAttributes());
        $this->assertSame($deletedGenericBefore, PassiveOpticalPort::withTrashed()->findOrFail($deletedGeneric->id)->getAttributes());
        $this->assertSame($otherBefore, $otherPort->fresh()->getAttributes());
    }

    public function test_generation_reuses_exact_manual_ports_without_overwriting_attributes(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company);
        $manual = $this->actingAs($user, 'sanctum')->postJson("/api/v1/assets/{$asset->id}/passive-optical-ports", [
            'network_connection_point_id' => $point->id, 'port_number' => 'FEED-A', 'port_role' => 'splitter_input',
            'connector_type' => 'SC/APC', 'metadata' => ['manual' => 'keep me'],
        ])->assertCreated();
        $port = PassiveOpticalPort::findOrFail($manual->json('data.id'));
        $before = $port->getAttributes();
        $this->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/ports/generate", $this->batch($point))->assertCreated()
            ->assertJsonPath('meta.created_count', 2)->assertJsonPath('meta.matched_existing_count', 1)
            ->assertJsonPath('data.0.id', $port->id)->assertJsonPath('data.0.connector_type', 'SC/APC')
            ->assertJsonPath('data.0.metadata.manual', 'keep me');
        $this->assertSame($before, $port->fresh()->getAttributes());
        $this->assertDatabaseCount('passive_optical_ports', 3);
    }

    public function test_duplicate_input_labels_are_rejected_even_when_the_ncps_differ(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        [, $otherPoint] = $this->endpoint($company);
        $profile = $this->profile($asset, ['input_port_count' => 2]);
        $batch = $this->batch($point);
        $batch['inputs'][] = ['port_number' => 'FEED-A', 'network_connection_point_id' => $otherPoint->id];
        $this->actingAs($this->user($company), 'sanctum')->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/ports/generate", $batch)
            ->assertUnprocessable()->assertJsonValidationErrors('inputs');
        $this->assertDatabaseCount('passive_optical_ports', 0);
    }

    public function test_generated_counts_are_frozen_and_historical_labels_cannot_be_recreated_manually_or_by_sql(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $url = "/api/v1/fim/splitter-profiles/{$profile->id}";
        $id = $this->actingAs($this->user($company), 'sanctum')->postJson("{$url}/ports/generate", $this->batch($point))
            ->assertCreated()->json('data.0.id');
        $port = PassiveOpticalPort::findOrFail($id);
        foreach ([false, true] as $deleted) {
            if ($deleted) {
                PassiveOpticalPort::where('asset_id', $asset->id)->delete();
            }
            $this->patchJson($url, ['input_port_count' => 2, 'output_port_count' => 4])->assertUnprocessable();
            $this->assertDatabaseRejected(fn () => DB::table('splitter_profiles')->where('id', $profile->id)->update(['output_port_count' => 4]));
            $this->postJson("/api/v1/assets/{$asset->id}/passive-optical-ports", [
                'network_connection_point_id' => $point->id, 'port_number' => $port->port_number, 'port_role' => 'splitter_input',
            ])->assertUnprocessable()->assertJsonValidationErrors('port_number');
            $this->assertDatabaseRejected(fn () => DB::table('passive_optical_ports')->insert([
                'asset_id' => $asset->id, 'company_id' => $company->id, 'network_connection_point_id' => $point->id,
                'port_number' => $port->port_number, 'port_role' => 'splitter_input',
            ]), '23505');
        }
        $this->postJson("{$url}/ports/generate", $this->batch($point))->assertUnprocessable();
        $this->assertDatabaseCount('passive_optical_ports', 3);
        $this->assertSame(1, $profile->fresh()->input_port_count);
        $this->assertSame(2, $profile->fresh()->output_port_count);
    }

    public static function conflictingHistory(): array
    {
        return [
            'generic reserves label' => ['generic', false, false, false],
            'wrong role' => ['splitter_input', false, false, false],
            'wrong ncp' => ['splitter_output', false, true, false],
            'deleted exact match' => ['splitter_output', true, false, false],
            'deleted generic reserves label' => ['generic', true, false, false],
            'extra live input history' => ['splitter_input', false, false, true],
            'extra deleted input history' => ['splitter_input', true, false, true],
            'extra live output history' => ['splitter_output', false, false, true],
            'extra deleted output history' => ['splitter_output', true, false, true],
        ];
    }

    #[DataProvider('conflictingHistory')]
    public function test_conflicting_or_extra_role_history_rejects_the_whole_batch(string $role, bool $deleted, bool $wrongPoint, bool $extra): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        [, $otherPoint] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $port = $this->port($asset, $wrongPoint ? $otherPoint : $point, [
            'port_number' => $extra ? 'UNREQUESTED' : 'DROP-01', 'port_role' => $role, 'metadata' => ['original' => true],
        ]);
        if ($deleted) {
            $port->delete();
        }
        $before = $port->getAttributes();
        $this->actingAs($this->user($company), 'sanctum')->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/ports/generate", $this->batch($point))
            ->assertUnprocessable()->assertJsonValidationErrors('inputs');
        $this->assertDatabaseCount('passive_optical_ports', 1);
        $this->assertSame($before, PassiveOpticalPort::withTrashed()->findOrFail($port->id)->getAttributes());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'fim.splitter-profile.ports-generated']);
    }

    public static function invalidBatches(): array
    {
        return [
            'missing inputs' => ['missing-inputs'],
            'missing outputs' => ['missing-outputs'],
            'too few inputs' => ['few-inputs'],
            'too many inputs' => ['many-inputs'],
            'too few outputs' => ['few-outputs'],
            'too many outputs' => ['many-outputs'],
            'duplicate across roles' => ['cross-duplicate'],
            'duplicate within outputs' => ['output-duplicate'],
            'missing label' => ['missing-label'],
            'empty label' => ['empty-label'],
            'long label' => ['long-label'],
            'missing ncp' => ['missing-ncp'],
            'invalid ncp' => ['invalid-ncp'],
            'implicit array keys' => ['not-list'],
            'unapproved port field' => ['extra-field'],
        ];
    }

    #[DataProvider('invalidBatches')]
    public function test_invalid_generation_batches_are_atomic(string $case): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $batch = $this->batch($point);
        switch ($case) {
            case 'missing-inputs': unset($batch['inputs']);
                break;
            case 'missing-outputs': unset($batch['outputs']);
                break;
            case 'few-inputs': $batch['inputs'] = [];
                break;
            case 'many-inputs': $batch['inputs'][] = ['port_number' => 'FEED-B', 'network_connection_point_id' => $point->id];
                break;
            case 'few-outputs': array_pop($batch['outputs']);
                break;
            case 'many-outputs': $batch['outputs'][] = ['port_number' => 'DROP-03', 'network_connection_point_id' => $point->id];
                break;
            case 'cross-duplicate': $batch['outputs'][1]['port_number'] = 'FEED-A';
                break;
            case 'output-duplicate': $batch['outputs'][1]['port_number'] = 'DROP-02';
                break;
            case 'missing-label': unset($batch['outputs'][1]['port_number']);
                break;
            case 'empty-label': $batch['outputs'][1]['port_number'] = '';
                break;
            case 'long-label': $batch['outputs'][1]['port_number'] = str_repeat('x', 256);
                break;
            case 'missing-ncp': unset($batch['outputs'][1]['network_connection_point_id']);
                break;
            case 'invalid-ncp': $batch['outputs'][1]['network_connection_point_id'] = 0;
                break;
            case 'not-list': $batch['outputs'] = ['named' => $batch['outputs'][0]];
                break;
            case 'extra-field': $batch['outputs'][1]['port_role'] = 'generic';
                break;
        }
        $this->actingAs($this->user($company), 'sanctum')->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/ports/generate", $batch)->assertUnprocessable();
        $this->assertDatabaseCount('passive_optical_ports', 0);
        $this->assertSame($profile->getAttributes(), $profile->fresh()->getAttributes());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'fim.splitter-profile.ports-generated']);
    }

    public function test_visible_foreign_company_and_deleted_ncps_reject_generation_atomically(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        [, $foreignPoint] = $this->endpoint($other);
        [, $deletedPoint] = $this->endpoint($company);
        $deletedPoint->delete();
        $profile = $this->profile($asset);
        $user = $this->user($company);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $other->id, 'granted_by' => $user->id]);
        $this->actingAs($user->refresh(), 'sanctum');
        foreach ([$foreignPoint, $deletedPoint] as $invalidPoint) {
            $batch = $this->batch($point);
            $batch['outputs'][1]['network_connection_point_id'] = $invalidPoint->id;
            $this->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/ports/generate", $batch)->assertUnprocessable()->assertJsonValidationErrors('inputs');
            $this->assertDatabaseCount('passive_optical_ports', 0);
        }
    }

    public function test_retirement_preserves_ports_and_live_and_historical_attachments_and_hides_profile_routes(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        [, $otherPoint] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $url = "/api/v1/fim/splitter-profiles/{$profile->id}";
        $ports = $this->actingAs($this->user($company), 'sanctum')->postJson("{$url}/ports/generate", $this->batch($point))->assertCreated()->json('data');
        $cable = FiberCable::factory()->create(['company_id' => $company->id]);
        $segment = FiberSegment::factory()->create(['fiber_cable_id' => $cable->id, 'company_id' => $company->id, 'endpoint_a_id' => $point->id, 'endpoint_b_id' => $otherPoint->id]);
        foreach ([false, true] as $index => $deleted) {
            $core = FiberCore::factory()->create(['fiber_segment_id' => $segment->id, 'company_id' => $company->id, 'core_number' => $index + 1]);
            $termination = FiberTermination::create(['fiber_core_id' => $core->id, 'company_id' => $company->id, 'network_connection_point_id' => $point->id, 'segment_end' => 'A']);
            DB::table('fiber_termination_port_attachments')->insert([
                'fiber_termination_id' => $termination->id, 'passive_optical_port_id' => $ports[$index]['id'],
                'company_id' => $company->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($deleted) {
                DB::table('fiber_termination_port_attachments')->where('fiber_termination_id', $termination->id)->update(['deleted_at' => now()]);
            }
        }
        $portSnapshot = DB::table('passive_optical_ports')->orderBy('id')->get()->toJson();
        $attachmentSnapshot = DB::table('fiber_termination_port_attachments')->orderBy('id')->get()->toJson();
        $terminationSnapshot = DB::table('fiber_terminations')->orderBy('id')->get()->toJson();
        $this->deleteJson($url)->assertOk();
        $this->assertSoftDeleted($profile);
        $this->postJson("{$url}/ports/generate", $this->batch($point))->assertNotFound();
        $this->getJson("/api/v1/assets/{$asset->id}/splitter-profile")->assertNotFound();
        $this->patchJson($url, ['metadata' => []])->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->assertSame($portSnapshot, DB::table('passive_optical_ports')->orderBy('id')->get()->toJson());
        $this->assertSame($attachmentSnapshot, DB::table('fiber_termination_port_attachments')->orderBy('id')->get()->toJson());
        $this->assertSame($terminationSnapshot, DB::table('fiber_terminations')->orderBy('id')->get()->toJson());
        $this->assertNull($asset->fresh()->deleted_at);
    }

    public function test_live_and_retired_profile_history_blocks_direct_asset_deletion_reclassification_and_company_changes(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        // No NCP or port history: these rejections must come from splitter profile protection alone.
        $asset = Asset::factory()->create(['company_id' => $company->id, 'category' => 'INFRASTRUCTURE', 'type' => 'SPLITTER']);
        $profile = $this->profile($asset);
        $before = $asset->refresh()->getAttributes();
        foreach ([false, true] as $retired) {
            if ($retired) {
                $profile->delete();
            }
            foreach (['deleted_at' => now(), 'category' => 'NETWORK', 'type' => 'ODF', 'company_id' => $other->id] as $field => $value) {
                $this->assertDatabaseRejected(fn () => DB::table('assets')->where('id', $asset->id)->update([$field => $value]));
            }
            $this->assertDatabaseRejected(fn () => DB::table('assets')->where('id', $asset->id)->update(['company_id' => null]));
            $this->assertDatabaseRejected(fn () => DB::table('assets')->where('id', $asset->id)->delete(), '23503');
            $this->assertSame($before, $asset->fresh()->getAttributes());
        }
    }

    public function test_all_profile_routes_require_authentication(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $this->getJson("/api/v1/assets/{$asset->id}/splitter-profile")->assertUnauthorized();
        $this->postJson("/api/v1/assets/{$asset->id}/splitter-profile", [])->assertUnauthorized();
        $this->patchJson("/api/v1/fim/splitter-profiles/{$profile->id}", [])->assertUnauthorized();
        $this->deleteJson("/api/v1/fim/splitter-profiles/{$profile->id}")->assertUnauthorized();
        $this->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/ports/generate", $this->batch($point))->assertUnauthorized();
    }

    public function test_each_profile_action_requires_its_seeded_permission(): void
    {
        $company = Company::factory()->create();
        [$asset] = $this->endpoint($company);
        [$emptyAsset] = $this->endpoint($company);
        $profile = $this->profile($asset);
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            $user = $this->user($company, array_values(array_diff($this->permissions(), ['fim.splitter-profiles.'.$action])));
            $this->actingAs($user, 'sanctum');
            (match ($action) {
                'view' => $this->getJson("/api/v1/assets/{$asset->id}/splitter-profile"),
                'create' => $this->postJson("/api/v1/assets/{$emptyAsset->id}/splitter-profile", ['input_port_count' => 1, 'output_port_count' => 2]),
                'update' => $this->patchJson("/api/v1/fim/splitter-profiles/{$profile->id}", ['metadata' => ['unauthorized' => true]]),
                'delete' => $this->deleteJson("/api/v1/fim/splitter-profiles/{$profile->id}"),
            })->assertForbidden();
        }
        $this->assertDatabaseCount('splitter_profiles', 1);
        $this->assertSame($profile->getAttributes(), $profile->fresh()->getAttributes());
    }

    public static function generationPermissions(): array
    {
        return array_map(fn ($permission) => [$permission], [
            'fim.splitter-profiles.generate-ports', 'fim.passive-optical-ports.create',
            'fim.passive-optical-ports.view', 'assets.view', 'fim.connection-points.view',
        ]);
    }

    #[DataProvider('generationPermissions')]
    public function test_generation_requires_each_permission_and_endpoint_visibility(string $missing): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $user = $this->user($company, array_values(array_diff($this->permissions(), [$missing])));
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/ports/generate", $this->batch($point))->assertForbidden();
        $this->assertDatabaseCount('passive_optical_ports', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'fim.splitter-profile.ports-generated']);
    }

    public function test_hidden_assets_deny_all_profile_actions_even_with_feature_permissions(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        [$emptyAsset] = $this->endpoint($company);
        $profile = $this->profile($asset);
        $users = [
            $this->user($company, array_values(array_diff($this->permissions(), ['assets.view']))),
            $this->user(Company::factory()->create()),
        ];
        foreach ($users as $user) {
            $this->actingAs($user, 'sanctum')->getJson("/api/v1/assets/{$asset->id}/splitter-profile")->assertForbidden()->assertJsonMissing(['id' => $profile->id]);
            $this->postJson("/api/v1/assets/{$emptyAsset->id}/splitter-profile", ['input_port_count' => 1, 'output_port_count' => 2])->assertForbidden();
            $this->patchJson("/api/v1/fim/splitter-profiles/{$profile->id}", ['metadata' => ['hidden' => true]])->assertForbidden();
            $this->deleteJson("/api/v1/fim/splitter-profiles/{$profile->id}")->assertForbidden();
            $this->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/ports/generate", $this->batch($point))->assertForbidden();
        }
        $this->assertDatabaseCount('splitter_profiles', 1);
        $this->assertDatabaseCount('passive_optical_ports', 0);
        $this->assertSame($profile->getAttributes(), $profile->fresh()->getAttributes());
    }

    public function test_hidden_and_missing_requested_ncps_are_forbidden_before_matching_diagnostics(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        [, $hiddenPoint] = $this->endpoint(Company::factory()->create());
        $profile = $this->profile($asset);
        $this->actingAs($this->user($company), 'sanctum');
        foreach ([$hiddenPoint->id, 2147483647] as $id) {
            $batch = $this->batch($point);
            $batch['outputs'][1] = ['port_number' => 'FEED-A', 'network_connection_point_id' => $id];
            $this->postJson("/api/v1/fim/splitter-profiles/{$profile->id}/ports/generate", $batch)->assertForbidden()->assertJsonMissingPath('errors');
        }
        $this->assertDatabaseCount('passive_optical_ports', 0);
    }

    public function test_audits_are_compact_and_never_include_profile_metadata_ratio_or_port_payloads(): void
    {
        $company = Company::factory()->create();
        [$asset, $point] = $this->endpoint($company);
        $user = $this->user($company);
        $id = $this->actingAs($user, 'sanctum')->postJson("/api/v1/assets/{$asset->id}/splitter-profile", [
            'input_port_count' => 1, 'output_port_count' => 2, 'split_ratio' => 'private ratio marker',
            'metadata' => ['nested' => ['note' => 'private create marker']],
        ])->assertCreated()->json('data.id');
        $url = "/api/v1/fim/splitter-profiles/{$id}";
        $this->patchJson($url, ['split_ratio' => 'private revised ratio', 'metadata' => ['note' => 'private update marker']])->assertOk();
        $this->postJson("{$url}/ports/generate", $this->batch($point))->assertCreated();
        $this->postJson("{$url}/ports/generate", $this->batch($point))->assertCreated();
        $this->deleteJson($url)->assertOk();
        $identity = ['id' => $id, 'asset_id' => $asset->id, 'company_id' => $company->id];
        $counts = [...$identity, 'input_port_count' => 1, 'output_port_count' => 2];
        $logs = AuditLog::where('target_type', SplitterProfile::class)->where('target_id', $id)->orderBy('id')->get();
        $expected = [
            ['created', $counts],
            ['updated', [...$counts, 'input_port_count_changed' => false, 'output_port_count_changed' => false, 'split_ratio_changed' => true, 'metadata_changed' => true]],
            ['ports-generated', [...$identity, 'created_count' => 3, 'matched_existing_count' => 0]],
            ['ports-generated', [...$identity, 'created_count' => 0, 'matched_existing_count' => 3]],
            ['deleted', $counts],
        ];
        $this->assertCount(5, $logs);
        foreach ($expected as $index => [$action, $metadata]) {
            $this->assertSame('fim.splitter-profile.'.$action, $logs[$index]->action);
            $this->assertSame('success', $logs[$index]->result);
            $this->assertEquals($user->id, $logs[$index]->actor_id);
            $this->assertEquals($metadata, $logs[$index]->metadata);
            $this->assertStringNotContainsString('private', json_encode($logs[$index]->metadata, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('FEED-A', json_encode($logs[$index]->metadata, JSON_THROW_ON_ERROR));
        }
    }
}
