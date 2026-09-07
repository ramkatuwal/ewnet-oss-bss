<?php

namespace Tests\Feature\Fim;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\NetworkConnectionPoint;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkConnectionPointGeometryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function scopedUser(Company $company, array $permissions): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        return $user->refresh();
    }

    public function test_standalone_geometry_stored_as_postgis_point(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.create']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/fim/connection-points', [
                'point_type' => 'standalone',
                'name' => 'Geo Point',
                'geometry' => ['type' => 'Point', 'coordinates' => [81.52572, 28.97354]],
                'company_id' => $company->id,
                'status' => 'active',
            ]);

        $point = NetworkConnectionPoint::first();
        $this->assertNotNull($point->geometry);

        $raw = \DB::selectOne('SELECT ST_SRID(geometry) AS srid, ST_GeometryType(geometry) AS type FROM network_connection_points WHERE id = ?', [$point->id]);
        $this->assertEquals(4326, (int) $raw->srid);
        $this->assertEquals('ST_Point', $raw->type);
    }

    public function test_standalone_geometry_with_site_anchor_uses_site_location(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.create']);
        $site = Site::factory()->create(['latitude' => '28.97354', 'longitude' => '81.52572', 'company_id' => $company->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/fim/connection-points', [
                'point_type' => 'odf',
                'name' => 'Site-Anchored ODF',
                'site_id' => $site->id,
                'company_id' => $company->id,
                'status' => 'active',
            ]);

        $point = NetworkConnectionPoint::first();
        $this->assertNull($point->geometry);
        $this->assertEquals($site->id, $point->site_id);
    }

    public function test_geometry_compact_audit_not_full_coordinates(): void
    {
        $company = Company::factory()->create();
        $user = $this->scopedUser($company, ['fim.connection-points.create']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/fim/connection-points', [
                'point_type' => 'standalone',
                'name' => 'Geo Point',
                'geometry' => ['type' => 'Point', 'coordinates' => [81.52572, 28.97354]],
                'company_id' => $company->id,
                'status' => 'active',
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'fim.connection-point.created',
            'target_type' => NetworkConnectionPoint::class,
        ]);

        $audit = AuditLog::where('target_type', NetworkConnectionPoint::class)->first();
        $metadata = $audit->metadata;

        $this->assertArrayHasKey('geometry', $metadata);
        $this->assertArrayHasKey('point_hash', $metadata['geometry']);
        $this->assertArrayHasKey('coordinate_count', $metadata['geometry']);
        $this->assertArrayHasKey('bbox', $metadata['geometry']);
    }
}
