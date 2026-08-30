<?php

namespace Tests\Unit\Integrations;

use App\Integrations\Providers\LibreNMS\LibreNMSProvider;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use App\Models\LibreNmsObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LibreNMSPortSyncTest extends TestCase
{
    use RefreshDatabase;

    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = Integration::create([
            'name' => 'Port Sync Test',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'status' => 'active',
            'configuration' => ['endpoint' => 'https://nms.example.com', 'timeout' => 10],
        ]);
        $cred = new IntegrationCredential([
            'integration_id' => $this->integration->id,
            'credential_type' => 'api_token',
            'label' => 'Test',
            'is_active' => true,
        ]);
        $cred->setSecretValue('test-token');
        $cred->save();
    }

    private function objectCount(): int
    {
        return LibreNmsObject::where('integration_id', $this->integration->id)->count();
    }

    public function test_port_sync_creates_ports_with_full_data(): void
    {
        LibreNmsObject::create([
            'integration_id' => $this->integration->id,
            'object_type' => 'device',
            'external_id' => '5',
            'data' => ['hostname' => 'router1'],
            'display_name' => 'router1',
        ]);

        Http::fake([
            '*/devices/5/ports*' => Http::response([
                'status' => 'ok',
                'ports' => [
                    ['port_id' => '100', 'device_id' => '5', 'ifIndex' => 1, 'ifName' => 'eth0', 'ifDescr' => 'Ethernet0', 'ifSpeed' => 1000000000, 'ifOperStatus' => 'up'],
                    ['port_id' => '101', 'device_id' => '5', 'ifIndex' => 2, 'ifName' => 'eth1', 'ifDescr' => 'Ethernet1', 'ifSpeed' => 100000000, 'ifOperStatus' => 'down'],
                ],
            ]),
            '*/devices*' => Http::response(['status' => 'ok', 'devices' => []]),
            '*/alerts*' => Http::response(['status' => 'ok', 'alerts' => []]),
            '*/pollers*' => Http::response(['status' => 'ok', 'pollers' => []]),
        ]);

        $provider = new LibreNMSProvider();
        $counts = $provider->synchronize($this->integration);

        $this->assertSame(2, $counts['created']);
        $this->assertSame(0, $counts['failed']);
        $this->assertSame(0, $counts['skipped']);
        $this->assertSame(3, $this->objectCount()); // 1 device + 2 ports

        $port = LibreNmsObject::where('integration_id', $this->integration->id)
            ->where('external_id', '100')->first();
        $this->assertSame('port', $port->object_type);
        $this->assertSame('5', $port->external_parent_id);
        $this->assertSame('eth0', $port->display_name);
        $this->assertSame('up', $port->status);
        $this->assertArrayHasKey('port_id', $port->data);
        $this->assertArrayHasKey('ifSpeed', $port->data);
    }

    public function test_port_sync_404_counted_as_skipped_not_failed(): void
    {
        LibreNmsObject::create([
            'integration_id' => $this->integration->id,
            'object_type' => 'device',
            'external_id' => '999',
            'data' => ['hostname' => 'stale-device'],
            'display_name' => 'stale-device',
        ]);

        Http::fake([
            '*/devices/999/ports*' => Http::response(['status' => 'error'], 404),
            '*/devices*' => Http::response(['status' => 'ok', 'devices' => []]),
            '*/alerts*' => Http::response(['status' => 'ok', 'alerts' => []]),
            '*/pollers*' => Http::response(['status' => 'ok', 'pollers' => []]),
        ]);

        $provider = new LibreNMSProvider();
        $counts = $provider->synchronize($this->integration);

        $this->assertSame(1, $counts['skipped']);
        $this->assertSame(0, $counts['failed']);
        $this->assertSame(0, $counts['created']);
    }

    public function test_port_sync_idempotency_second_run(): void
    {
        LibreNmsObject::create([
            'integration_id' => $this->integration->id,
            'object_type' => 'device',
            'external_id' => '5',
            'data' => ['hostname' => 'router1'],
            'display_name' => 'router1',
        ]);

        $portData = [
            'status' => 'ok',
            'ports' => [
                ['port_id' => '100', 'device_id' => '5', 'ifIndex' => 1, 'ifName' => 'eth0', 'ifDescr' => 'Ethernet0', 'ifSpeed' => 1000000000, 'ifOperStatus' => 'up'],
            ],
        ];

        Http::fake([
            '*/devices/5/ports*' => Http::response($portData),
            '*/devices*' => Http::response(['status' => 'ok', 'devices' => []]),
            '*/alerts*' => Http::response(['status' => 'ok', 'alerts' => []]),
            '*/pollers*' => Http::response(['status' => 'ok', 'pollers' => []]),
        ]);

        $provider = new LibreNMSProvider();

        // First sync: creates port
        $first = $provider->synchronize($this->integration);
        $this->assertSame(1, $first['created']);
        $this->assertSame(2, $this->objectCount()); // 1 device + 1 port

        // Second sync: unchanged
        $second = $provider->synchronize($this->integration);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['unchanged']);
        $this->assertSame(2, $this->objectCount()); // No duplicates
    }

    public function test_port_sync_auth_failure_counted_as_failed(): void
    {
        LibreNmsObject::create([
            'integration_id' => $this->integration->id,
            'object_type' => 'device',
            'external_id' => '5',
            'data' => ['hostname' => 'router1'],
            'display_name' => 'router1',
        ]);

        Http::fake([
            '*/devices/5/ports*' => Http::response(['message' => 'Unauthorized'], 401),
            '*/devices*' => Http::response(['status' => 'ok', 'devices' => []]),
            '*/alerts*' => Http::response(['status' => 'ok', 'alerts' => []]),
            '*/pollers*' => Http::response(['status' => 'ok', 'pollers' => []]),
        ]);

        $provider = new LibreNMSProvider();
        $counts = $provider->synchronize($this->integration);

        $this->assertSame(1, $counts['failed']);
        $this->assertSame(0, $counts['skipped']);
    }

    public function test_no_duplicate_api_requests_per_device(): void
    {
        LibreNmsObject::create([
            'integration_id' => $this->integration->id,
            'object_type' => 'device',
            'external_id' => '5',
            'data' => ['hostname' => 'router1'],
            'display_name' => 'router1',
        ]);

        Http::fake([
            '*/devices/5/ports*' => Http::response(['status' => 'ok', 'ports' => []]),
            '*/devices*' => Http::response(['status' => 'ok', 'devices' => []]),
            '*/alerts*' => Http::response(['status' => 'ok', 'alerts' => []]),
            '*/pollers*' => Http::response(['status' => 'ok', 'pollers' => []]),
        ]);

        $provider = new LibreNMSProvider();
        $provider->synchronize($this->integration);

        // Exactly one request per endpoint: devices, ports, alerts, pollers
        Http::assertSentCount(4);
    }
}
