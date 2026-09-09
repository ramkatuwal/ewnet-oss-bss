<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class NetworkPortFiberConcurrencyTest extends TestCase
{
    use NetworkPortFiberFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertFalse(app()->configurationIsCached());
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('ewnet_test', DB::selectOne('select current_database() as db')->db);
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->seed();
    }

    protected function tearDown(): void
    {
        // These tests deliberately commit fixtures across real sessions. Never roll back legacy migrations.
        try {
            $this->assertSame('ewnet_test', DB::selectOne('select current_database() as db')->db);
            $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
            RefreshDatabaseState::$migrated = false;
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('races')]
    public function test_real_sessions_serialize_external_writes(string $first, string $second): void
    {
        [$port, $term, $other, $passive] = $this->fixture();
        $rows = [
            'active' => ['network_port_fiber_termination_attachments', $this->activeRow($port, $term)],
            'passive' => $this->competitorRow('passive', $term, $other, $passive),
            'splice' => $this->competitorRow('splice', $term, $other, $passive),
            'same_port' => ['network_port_fiber_termination_attachments', $this->activeRow($port, $other)],
        ];
        $this->race($rows[$first], $rows[$second]);
        $count = DB::selectOne('SELECT (SELECT count(*) FROM network_port_fiber_termination_attachments WHERE deleted_at IS NULL) + (SELECT count(*) FROM physical_connections WHERE deleted_at IS NULL) + (SELECT count(*) FROM fiber_termination_port_attachments WHERE deleted_at IS NULL) AS edges')->edges;
        $this->assertSame(1, (int) $count);
    }

    public static function races(): array
    {
        return [['active', 'passive'], ['passive', 'active'], ['active', 'splice'], ['splice', 'active'], ['active', 'active'], ['active', 'same_port'], ['passive', 'splice'], ['splice', 'passive']];
    }

    #[DataProvider('associationWriters')]
    public function test_ncp_association_update_and_attachment_do_not_deadlock(string $writer): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $name = 'ned004-'.bin2hex(random_bytes(8));
        $worker = new Process([PHP_BINARY, base_path('tests/Support/network_port_fiber_worker.php'), $name, $writer, (string) $user->id, (string) $port->id, (string) $term->id]);
        DB::beginTransaction();
        try {
            DB::statement("SET LOCAL statement_timeout = '5s'");
            DB::table('network_connection_points')->where('id', $term->network_connection_point_id)->lockForUpdate()->first();
            $worker->start();
            $this->waitForWorkerLock($name, $worker);
            // Before the fix the worker owned the port while waiting for our NCP tuple.
            $this->assertSame(1, DB::table('network_connection_points')->where('id', $term->network_connection_point_id)->update(['network_port_id' => $port->id]));
            DB::commit();
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertSame('created', $worker->getOutput());
            $this->assertDatabaseHas('network_port_fiber_termination_attachments', $this->activeRow($port, $term));
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker->stop();
        }
    }

    public static function associationWriters(): array
    {
        return [['attach'], ['raw-attach']];
    }

    #[DataProvider('parentWriters')]
    public function test_parent_descriptive_update_and_attachment_do_not_deadlock(string $parent): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $name = 'ned004-'.bin2hex(random_bytes(8));
        $worker = new Process([PHP_BINARY, base_path('tests/Support/network_port_fiber_worker.php'), $name, 'attach', (string) $user->id, (string) $port->id, (string) $term->id]);
        DB::beginTransaction();
        try {
            DB::statement("SET LOCAL statement_timeout = '5s'");
            $query = DB::table($parent)->where('id', $parent === 'assets' ? $port->asset_id : $port->id);
            $query->lockForUpdate()->first();
            $worker->start();
            $this->waitForWorkerLock($name, $worker);
            $column = $parent === 'assets' ? 'description' : 'name';
            $this->assertSame(1, $query->update([$column => 'Concurrent descriptive update']));
            DB::commit();
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertSame('created', $worker->getOutput());
            $this->assertDatabaseHas('network_port_fiber_termination_attachments', $this->activeRow($port, $term));
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker->stop();
        }
    }

    public static function parentWriters(): array
    {
        return [['network_ports'], ['assets']];
    }

    #[DataProvider('existingRowUpdates')]
    public function test_raw_existing_row_update_and_api_disconnect_do_not_deadlock(string $update): void
    {
        [$port, $term, , , $user] = $this->fixture();
        $attachment = $this->attach($port, $term, $user);
        $name = 'ned004-'.bin2hex(random_bytes(8));
        $worker = new Process([PHP_BINARY, base_path('tests/Support/network_port_fiber_worker.php'), $name, 'disconnect', (string) $user->id, (string) $attachment->id]);
        DB::beginTransaction();
        try {
            DB::statement("SET LOCAL statement_timeout = '5s'");
            $query = DB::table('network_port_fiber_termination_attachments')->where('id', $attachment->id);
            $query->lockForUpdate()->first();
            $worker->start();
            $this->waitForWorkerLock($name, $worker);
            // Before the fix disconnect owned the termination while waiting for our attachment.
            $this->assertSame(1, $query->update($update === 'metadata' ? ['metadata' => '{"note":"concurrent"}'] : ['deleted_at' => now()]));
            if ($update === 'reactivation') {
                DB::statement('SAVEPOINT rejected_restore');
                try {
                    $query->update(['deleted_at' => null]);
                    $this->fail('Historical attachment must not reactivate.');
                } catch (QueryException $e) {
                    $this->assertSame('23503', $e->errorInfo[0]);
                    DB::statement('ROLLBACK TO SAVEPOINT rejected_restore');
                }
            }
            DB::commit();
            $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());
            $this->assertSame($update === 'metadata' ? '200' : '404', $worker->getOutput());
            $this->assertSoftDeleted('network_port_fiber_termination_attachments', ['id' => $attachment->id]);
            $this->assertDatabaseCount('network_port_fiber_termination_attachments', 1);
            if ($update === 'metadata') {
                $this->assertSame(['note' => 'concurrent'], $attachment->fresh()->metadata);
                $this->assertSame($user->id, $attachment->fresh()->updated_by);
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker->stop();
        }
    }

    public static function existingRowUpdates(): array
    {
        return [['metadata'], ['soft_delete'], ['reactivation']];
    }

    private function waitForWorkerLock(string $name, Process $worker): void
    {
        $deadline = microtime(true) + 5;
        do {
            // Statistics snapshots otherwise remain cached for this open transaction.
            DB::selectOne('SELECT pg_stat_clear_snapshot()');
            $activity = DB::selectOne('SELECT pid FROM pg_stat_activity WHERE application_name = ? AND ? = ANY(pg_blocking_pids(pid))', [$name, DB::selectOne('select pg_backend_pid() as pid')->pid]);
            if ($activity !== null) {
                $this->assertTrue($worker->isRunning());

                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Worker did not block on the held row: '.$worker->getErrorOutput().$worker->getOutput());
    }

    private function race(array $first, array $second): void
    {
        $config = config('database.connections.pgsql');
        $dsn = implode(' ', array_map(fn ($key, $value) => $key."='".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value)."'", ['host', 'port', 'dbname', 'user', 'password'], [$config['host'], $config['port'], 'ewnet_test', $config['username'], $config['password']]));
        $a = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        $b = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($a);
        $this->assertNotFalse($b);
        try {
            foreach ([$a, $b] as $connection) {
                $this->assertSame('ewnet_test', pg_fetch_result(pg_query($connection, 'select current_database()'), 0, 0));
                pg_query($connection, "SET statement_timeout = '10s'");
                pg_query($connection, 'BEGIN');
            }
            [$table, $row] = $first;
            $sql = 'INSERT INTO '.$table.' ('.implode(',', array_keys($row)).') VALUES ('.implode(',', array_map(fn ($v) => pg_escape_literal($a, (string) $v), $row)).')';
            $this->assertNotFalse(pg_query($a, $sql));
            [$table, $row] = $second;
            $sql = 'INSERT INTO '.$table.' ('.implode(',', array_keys($row)).') VALUES ('.implode(',', array_map(fn ($v) => pg_escape_literal($b, (string) $v), $row)).')';
            $this->assertTrue(pg_send_query($b, $sql));
            $blocked = false;
            $deadline = microtime(true) + 5;
            do {
                $activity = DB::selectOne('SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?', [pg_get_pid($b)]);
                if ($activity?->wait_event_type === 'Lock') {
                    $blocked = true;
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($blocked, 'Session B must wait on the endpoint/port lock while A is uncommitted.');
            $this->assertNotFalse(pg_query($a, 'COMMIT'));
            $result = pg_get_result($b);
            $this->assertSame(PGSQL_FATAL_ERROR, pg_result_status($result));
            $this->assertSame('23505', pg_result_error_field($result, PGSQL_DIAG_SQLSTATE));
            pg_get_result($b);
            pg_query($b, 'ROLLBACK');
        } finally {
            pg_close($a);
            pg_close($b);
        }
    }
}
