<?php

namespace Tests\Unit\Integrations;

use App\Integrations\Providers\LibreNMS\LibreNMSClient;
use App\Models\Integration;
use App\Models\IntegrationCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LibreNMSClientTest extends TestCase
{
    use RefreshDatabase;

    private Integration $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = Integration::create([
            'name' => 'Test LibreNMS',
            'provider' => 'librenms',
            'type' => 'monitoring',
            'status' => 'pending',
            'configuration' => ['endpoint' => 'https://nms.example.com', 'timeout' => 10, 'tls_verify' => true],
        ]);
        $cred = new IntegrationCredential([
            'integration_id' => $this->integration->id,
            'credential_type' => 'api_token',
            'label' => 'Test',
            'is_active' => true,
        ]);
        $cred->setSecretValue('test-token-abc');
        $cred->save();
    }

    private function client(): LibreNMSClient
    {
        return new LibreNMSClient($this->integration);
    }

    public function test_ping_success(): void
    {
        Http::fake(['*' => Http::response(['message' => 'pong'], 200)]);
        $result = $this->client()->ping();
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('response_time_ms', $result);
    }

    public function test_get_device_ports_with_columns(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'ok',
            'ports' => [
                ['port_id' => '100', 'device_id' => '5', 'ifIndex' => 1, 'ifName' => 'eth0', 'ifDescr' => 'Ethernet0', 'ifSpeed' => 1000000000, 'ifOperStatus' => 'up'],
                ['port_id' => '101', 'device_id' => '5', 'ifIndex' => 2, 'ifName' => 'eth1', 'ifDescr' => 'Ethernet1', 'ifSpeed' => 1000000000, 'ifOperStatus' => 'down'],
            ],
        ], 200)]);

        $result = $this->client()->getDevicePorts('5');
        $this->assertSame(200, $result['status']);
        $this->assertCount(2, $result['ports']);
        $this->assertSame('100', $result['ports'][0]['port_id']);
        $this->assertSame('eth0', $result['ports'][0]['ifName']);
    }

    public function test_get_device_ports_404_returns_skipped_status(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'message' => 'Device not found'], 404)]);
        $result = $this->client()->getDevicePorts('999');
        $this->assertSame(404, $result['status']);
        $this->assertEmpty($result['ports']);
    }

    public function test_auth_failure_throws(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated'], 401)]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('authentication failed');
        $this->client()->ping();
    }

    public function test_rate_limit_throws(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Rate limited'], 429)]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rate limit');
        $this->client()->ping();
    }

    public function test_server_error_throws(): void
    {
        Http::fake(['*' => Http::response(['message' => 'Internal error'], 500)]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('server error');
        $this->client()->ping();
    }

    public function test_connection_failure_throws(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('DNS failure');
        });
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('connection failed');
        $this->client()->ping();
    }

    public function test_list_devices_returns_array(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'ok',
            'devices' => [
                ['device_id' => '1', 'hostname' => 'router1'],
                ['device_id' => '2', 'hostname' => 'switch1'],
            ],
        ], 200)]);
        $devices = $this->client()->listDevices();
        $this->assertCount(2, $devices);
        $this->assertSame('router1', $devices[0]['hostname']);
    }

    public function test_list_alerts_returns_array(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'ok',
            'alerts' => [['alert_id' => '10', 'severity' => 'critical']],
        ], 200)]);
        $alerts = $this->client()->listAlerts();
        $this->assertCount(1, $alerts);
        $this->assertSame('critical', $alerts[0]['severity']);
    }

    public function test_get_pollers_returns_array(): void
    {
        Http::fake(['*' => Http::response([
            'status' => 'ok',
            'pollers' => [['id' => '1', 'poller_name' => 'poller-01']],
        ], 200)]);
        $pollers = $this->client()->getPollers();
        $this->assertCount(1, $pollers);
        $this->assertSame('poller-01', $pollers[0]['poller_name']);
    }
}
