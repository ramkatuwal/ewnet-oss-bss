<?php

namespace Tests\Unit;

use App\Models\ImportHistory;
use App\Models\Integration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_import_history_with_valid_fields()
    {
        $history = ImportHistory::create([
            'source' => 'uisp',
            'type' => 'device',
            'status' => 'pending',
            'total_records' => 100,
        ]);

        $this->assertDatabaseHas('import_history', [
            'id' => $history->id,
            'source' => 'uisp',
            'type' => 'device',
            'status' => 'pending',
            'total_records' => 100,
        ]);
    }

    public function test_creates_import_history_with_integration()
    {
        $integration = Integration::factory()->create();
        $history = ImportHistory::create([
            'source' => 'uisp',
            'type' => 'device',
            'integration_id' => $integration->id,
            'status' => 'pending',
        ]);

        $this->assertEquals($integration->id, $history->integration_id);
        $this->assertInstanceOf(Integration::class, $history->integration);
    }

    public function test_creates_import_history_with_user()
    {
        $user = User::factory()->create();
        $history = ImportHistory::create([
            'source' => 'uisp',
            'type' => 'device',
            'started_by' => $user->id,
            'status' => 'pending',
        ]);

        $this->assertEquals($user->id, $history->started_by);
        $this->assertInstanceOf(User::class, $history->startedBy);
    }

    public function test_marks_as_running()
    {
        $history = ImportHistory::create([
            'source' => 'uisp',
            'type' => 'device',
            'status' => 'pending',
        ]);

        $history->markAsRunning();

        $this->assertEquals('running', $history->status);
        $this->assertNotNull($history->started_at);
    }

    public function test_marks_as_completed()
    {
        $history = ImportHistory::create([
            'source' => 'uisp',
            'type' => 'device',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $history->markAsCompleted([
            'created_records' => 50,
            'updated_records' => 30,
            'skipped_records' => 20,
        ]);

        $this->assertEquals('completed', $history->status);
        $this->assertNotNull($history->completed_at);
        $this->assertEquals(50, $history->created_records);
        $this->assertEquals(30, $history->updated_records);
        $this->assertEquals(20, $history->skipped_records);
    }

    public function test_marks_as_failed()
    {
        $history = ImportHistory::create([
            'source' => 'uisp',
            'type' => 'device',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $errorMessage = 'Connection failed: UISP API timeout';
        $history->markAsFailed($errorMessage);

        $this->assertEquals('failed', $history->status);
        $this->assertNotNull($history->completed_at);
        $this->assertEquals($errorMessage, $history->error_message);
    }

    public function test_calculates_duration()
    {
        $history = ImportHistory::create([
            'source' => 'uisp',
            'type' => 'device',
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);

        $duration = $history->getDurationInSeconds();
        $this->assertGreaterThanOrEqual(299, $duration);
        $this->assertLessThanOrEqual(301, $duration);
    }

    public function test_scopes_work_correctly()
    {
        ImportHistory::create(['source' => 'uisp', 'type' => 'device', 'status' => 'pending']);
        ImportHistory::create(['source' => 'uisp', 'type' => 'site', 'status' => 'pending']);
        ImportHistory::create(['source' => 'librenms', 'type' => 'device', 'status' => 'pending']);

        $uispCount = ImportHistory::forSource('uisp')->count();
        $deviceCount = ImportHistory::forType('device')->count();

        $this->assertEquals(2, $uispCount);
        $this->assertEquals(2, $deviceCount);
    }

    public function test_status_helpers_work()
    {
        $pending = ImportHistory::create(['source' => 'uisp', 'type' => 'device', 'status' => 'pending']);
        $running = ImportHistory::create(['source' => 'uisp', 'type' => 'device', 'status' => 'running']);
        $completed = ImportHistory::create(['source' => 'uisp', 'type' => 'device', 'status' => 'completed']);
        $failed = ImportHistory::create(['source' => 'uisp', 'type' => 'device', 'status' => 'failed']);

        $this->assertTrue($pending->isPending());
        $this->assertTrue($running->isRunning());
        $this->assertTrue($completed->isCompleted());
        $this->assertTrue($failed->isFailed());
    }
}
