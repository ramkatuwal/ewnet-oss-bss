<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_user_can_update_profile(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/profile', [
                'name' => 'Updated Name',
                'phone_number' => '+1234567890',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone_number', '+1234567890');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Updated Name',
            'phone_number' => '+1234567890',
        ]);
    }

    public function test_user_cannot_update_email_to_existing_email(): void
    {
        $otherUser = User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/profile', [
                'email' => 'taken@example.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_change_password(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/profile/password', [
                'current_password' => 'password123',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password changed successfully']);

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->password));
        $this->assertNotNull($this->user->password_changed_at);
    }

    public function test_user_cannot_change_password_with_wrong_current_password(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/profile/password', [
                'current_password' => 'wrongpassword',
                'new_password' => 'newpassword123',
                'new_password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_new_password_must_be_different_from_current(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/profile/password', [
                'current_password' => 'password123',
                'new_password' => 'password123',
                'new_password_confirmation' => 'password123',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 500, 500);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Avatar uploaded successfully']);

        $this->user->refresh();
        $this->assertNotNull($this->user->avatar);
        Storage::disk('public')->assertExists($this->user->avatar);
    }

    public function test_avatar_must_be_valid_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_must_not_exceed_max_size(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg')->size(3000); // 3MB

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_user_can_remove_avatar(): void
    {
        Storage::fake('public');

        // First upload an avatar
        $file = UploadedFile::fake()->image('avatar.jpg', 500, 500);
        $path = $file->store('avatars', 'public');
        $this->user->update(['avatar' => $path]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/profile/avatar');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Avatar removed successfully']);

        $this->user->refresh();
        $this->assertNull($this->user->avatar);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_unauthenticated_user_cannot_access_profile_endpoints(): void
    {
        $this->putJson('/api/v1/profile', ['name' => 'Test'])
            ->assertStatus(401);

        $this->postJson('/api/v1/profile/password', [
            'current_password' => 'test',
            'new_password' => 'test1234',
            'new_password_confirmation' => 'test1234',
        ])->assertStatus(401);
        
        $this->deleteJson('/api/v1/profile/avatar')
            ->assertStatus(401);
    }
}
