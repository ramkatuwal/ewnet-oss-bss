<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Region;
use App\Models\Branch;
use App\Models\Site;
use App\Models\User;
use App\Models\Integration;
use App\Models\SiteExternalReference;
use App\Services\SiteMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibreNmsSiteMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_explicit_mapping_by_device_id()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id, 'site_code' => 'TEST-001']);
        $integration = Integration::factory()->create(['provider' => 'librenms']);
        
        // Create explicit reference
        SiteExternalReference::create([
            'site_id' => $site->id,
            'provider' => 'librenms',
            'external_type' => 'device',
            'external_id' => '999',
        ]);

        $service = new SiteMappingService();
        $result = $service->mapDevice(['device_id' => '999', 'hostname' => 'other-host'], $integration);

        $this->assertEquals('mapped', $result['status']);
        $this->assertEquals($site->id, $result['site_id']);
    }

    public function test_heuristic_mapping_by_hostname()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id, 'site_code' => 'KTM-POP-001']);
        $integration = Integration::factory()->create(['provider' => 'librenms']);

        $service = new SiteMappingService();
        $result = $service->mapDevice(['device_id' => '123', 'hostname' => 'ktm-pop-001'], $integration);

        $this->assertEquals('mapped', $result['status']);
        $this->assertEquals($site->id, $result['site_id']);
        $this->assertDatabaseHas('site_external_references', [
            'site_id' => $site->id,
            'external_id' => '123',
        ]);
    }

    public function test_heuristic_mapping_by_location_name()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id, 'name' => 'Kathmandu Core']);
        $integration = Integration::factory()->create(['provider' => 'librenms']);

        $service = new SiteMappingService();
        $result = $service->mapDevice(['device_id' => '456', 'hostname' => 'router-1', 'location' => 'Kathmandu Core'], $integration);

        $this->assertEquals('mapped', $result['status']);
        $this->assertEquals($site->id, $result['site_id']);
    }

    public function test_unmapped_device()
    {
        $integration = Integration::factory()->create(['provider' => 'librenms']);
        $service = new SiteMappingService();
        
        $result = $service->mapDevice(['device_id' => '789', 'hostname' => 'unknown-host'], $integration);

        $this->assertEquals('unmapped', $result['status']);
        $this->assertNull($result['site_id']);
    }

    public function test_gps_enrichment_for_site_without_gps()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id, 'latitude' => null, 'longitude' => null]);
        $integration = Integration::factory()->create(['provider' => 'librenms']);

        $service = new SiteMappingService();
        $service->mapDevice([
            'device_id' => '101', 
            'hostname' => $site->site_code, 
            'lat' => 27.7172, 
            'lng' => 85.3240
        ], $integration);

        $site->refresh();
        $this->assertEquals(27.7172, $site->latitude);
        $this->assertEquals(85.3240, $site->longitude);
    }

    public function test_gps_not_overwritten_if_already_present()
    {
        $company = Company::factory()->create();
        $site = Site::factory()->create(['company_id' => $company->id, 'latitude' => 10.0, 'longitude' => 10.0]);
        $integration = Integration::factory()->create(['provider' => 'librenms']);

        $service = new SiteMappingService();
        $service->mapDevice([
            'device_id' => '102', 
            'hostname' => $site->site_code, 
            'lat' => 20.0, 
            'lng' => 20.0
        ], $integration);

        $site->refresh();
        $this->assertEquals(10.0, $site->latitude); // Should remain unchanged
    }
}
