<?php

namespace Tests\Unit\Integrations;

use App\Models\Integration;
use App\Models\LibreNmsObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibreNmsObjectTest extends TestCase
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
            'configuration' => ['endpoint' => 'https://test.example.com'],
        ]);
    }

    public function test_upsert_creates_new_object(): void
    {
        $result = LibreNmsObject::upsertObject(
            $this->integration->id, 'device', '87',
            ['hostname' => 'router1', 'ip' => '10.0.0.1'],
            'router1', 'up'
        );

        $this->assertSame('created', $result);
        $this->assertDatabaseHas('librenms_objects', [
            'integration_id' => $this->integration->id,
            'object_type' => 'device',
            'external_id' => '87',
            'display_name' => 'router1',
        ]);
    }

    public function test_upsert_updates_changed_object(): void
    {
        LibreNmsObject::upsertObject($this->integration->id, 'device', '87', ['v' => 1], 'r1', 'up');
        $result = LibreNmsObject::upsertObject($this->integration->id, 'device', '87', ['v' => 2], 'r1', 'down');

        $this->assertSame('updated', $result);
        $this->assertDatabaseCount('librenms_objects', 1);
    }

    public function test_upsert_returns_unchanged_for_identical_data(): void
    {
        $data = ['hostname' => 'router1'];
        LibreNmsObject::upsertObject($this->integration->id, 'device', '87', $data, 'r1', 'up');
        $result = LibreNmsObject::upsertObject($this->integration->id, 'device', '87', $data, 'r1', 'up');

        $this->assertSame('unchanged', $result);
        $this->assertDatabaseCount('librenms_objects', 1);
    }

    public function test_upsert_prevents_duplicates(): void
    {
        LibreNmsObject::upsertObject($this->integration->id, 'device', '87', ['a' => 1], 'r1');
        LibreNmsObject::upsertObject($this->integration->id, 'device', '87', ['a' => 1], 'r1');
        LibreNmsObject::upsertObject($this->integration->id, 'device', '87', ['a' => 1], 'r1');

        $this->assertDatabaseCount('librenms_objects', 1);
    }

    public function test_port_preserves_parent_device_id(): void
    {
        LibreNmsObject::upsertObject($this->integration->id, 'port', '1342',
            ['ifName' => 'eth0'], 'eth0', 'up', '87');

        $obj = LibreNmsObject::where('external_id', '1342')->first();
        $this->assertSame('87', $obj->external_parent_id);
    }
}
