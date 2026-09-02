<?php

namespace Tests\Feature\Integrations\Uisp;

use App\Integrations\Providers\Uisp\UispProvider;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UispProviderTest extends TestCase
{
    use RefreshDatabase;

    protected UispProvider $provider;
    protected Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new UispProvider();
        
        $this->integration = Integration::create([
            'name' => 'Test UISP',
            'provider' => 'uisp',
            'type' => 'monitoring',
            'configuration' => [
                'api_url' => 'https://unms.ewnet.com.np/nms/api/v2.1',
                'tls_verify' => true,
            ],
            'enabled' => true,
        ]);

        $credential = new IntegrationCredential([
            'integration_id' => $this->integration->id,
            'credential_type' => 'api_token',
            'label' => 'Primary Token',
            'is_active' => true,
        ]);
        $credential->setSecretValue('test-token-12345');
        $credential->save();
    }

    public function test_provider_identity()
    {
        $this->assertEquals('uisp', $this->provider->identity());
        $this->assertEquals('Ubiquiti UISP', $this->provider->displayName());
    }

    public function test_capabilities()
    {
        $capabilities = $this->provider->capabilities();
        $this->assertContains('sites', $capabilities);
        $this->assertContains('devices', $capabilities);
    }

    public function test_validate_configuration_success()
    {
        $errors = $this->provider->validateConfiguration([
            'api_url' => 'https://unms.ewnet.com.np/nms/api/v2.1',
        ]);
        $this->assertEmpty($errors);
    }

    public function test_validate_configuration_missing_url()
    {
        $errors = $this->provider->validateConfiguration([]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('api_url', $errors[0]);
    }

    public function test_validate_configuration_http_in_production()
    {
        // Simulate production environment
        $this->app['env'] = 'production';
        $errors = $this->provider->validateConfiguration([
            'api_url' => 'http://unms.ewnet.com.np/nms/api/v2.1',
        ]);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('HTTPS', $errors[0]);
    }

    public function test_health_check_success()
    {
        Http::fake([
            '*/nms/heartbeat' => Http::response(['result' => true], 200),
        ]);

        $result = $this->provider->healthCheck($this->integration);
        
        if ($result['status'] !== 'connected') {
            dump('Health Check Error:', $result['error'] ?? 'Unknown error');
        }

        $this->assertEquals('connected', $result['status']);
        
        Http::assertSent(function ($request) {
            return $request->hasHeader('x-auth-token', 'test-token-12345');
        });
    }

    public function test_health_check_failure()
    {
        Http::fake([
            '*/nms/heartbeat' => Http::response([], 500),
        ]);

        $result = $this->provider->healthCheck($this->integration);
        $this->assertEquals('failed', $result['status']);
    }

    public function test_synchronize_sites_success()
    {
        Http::fake([
            '*/nms/api/v2.1/sites' => Http::response([
                [
                    'id' => 'uisp-site-001',
                    'name' => 'Test Site Alpha',
                    'status' => 'active',
                    'location' => ['lat' => 28.6, 'lon' => 81.6],
                    'address' => ['fullAddress' => 'Surkhet, Nepal'],
                ]
            ], 200),
        ]);

        $result = $this->provider->synchronize($this->integration);
        
        if ($result['status'] !== 'completed') {
            dump('Sync Error:', $result['error'] ?? 'Unknown error');
        }

        $this->assertEquals('completed', $result['status']);
        $this->assertGreaterThan(0, $result['counts']['created'] + $result['counts']['updated']);
        
        $this->assertDatabaseHas('sites', ['name' => 'Test Site Alpha']);
        $this->assertDatabaseHas('site_external_references', [
            'provider' => 'uisp',
            'external_id' => 'uisp-site-001',
        ]);
    }
}
