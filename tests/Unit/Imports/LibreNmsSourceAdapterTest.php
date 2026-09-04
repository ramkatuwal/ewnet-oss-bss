<?php

namespace Tests\Unit\Imports;

use Tests\TestCase;
use App\Services\Imports\LibreNmsSourceAdapter;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LibreNmsSourceAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected LibreNmsSourceAdapter $adapter;
    protected Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->integration = Integration::create([
            'name' => 'Test LibreNMS',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'enabled' => true,
            'configuration' => ['api_url' => 'https://nms.test/api/v0'],
        ]);

        $this->adapter = new LibreNmsSourceAdapter($this->integration);
    }

    public function test_normalize_device_with_display_name()
    {
        $device = [
            'device_id' => 3,
            'hostname' => '103.114.24.1',
            'sysName' => 'surkhet_edge_gt',
            'display' => 'SKT-CORE-NAT-1',
            'ip' => '103.114.24.1',
            'serial' => 'HEZ09FDKBJ1',
            'hardware' => 'CCR2216-1G-12XS-2XQ',
            'os' => 'routeros',
            'status' => 1,
        ];

        $record = $this->adapter->normalizeDevice($device);

        $this->assertEquals('librenms', $record->provider);
        $this->assertEquals('3', $record->externalId);
        $this->assertEquals('SKT-CORE-NAT-1', $record->name);
        $this->assertEquals('HEZ09FDKBJ1', $record->serialNumber);
        $this->assertEquals('CCR2216-1G-12XS-2XQ', $record->model);
        $this->assertEquals('103.114.24.1', $record->ipAddress);
    }

    public function test_display_name_fallback_to_sysname()
    {
        $device = [
            'device_id' => 4,
            'hostname' => '10.10.10.1',
            'sysName' => 'backup-router',
            'display' => null,
            'ip' => '10.10.10.1',
        ];

        $record = $this->adapter->normalizeDevice($device);

        $this->assertEquals('backup-router', $record->name);
    }

    public function test_display_name_fallback_to_hostname()
    {
        $device = [
            'device_id' => 5,
            'hostname' => '10.10.10.2',
            'sysName' => null,
            'display' => null,
            'ip' => '10.10.10.2',
        ];

        $record = $this->adapter->normalizeDevice($device);

        $this->assertEquals('10.10.10.2', $record->name);
    }
}
