<?php

namespace Tests\Unit\Imports;

use App\Dto\Imports\NormalizedRecord;
use App\Models\Asset;
use App\Models\Site;
use App\Services\Imports\ReconciliationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a valid site first to satisfy FK constraint
        $this->site = Site::create([
            'site_code' => 'TEST-SITE',
            'name' => 'Test Site',
            'type' => 'pop',
            'status' => 'active',
        ]);

        // Seed destination asset 1
        Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'TEST-001',
            'serial_number' => 'SN-11111',
            'category' => 'NETWORK',
            'type' => 'Router',
            'status' => 'OPERATIONAL',
            'specifications' => ['mac_address' => 'aa:bb:cc:dd:ee:ff', 'ip_address' => '192.168.1.1']
        ]);

        // Seed destination asset 2
        Asset::create([
            'site_id' => $this->site->id,
            'asset_tag' => 'TEST-002',
            'serial_number' => 'SN-22222',
            'category' => 'NETWORK',
            'type' => 'Switch',
            'status' => 'OPERATIONAL',
            'specifications' => ['mac_address' => '99:88:77:66:55:44']
        ]);
    }

    public function test_create_decision_when_no_match()
    {
        $engine = new ReconciliationEngine();
        $record = new NormalizedRecord(
            provider: 'uisp',
            sourceType: 'device',
            externalId: 'NEW-123',
            name: 'New Device',
            macAddress: '11:22:33:44:55:66'
        );

        $result = $engine->reconcile($record);
        $this->assertEquals('CREATE', $result['decision']);
    }

    public function test_link_decision_by_mac()
    {
        $engine = new ReconciliationEngine();
        $record = new NormalizedRecord(
            provider: 'uisp',
            sourceType: 'device',
            externalId: 'EXT-123',
            name: 'Test Device',
            macAddress: 'aa:bb:cc:dd:ee:ff'
        );

        $result = $engine->reconcile($record);
        $this->assertEquals('LINK', $result['decision']);
        $this->assertNotNull($result['destination_id']);
    }

    public function test_conflict_decision_by_mac_and_serial()
    {
        // Source record matches Asset 1 by MAC and Asset 2 by Serial
        $engine = new ReconciliationEngine();
        $record = new NormalizedRecord(
            provider: 'uisp',
            sourceType: 'device',
            externalId: 'EXT-456',
            name: 'Conflicting Device',
            macAddress: 'aa:bb:cc:dd:ee:ff', // Matches TEST-001
            serialNumber: 'SN-22222'         // Matches TEST-002
        );

        $result = $engine->reconcile($record);
        $this->assertEquals('CONFLICT', $result['decision']);
    }
}
