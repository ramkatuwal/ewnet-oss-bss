<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugLogsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $company->id]);
        $this->user->givePermissionTo('system.debug.view');
    }

    public function test_unauthorized_cannot_access_paginated_logs(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/debug/logs?type=laravel&page=1')
            ->assertStatus(403);
    }

    public function test_invalid_log_type_returns_400_with_pagination(): void
    {
        $this->actingAs($this->user)
            ->getJson('/api/v1/debug/logs?type=mysql&page=1')
            ->assertStatus(400);
    }

    public function test_paginated_laravel_logs_return_data_and_meta_shape(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/debug/logs?type=laravel&page=1&per_page=50');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['*' => ['time', 'level', 'message', 'classification']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to', 'window'],
        ]);
        $response->assertJsonPath('meta.current_page', 1);
        $response->assertJsonPath('meta.per_page', 50);
    }

    public function test_paginated_nginx_logs_accept_status_and_search_filters(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/debug/logs?type=nginx&page=2&per_page=25&status_class=4xx&search=admin');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['*' => ['ip', 'time', 'method', 'path', 'status', 'classification']],
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
        ]);
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.per_page', 25);
    }

    public function test_laravel_logs_level_filter_is_accepted(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/debug/logs?type=laravel&page=1&level=error&date_from=2026-01-01&date_to=2026-12-31');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 50);
    }

    public function test_per_page_is_clamped_to_two_hundred(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/debug/logs?type=laravel&page=1&per_page=99999');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.per_page', 200);
    }

    public function test_window_is_clamped_to_max(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/debug/logs?type=nginx&page=1&window=999999');

        $response->assertStatus(200);
        $response->assertJsonPath('meta.window', 10000);
    }

    public function test_legacy_limit_contract_still_returns_flat_array(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/debug/logs?type=laravel&limit=100');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
    }
}
