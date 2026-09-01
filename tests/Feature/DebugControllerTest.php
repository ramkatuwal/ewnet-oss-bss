<?php
namespace Tests\Feature;
use App\Models\User;
use Tests\TestCase;
class DebugControllerTest extends TestCase
{
    public function test_unauthorized_cannot_view_logs() {
        $this->getJson('/api/v1/debug/summary')->assertStatus(401);
    }
    public function test_authorized_can_view_summary() {
        $user = User::factory()->create();
        $user->givePermissionTo('system.debug.view');
        $this->actingAs($user);
        $this->getJson('/api/v1/debug/summary')->assertStatus(200);
    }
}
