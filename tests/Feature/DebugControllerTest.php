<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unauthorized_cannot_view_logs(): void
    {
        $this->getJson('/api/v1/debug/summary')->assertStatus(401);
    }

    public function test_authorized_can_view_summary(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('system.debug.view');
        $this->actingAs($user);
        $this->getJson('/api/v1/debug/summary')->assertStatus(200);
    }
}
