<?php

namespace Tests\Unit\Imports;

use Tests\TestCase;
use App\Services\Imports\ReconciliationEngine;
use App\Dto\Imports\NormalizedRecord;
use App\Models\Asset;
use App\Models\AssetExternalReference;
use App\Models\AssetInterface;
use App\Models\IpAddress;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ReconciliationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->site = Site::create([
            'site_code' => 'TEST-SITE-' . time(),
            'name' => 'Test Site',
            'type' => 'pop',
            'status' => 'active',
            'description' => 'Test site for reconciliation',
        ]);
    }

    // ============================================================
    // EXISTING TESTS
    // ============================================================

    public function test_create_decision_when_no_match()
    {
        $engine = new ReconciliationEngine();

        $record = new NormalizedRecord();
        $record->sourceType = 'device';
        $record->provider = 'librenms';
        $record->externalId = 'nonexistent';
        $record->name = 'Test Device';

        $result = $engine->reconcile($record);

        $this->assertEquals('CREATE', $result['decision']);
        $this->assertNull($result['destination_id']);
    }

    public function test_link_decision_by_mac()
    {
        $asset = Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'MAC-TEST-001',
            'category' => 'NETWORK',
            'type' => 'device',
            'status' => 'OPERATIONAL',
            'condition' => 'GOOD',
            'quantity' => 1,
            'unit' => 'pcs',
            'specifications' => ['mac_address' => '00:11:22:33:44:55'],
            'description' => 'MAC Test Device',
        ]);

        AssetExternalReference::create([
            'asset_id' => $asset->id,
            'provider' => 'librenms',
            'external_type' => 'device',
            'external_id' => 'mac-test-123',
        ]);

        $engine = new ReconciliationEngine();

        $record = new NormalizedRecord();
        $record->sourceType = 'device';
        $record->provider = 'librenms';
        $record->externalId = 'mac-test-456';
        $record->macAddress = '00:11:22:33:44:55';
        $record->name = 'Same MAC Device';

        $result = $engine->reconcile($record);

        $this->assertEquals('LINK', $result['decision']);
        $this->assertEquals($asset->id, $result['destination_id']);
    }

    public function test_conflict_decision_by_mac_and_serial()
    {
        $asset1 = Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'CONFLICT-001',
            'category' => 'NETWORK',
            'type' => 'device',
            'status' => 'OPERATIONAL',
            'condition' => 'GOOD',
            'quantity' => 1,
            'unit' => 'pcs',
            'specifications' => ['mac_address' => 'AA:BB:CC:DD:EE:FF'],
            'description' => 'Conflict Device 1',
        ]);

        AssetExternalReference::create([
            'asset_id' => $asset1->id,
            'provider' => 'librenms',
            'external_type' => 'device',
            'external_id' => 'conflict-001',
        ]);

        $asset2 = Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'CONFLICT-002',
            'category' => 'NETWORK',
            'type' => 'device',
            'status' => 'OPERATIONAL',
            'condition' => 'GOOD',
            'quantity' => 1,
            'unit' => 'pcs',
            'serial_number' => 'SN-CONFLICT-123',
            'specifications' => ['mac_address' => '11:22:33:44:55:66'],
            'description' => 'Conflict Device 2',
        ]);

        AssetExternalReference::create([
            'asset_id' => $asset2->id,
            'provider' => 'librenms',
            'external_type' => 'device',
            'external_id' => 'conflict-002',
        ]);

        $engine = new ReconciliationEngine();

        $record = new NormalizedRecord();
        $record->sourceType = 'device';
        $record->provider = 'librenms';
        $record->externalId = 'conflict-003';
        $record->macAddress = 'AA:BB:CC:DD:EE:FF';
        $record->serialNumber = 'SN-CONFLICT-123';
        $record->name = 'Conflict Device 3';

        $result = $engine->reconcile($record);

        $this->assertEquals('CONFLICT', $result['decision']);
        $this->assertArrayHasKey('candidate_ids', $result);
        $this->assertCount(2, $result['candidate_ids']);
    }

    // ============================================================
    // PHASE 2.1 - INTERFACE IDEMPOTENCY TESTS
    // ============================================================

    public function test_interface_idempotency()
    {
        $asset = Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'IDEMPOTENT-ASSET-001',
            'category' => 'NETWORK',
            'type' => 'device',
            'status' => 'OPERATIONAL',
            'condition' => 'GOOD',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $interfaceData = [
            'name' => 'ge-0/0/0',
            'display_name' => 'Gigabit Ethernet 0/0/0',
            'type' => 'ethernet',
            'mac_address' => 'AA:BB:CC:DD:EE:11',
            'speed' => 1000000000,
            'status' => 'up',
            'provider' => 'uisp',
            'external_type' => 'interface',
            'external_id' => 'interface-123',
        ];

        $engine = new ReconciliationEngine();
        $result1 = $engine->reconcileInterface($interfaceData, $asset->id);
        $this->assertEquals('CREATE', $result1['decision']);

        $interface = AssetInterface::create([
            'asset_id' => $asset->id,
            'name' => $interfaceData['name'],
            'display_name' => $interfaceData['display_name'],
            'type' => $interfaceData['type'],
            'mac_address' => $interfaceData['mac_address'],
            'speed' => $interfaceData['speed'],
            'status' => $interfaceData['status'],
            'provider' => $interfaceData['provider'],
            'external_type' => $interfaceData['external_type'],
            'external_id' => $interfaceData['external_id'],
        ]);

        $engine2 = new ReconciliationEngine();
        $result2 = $engine2->reconcileInterface($interfaceData, $asset->id);
        $this->assertEquals('LINK', $result2['decision']);
        $this->assertEquals($interface->id, $result2['destination_id']);

        $count = AssetInterface::where('asset_id', $asset->id)->count();
        $this->assertEquals(1, $count);
    }

    // ============================================================
    // PHASE 2.1 - IP IDEMPOTENCY TESTS
    // ============================================================

    public function test_ip_idempotency()
    {
        $asset = Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'IP-IDEMPOTENT-001',
            'category' => 'NETWORK',
            'type' => 'device',
            'status' => 'OPERATIONAL',
            'condition' => 'GOOD',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $interface = AssetInterface::create([
            'asset_id' => $asset->id,
            'name' => 'ge-0/0/0',
            'type' => 'ethernet',
            'provider' => 'uisp',
            'external_type' => 'interface',
            'external_id' => 'interface-456',
        ]);

        $ipData = [
            'ip' => '192.168.1.1',
            'prefix' => 24,
            'is_primary' => true,
            'is_management' => true,
            'provider' => 'uisp',
            'external_type' => 'ip',
            'external_id' => 'ip-789',
        ];

        $engine = new ReconciliationEngine();
        $result1 = $engine->reconcileIpAddress($ipData, $interface->id, $asset->id);
        $this->assertEquals('CREATE', $result1['decision']);

        $ip = IpAddress::create([
            'asset_interface_id' => $interface->id,
            'ip_address' => $ipData['ip'],
            'prefix_length' => $ipData['prefix'],
            'is_primary' => $ipData['is_primary'],
            'is_management' => $ipData['is_management'],
            'provider' => $ipData['provider'],
            'external_type' => $ipData['external_type'],
            'external_id' => $ipData['external_id'],
        ]);

        $engine2 = new ReconciliationEngine();
        $result2 = $engine2->reconcileIpAddress($ipData, $interface->id, $asset->id);
        $this->assertEquals('LINK', $result2['decision']);
        $this->assertEquals($ip->id, $result2['destination_id']);

        $count = IpAddress::where('asset_interface_id', $interface->id)->count();
        $this->assertEquals(1, $count);
    }

    // ============================================================
    // PHASE 2.1 - THIRD IMPORT IDEMPOTENCY
    // ============================================================

    public function test_third_import_idempotency()
    {
        $asset = Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'THIRD-IMPORT-001',
            'category' => 'NETWORK',
            'type' => 'device',
            'status' => 'OPERATIONAL',
            'condition' => 'GOOD',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $interfaceData = [
            'name' => 'eth0',
            'type' => 'ethernet',
            'mac_address' => '11:22:33:44:55:66',
            'provider' => 'librenms',
            'external_type' => 'port',
            'external_id' => 'port-123',
        ];

        for ($i = 0; $i < 3; $i++) {
            $engine = new ReconciliationEngine();
            $result = $engine->reconcileInterface($interfaceData, $asset->id);

            if ($i === 0) {
                $this->assertEquals('CREATE', $result['decision']);
                AssetInterface::create([
                    'asset_id' => $asset->id,
                    'name' => $interfaceData['name'],
                    'type' => $interfaceData['type'],
                    'mac_address' => $interfaceData['mac_address'],
                    'provider' => $interfaceData['provider'],
                    'external_type' => $interfaceData['external_type'],
                    'external_id' => $interfaceData['external_id'],
                ]);
            } else {
                $this->assertEquals('LINK', $result['decision']);
            }
        }

        $interfaceCount = AssetInterface::where('asset_id', $asset->id)->count();
        $this->assertEquals(1, $interfaceCount);
    }

    // ============================================================
    // PHASE 2.1 - CROSS-PROVIDER RECONCILIATION
    // ============================================================

    public function test_cross_provider_reconciliation_same_device()
    {
        $asset = Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'CROSS-PROVIDER-001',
            'category' => 'NETWORK',
            'type' => 'device',
            'status' => 'OPERATIONAL',
            'condition' => 'GOOD',
            'quantity' => 1,
            'unit' => 'pcs',
            'serial_number' => 'SERIAL-123',
            'specifications' => ['mac_address' => 'AA:BB:CC:DD:EE:FF'],
        ]);

        AssetExternalReference::create([
            'asset_id' => $asset->id,
            'provider' => 'uisp',
            'external_type' => 'device',
            'external_id' => 'UISP-DEVICE-123',
        ]);

        $record = new NormalizedRecord();
        $record->sourceType = 'device';
        $record->provider = 'librenms';
        $record->externalId = 'LIBRENMS-DEVICE-456';
        $record->serialNumber = 'SERIAL-123';
        $record->macAddress = 'AA:BB:CC:DD:EE:FF';
        $record->name = 'SKT-CORE-NAT-1';
        $record->ipAddress = '10.10.10.10';

        $engine = new ReconciliationEngine();
        $result = $engine->reconcile($record);

        // Should LINK to the existing asset
        $this->assertEquals('LINK', $result['decision']);
        $this->assertEquals($asset->id, $result['destination_id']);
    }

    // ============================================================
    // PHASE 2.1 - CONCURRENT IMPORT PROTECTION
    // ============================================================

    public function test_concurrent_import_protection()
    {
        $asset = Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'CONCURRENT-001',
            'category' => 'NETWORK',
            'type' => 'device',
            'status' => 'OPERATIONAL',
            'condition' => 'GOOD',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $interfaceData = [
            'name' => 'eth0',
            'type' => 'ethernet',
            'provider' => 'uisp',
            'external_type' => 'interface',
            'external_id' => 'concurrent-interface-1',
        ];

        $engine1 = new ReconciliationEngine();
        $engine2 = new ReconciliationEngine();

        $result1 = $engine1->reconcileInterface($interfaceData, $asset->id);
        $result2 = $engine2->reconcileInterface($interfaceData, $asset->id);

        $this->assertEquals($result1['decision'], $result2['decision']);

        if ($result1['decision'] === 'CREATE') {
            AssetInterface::create([
                'asset_id' => $asset->id,
                'name' => $interfaceData['name'],
                'type' => $interfaceData['type'],
                'provider' => $interfaceData['provider'],
                'external_type' => $interfaceData['external_type'],
                'external_id' => $interfaceData['external_id'],
            ]);
        }

        $count = AssetInterface::where('asset_id', $asset->id)->count();
        $this->assertEquals(1, $count);
    }

    // ============================================================
    // PHASE 2.1 - TRANSACTION ROLLBACK ON FAILURE
    // ============================================================

    public function test_transaction_rollback_on_ip_failure()
    {
        $asset = Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'ROLLBACK-001',
            'category' => 'NETWORK',
            'type' => 'device',
            'status' => 'OPERATIONAL',
            'condition' => 'GOOD',
            'quantity' => 1,
            'unit' => 'pcs',
        ]);

        $interfaceData = [
            'name' => 'eth0',
            'type' => 'ethernet',
            'provider' => 'uisp',
            'external_type' => 'interface',
            'external_id' => 'rollback-interface-1',
        ];

        $invalidIpData = [
            'ip' => 'invalid-ip-format',
            'prefix' => 24,
            'provider' => 'uisp',
            'external_type' => 'ip',
            'external_id' => 'rollback-ip-1',
        ];

        try {
            DB::transaction(function () use ($asset, $interfaceData, $invalidIpData) {
                $interface = AssetInterface::create([
                    'asset_id' => $asset->id,
                    'name' => $interfaceData['name'],
                    'type' => $interfaceData['type'],
                    'provider' => $interfaceData['provider'],
                    'external_type' => $interfaceData['external_type'],
                    'external_id' => $interfaceData['external_id'],
                ]);

                IpAddress::create([
                    'asset_interface_id' => $interface->id,
                    'ip_address' => $invalidIpData['ip'],
                    'prefix_length' => $invalidIpData['prefix'],
                    'provider' => $invalidIpData['provider'],
                    'external_type' => $invalidIpData['external_type'],
                    'external_id' => $invalidIpData['external_id'],
                ]);
            });
            $this->fail('Transaction should have failed due to invalid IP');
        } catch (\Exception $e) {
            $interfaceCount = AssetInterface::where('asset_id', $asset->id)->count();
            $this->assertEquals(0, $interfaceCount);

            $ipCount = IpAddress::count();
            $this->assertEquals(0, $ipCount);
        }
    }
}
