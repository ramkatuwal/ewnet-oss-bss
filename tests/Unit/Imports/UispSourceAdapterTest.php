<?php

namespace Tests\Unit\Imports;

use Tests\TestCase;
use App\Services\Imports\UispSourceAdapter;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UispSourceAdapterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test extractInterfaces method directly using reflection
     */
    public function test_extract_interfaces_from_uisp_device()
    {
        // Create the integration for the test
        $integration = Integration::create([
            'name' => 'Test UISP',
            'provider' => 'uisp',
            'type' => 'monitoring',
            'enabled' => true,
            'configuration' => ['api_url' => 'https://unms.test/api.v2.1'],
        ]);

        // We need to test the extractInterfaces method without the client
        // Since the method doesn't actually need the client, we can create a test double
        // using a simple anonymous class that extends the parent but skips the client setup
        $adapter = new class($integration) extends UispSourceAdapter {
            private $integrationData;
            
            public function __construct($integration) {
                // Store the integration data without calling parent constructor
                $this->integrationData = $integration;
            }
            
            // Expose the protected method for testing
            public function testExtractInterfaces($device) {
                return $this->extractInterfaces($device);
            }
            
            public function testExtractIpAddresses($device) {
                return $this->extractIpAddresses($device);
            }
        };

        $device = [
            'id' => 'device-123',
            'name' => 'Test Router',
            'interfaces' => [
                [
                    'id' => 'if-1',
                    'name' => 'eth0',
                    'type' => 'ethernet',
                    'mac' => 'aa:bb:cc:dd:ee:ff',
                    'speed' => 1000000000,
                    'status' => 'up',
                    'ip_addresses' => [
                        [
                            'id' => 'ip-1',
                            'address' => '192.168.1.1',
                            'prefix' => 24,
                            'primary' => true,
                        ]
                    ]
                ],
                [
                    'id' => 'if-2',
                    'name' => 'eth1',
                    'type' => 'ethernet',
                    'mac' => '11:22:33:44:55:66',
                    'speed' => 100000000,
                    'status' => 'down',
                ]
            ]
        ];

        $interfaces = $adapter->testExtractInterfaces($device);

        $this->assertCount(2, $interfaces);
        $this->assertEquals('eth0', $interfaces[0]['name']);
        $this->assertEquals('aa:bb:cc:dd:ee:ff', $interfaces[0]['mac_address']);
        $this->assertEquals('uisp', $interfaces[0]['provider']);
        $this->assertEquals('if-1', $interfaces[0]['external_id']);
        $this->assertCount(1, $interfaces[0]['ip_addresses']);
        $this->assertEquals('192.168.1.1', $interfaces[0]['ip_addresses'][0]['ip']);
    }

    public function test_extract_management_ip_from_uisp_device()
    {
        $integration = Integration::create([
            'name' => 'Test UISP',
            'provider' => 'uisp',
            'type' => 'monitoring',
            'enabled' => true,
            'configuration' => ['api_url' => 'https://unms.test/api.v2.1'],
        ]);

        $adapter = new class($integration) extends UispSourceAdapter {
            private $integrationData;
            
            public function __construct($integration) {
                $this->integrationData = $integration;
            }
            
            public function testExtractInterfaces($device) {
                return $this->extractInterfaces($device);
            }
        };

        $device = [
            'id' => 'device-456',
            'name' => 'Core Router',
            'management_ip' => '10.10.10.1',
            'interfaces' => []
        ];

        $interfaces = $adapter->testExtractInterfaces($device);

        $managementInterfaces = array_filter($interfaces, fn($i) => $i['name'] === 'management');
        $this->assertCount(1, $managementInterfaces);
        
        $mgmt = array_values($managementInterfaces)[0];
        $this->assertEquals('management', $mgmt['name']);
        $this->assertEquals('Management IP', $mgmt['display_name']);
        $this->assertCount(1, $mgmt['ip_addresses']);
        $this->assertEquals('10.10.10.1', $mgmt['ip_addresses'][0]['ip']);
        $this->assertTrue($mgmt['ip_addresses'][0]['is_management']);
    }

    public function test_extract_ip_addresses_from_uisp_device()
    {
        $integration = Integration::create([
            'name' => 'Test UISP',
            'provider' => 'uisp',
            'type' => 'monitoring',
            'enabled' => true,
            'configuration' => ['api_url' => 'https://unms.test/api.v2.1'],
        ]);

        $adapter = new class($integration) extends UispSourceAdapter {
            private $integrationData;
            
            public function __construct($integration) {
                $this->integrationData = $integration;
            }
            
            public function testExtractIpAddresses($device) {
                return $this->extractIpAddresses($device);
            }
        };

        $device = [
            'id' => 'device-789',
            'name' => 'Router with IP',
            'ip' => '10.10.10.2',
            'interfaces' => []
        ];

        $ips = $adapter->testExtractIpAddresses($device);

        $this->assertCount(1, $ips);
        $this->assertEquals('10.10.10.2', $ips[0]['ip']);
        $this->assertTrue($ips[0]['is_management']);
        $this->assertEquals('uisp', $ips[0]['provider']);
    }
}
