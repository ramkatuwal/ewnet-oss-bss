<?php

namespace Tests\Feature;

use App\Http\Resources\V1\AuditLogResource;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\RoutingInstance;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoutingInstanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function user(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        UserManagementScope::create(['user_id' => $user->id, 'scope_type' => 'company', 'scope_id' => $company->id, 'granted_by' => $user->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        return $user;
    }

    protected function asset(Company $company, array $attributes = []): Asset
    {
        return Asset::factory()->create([
            'company_id' => $company->id,
            'site_id' => Site::factory()->create(['company_id' => $company->id])->id,
            'category' => 'NETWORK',
            'type' => 'SWITCH',
            ...$attributes,
        ]);
    }

    protected function permissions(): array
    {
        return ['assets.view', 'net.routing-instances.view', 'net.routing-instances.create', 'net.routing-instances.delete'];
    }

    public function test_create_list_show_and_retire_routing_instances_with_safe_audit_metadata(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, $this->permissions());
        $asset = $this->asset($company);

        $id = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/routing-instances", [
            'name' => 'main', 'kind' => 'default', 'metadata' => ['description' => 'not audited'],
        ])->assertCreated()->assertJsonPath('data.name', 'main')->json('data.id');
        $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/routing-instances")->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($user)->getJson("/api/v1/routing-instances/{$id}")->assertOk()->assertJsonPath('data.kind', 'default');
        $this->actingAs($user)->deleteJson("/api/v1/routing-instances/{$id}")->assertOk();

        $this->assertSoftDeleted('routing_instances', ['id' => $id]);
        $audit = AuditLog::where('action', 'net.routing-instance-created')->firstOrFail();
        $this->assertSame($id, $audit->metadata['routing_instance_id']);
        $this->assertArrayNotHasKey('metadata', $audit->metadata);
        $this->assertDatabaseHas('audit_logs', ['action' => 'net.routing-instance-retired', 'target_id' => $id]);
    }

    public function test_identity_default_cardinality_and_history_are_enforced(): void
    {
        $company = Company::factory()->create();
        $user = $this->user($company, $this->permissions());
        $asset = $this->asset($company);
        $first = $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/routing-instances", ['name' => 'main', 'kind' => 'default'])->assertCreated()->json('data.id');

        $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/routing-instances", ['name' => 'main', 'kind' => 'vrf'])->assertUnprocessable();
        $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/routing-instances", ['name' => 'customers', 'kind' => 'default'])->assertUnprocessable();
        $this->actingAs($user)->deleteJson("/api/v1/routing-instances/{$first}")->assertOk();
        $this->actingAs($user)->postJson("/api/v1/assets/{$asset->id}/routing-instances", ['name' => 'main', 'kind' => 'default'])->assertCreated()->assertJsonPath('data.id', fn ($id) => $id !== $first);
        $this->expectException(QueryException::class);
        DB::table('routing_instances')->where('id', $first)->update(['deleted_at' => null]);
    }

    public function test_raw_database_and_parent_history_protections_hold(): void
    {
        $company = Company::factory()->create();
        $asset = $this->asset($company);
        $other = Company::factory()->create();

        $this->expectQueryException(fn () => DB::table('routing_instances')->insert([
            'asset_id' => $asset->id, 'company_id' => $other->id, 'name' => 'main', 'kind' => 'default', 'created_at' => now(), 'updated_at' => now(),
        ]));
        $instance = RoutingInstance::create(['asset_id' => $asset->id, 'company_id' => $company->id, 'name' => 'main', 'kind' => 'default']);
        $this->expectQueryException(fn () => DB::table('routing_instances')->where('id', $instance->id)->update(['name' => 'changed']));
        $this->expectQueryException(fn () => DB::table('assets')->where('id', $asset->id)->update(['category' => 'INFRASTRUCTURE']));
        $this->expectQueryException(fn () => DB::table('assets')->where('id', $asset->id)->update(['company_id' => $other->id]));
        DB::table('assets')->where('id', $asset->id)->update(['description' => 'Allowed descriptive update']);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'description' => 'Allowed descriptive update']);
    }

    public function test_scope_and_asset_visibility_do_not_leak_routing_instances(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = $this->user($company, $this->permissions());
        $asset = $this->asset($other);
        $instance = RoutingInstance::create(['asset_id' => $asset->id, 'company_id' => $other->id, 'name' => 'main', 'kind' => 'default']);

        $this->actingAs($user)->getJson("/api/v1/assets/{$asset->id}/routing-instances")->assertForbidden();
        $this->actingAs($user)->getJson("/api/v1/routing-instances/{$instance->id}")->assertForbidden();
    }

    public function test_audit_resource_redacts_hidden_routing_instance_identifiers(): void
    {
        $company = Company::factory()->create();
        $asset = $this->asset($company);
        $instance = RoutingInstance::create(['asset_id' => $asset->id, 'company_id' => $company->id, 'name' => 'main', 'kind' => 'default']);
        $log = AuditLog::create(['action' => 'net.routing-instance-created', 'result' => 'success', 'metadata' => [
            'routing_instance_id' => $instance->id,
            'asset_id' => $asset->id,
            'company_id' => $company->id,
            'name' => $instance->name,
            'kind' => $instance->kind,
        ]]);
        $request = Request::create('/');
        $request->setUserResolver(fn () => User::factory()->create());

        $metadata = (new AuditLogResource($log))->toArray($request)['metadata'];

        $this->assertSame(['name' => 'main', 'kind' => 'default'], $metadata);
    }

    private function expectQueryException(callable $operation): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected database integrity violation.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
