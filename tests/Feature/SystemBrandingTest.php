<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SystemBrandingTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->user->givePermissionTo('system.info.view');

        UserManagementScope::create([
            'user_id' => $this->user->id,
            'scope_type' => 'company',
            'scope_id' => $this->company->id,
            'granted_by' => $this->user->id,
        ]);
        $this->user->refresh();

        Storage::fake('public');
    }

    private function uploadBranding(array $payload): TestResponse
    {
        return $this->withHeader('Accept', 'application/json')
            ->post('/api/v1/system/branding', $payload);
    }

    public function test_unauthorized_user_cannot_upload_branding(): void
    {
        $plainUser = User::factory()->create(['company_id' => $this->company->id]);

        $response = $this->actingAs($plainUser)->post('/api/v1/system/branding', [
            'type' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);

        $response->assertStatus(403);
    }

    public function test_can_upload_application_logo(): void
    {
        $response = $this->actingAs($this->user)->uploadBranding([
            'type' => 'logo',
            'file' => UploadedFile::fake()->image('brand-logo.png', 200, 120),
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('type', 'logo');
        $response->assertJsonPath('message', 'Logo uploaded successfully.');

        $path = $response->json('path');
        $this->assertStringStartsWith('logos/', $path);
        Storage::disk('public')->assertExists($path);

        $this->assertSame($path, SystemSetting::where('key', 'logo_path')->value('value'));
    }

    public function test_can_upload_browser_favicon(): void
    {
        $response = $this->actingAs($this->user)->uploadBranding([
            'type' => 'favicon',
            'file' => UploadedFile::fake()->create('favicon.ico', 10, 'image/x-icon'),
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('type', 'favicon');

        $path = $response->json('path');
        $this->assertStringStartsWith('favicons/', $path);
        Storage::disk('public')->assertExists($path);

        $this->assertSame($path, SystemSetting::where('key', 'favicon_path')->value('value'));
    }

    public function test_rejects_executable_file_upload(): void
    {
        $response = $this->actingAs($this->user)->uploadBranding([
            'type' => 'logo',
            'file' => UploadedFile::fake()->create('evil.php', 30, 'text/x-php'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['file']);

        Storage::disk('public')->assertDirectoryEmpty('logos');
        $this->assertDatabaseMissing('system_settings', ['key' => 'logo_path']);
    }

    public function test_replaces_previous_file_on_reupload(): void
    {
        $first = $this->actingAs($this->user)->uploadBranding([
            'type' => 'logo',
            'file' => UploadedFile::fake()->image('logo-v1.png', 200, 120),
        ]);
        $firstPath = $first->json('path');
        Storage::disk('public')->assertExists($firstPath);

        $second = $this->actingAs($this->user)->uploadBranding([
            'type' => 'logo',
            'file' => UploadedFile::fake()->image('logo-v2.png', 300, 180),
        ]);
        $secondPath = $second->json('path');

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_public_branding_endpoint_returns_absolute_logo_url(): void
    {
        $this->actingAs($this->user)->uploadBranding([
            'type' => 'logo',
            'file' => UploadedFile::fake()->image('brand.png', 200, 120),
        ]);

        $response = $this->get('/api/v1/branding');

        $response->assertStatus(200);
        $response->assertJsonPath('data.app_name', 'EWNET');
        $this->assertStringStartsWith('http', $response->json('data.logo_path'));
        $this->assertStringContainsString('/storage/logos/', $response->json('data.logo_path'));
    }

    public function test_logo_and_favicon_persist_across_settings_reloads(): void
    {
        $this->actingAs($this->user)->uploadBranding([
            'type' => 'logo',
            'file' => UploadedFile::fake()->image('logo.png', 200, 120),
        ]);
        $this->actingAs($this->user)->uploadBranding([
            'type' => 'favicon',
            'file' => UploadedFile::fake()->create('favicon.ico', 10, 'image/x-icon'),
        ]);

        $logo = SystemSetting::where('key', 'logo_path')->value('value');
        $favicon = SystemSetting::where('key', 'favicon_path')->value('value');
        $this->assertNotNull($logo);
        $this->assertNotNull($favicon);
    }
}
