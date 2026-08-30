<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\Site;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_can_import_csv_file()
    {
        $company = Company::factory()->create(['name' => 'Test Company']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.import');
        
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        $csvContent = "site_code,name,type,status,company_name\nTEST-IMP-001,Test Import Site,pop,active,Test Company";
        $file = UploadedFile::fake()->createWithContent('sites.csv', $csvContent);

        $response = $this->actingAs($user)->postJson('/api/v1/sites/import', [
            'file' => $file,
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('message', 'Import queued successfully. Check Horizon for status.');
    }

    public function test_cannot_import_without_permission()
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('sites.csv', 'dummy content');

        $response = $this->actingAs($user)->postJson('/api/v1/sites/import', [
            'file' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_can_export_sites_to_csv()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('sites.export');
        
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company->id,
            'granted_by' => $user->id,
        ]);

        Site::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/sites/export?format=csv');

        // StreamedResponse doesn't return JSON, so we check status
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_cannot_export_without_permission()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/sites/export?format=csv');

        $response->assertStatus(403);
    }

    public function test_export_respects_management_scope()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        
        $user = User::factory()->create(['company_id' => $company1->id]);
        $user->givePermissionTo('sites.export');
        
        UserManagementScope::create([
            'user_id' => $user->id,
            'scope_type' => 'company',
            'scope_id' => $company1->id,
            'granted_by' => $user->id,
        ]);

        Site::factory()->create(['company_id' => $company1->id, 'site_code' => 'VISIBLE-001']);
        Site::factory()->create(['company_id' => $company2->id, 'site_code' => 'HIDDEN-001']);

        $response = $this->actingAs($user)->get('/api/v1/sites/export?format=csv');
        
        $content = $response->streamedContent();
        $this->assertStringContainsString('VISIBLE-001', $content);
        $this->assertStringNotContainsString('HIDDEN-001', $content);
    }
}
