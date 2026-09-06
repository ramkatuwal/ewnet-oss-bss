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

    public function test_saving_absolute_uploaded_url_stores_relative_path(): void
    {
        SystemSetting::updateOrCreate(['key' => 'logo_path'], [
            'value' => 'logos/abc.png',
            'group' => 'branding',
        ]);

        // The configuration form echoes back the absolute URL from the upload response
        $response = $this->actingAs($this->user)->putJson('/api/v1/system/configuration', [
            'branding' => [
                'logo_path' => 'https://oss.ewnet.com.np/storage/logos/abc.png',
            ],
        ]);

        $response->assertStatus(200);
        $this->assertSame(
            'logos/abc.png',
            SystemSetting::where('key', 'logo_path')->value('value')
        );
    }

    public function test_legacy_compounded_logo_path_is_cleaned_on_read(): void
    {
        SystemSetting::updateOrCreate(['key' => 'logo_path'], [
            'value' => 'https://oss.ewnet.com.np/https://oss.ewnet.com.np/https://oss.ewnet.com.np/logos/xyz.png',
            'group' => 'branding',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/system/configuration');

        $response->assertStatus(200);
        $logoPath = $response->json('data.branding.logo_path');
        $this->assertStringEndsWith('/storage/logos/xyz.png', $logoPath);
        $this->assertSame(1, substr_count($logoPath, '/storage/'));
        $this->assertStringNotContainsString('https://', $logoPath);
    }

    public function test_reading_relative_path_returns_single_storage_prefix(): void
    {
        SystemSetting::updateOrCreate(['key' => 'logo_path'], [
            'value' => 'logos/single.png',
            'group' => 'branding',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/system/configuration');

        $response->assertStatus(200);
        $this->assertStringEndsWith('/storage/logos/single.png', $response->json('data.branding.logo_path'));
    }

    public function test_repeated_save_cycles_never_compound_the_path(): void
    {
        // Simulate three full upload + save cycles with the field echoing absolute URLs
        foreach (['cycle-a', 'cycle-b', 'cycle-c'] as $filename) {
            $this->actingAs($this->user)->uploadBranding([
                'type' => 'logo',
                'file' => UploadedFile::fake()->image($filename.'.png', 200, 120),
            ]);

            $stored = SystemSetting::where('key', 'logo_path')->value('value');
            $absolute = url('storage/'.$stored);

            $this->actingAs($this->user)->putJson('/api/v1/system/configuration', [
                'branding' => ['logo_path' => $absolute],
            ]);
        }

        $stored = SystemSetting::where('key', 'logo_path')->value('value');
        $this->assertStringStartsWith('logos/', $stored);
        $this->assertStringNotContainsString('storage/', $stored);
        $this->assertStringNotContainsString('http', $stored);

        $response = $this->actingAs($this->user)->getJson('/api/v1/system/configuration');
        $this->assertStringEndsWith('/storage/'.$stored, $response->json('data.branding.logo_path'));
        $this->assertSame(1, substr_count($response->json('data.branding.logo_path'), '/storage/'));
    }
}
