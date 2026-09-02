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
        $this->assertEquals('connected', $result['status']);
        
        Http::assertSent(function ($request) {
            return $request->hasHeader('x-auth-token', 'test-token-12345');
        });
    }

    public function test_synchronize_sites_and_devices_success()
    {
        Http::fake([
            '*/nms/api/v2.1/sites' => Http::response([
                [
                    'id' => 'uisp-site-001',
                    'identification' => [
                        'name' => 'Test Site Alpha',
                        'status' => 'active',
                    ]
                ]
            ], 200),
            '*/nms/api/v2.1/devices' => Http::response([
                [
                    'id' => 'uisp-device-001',
                    'identification' => [
                        'id' => 'uisp-device-001',
                        'name' => 'Test Router',
                        'model' => 'EdgeRouter X',
                        'vendor' => 'Ubiquiti',
                        'mac' => 'AA:BB:CC:DD:EE:FF',
                        'firmwareVersion' => 'v2.0.0',
                        'site' => [
                            'id' => 'uisp-site-001'
                        ]
                    ],
                    'status' => 'online',
                ]
            ], 200),
        ]);

        $result = $this->provider->synchronize($this->integration);
        $this->assertEquals('completed', $result['status']);
        
        // Verify MAC is in specifications, NOT serial_number
        $asset = \App\Models\Asset::where('model', 'EdgeRouter X')->first();
        $this->assertNotNull($asset);
        $this->assertEquals('AA:BB:CC:DD:EE:FF', $asset->specifications['mac_address']);
        $this->assertNull($asset->serial_number); // Ensure serial_number is not misused for MAC
        
        $this->assertDatabaseHas('asset_external_references', [
            'provider' => 'uisp',
            'external_type' => 'device',
            'external_id' => 'uisp-device-001',
        ]);
    }
}
