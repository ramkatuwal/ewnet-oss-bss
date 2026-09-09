<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VlanConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('ewnet_test', DB::selectOne('select current_database() as db')->db);
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->seed();
    }

    protected function tearDown(): void
    {
        try {
            $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
            RefreshDatabaseState::$migrated = false;
        } finally {
            parent::tearDown();
        }
    }

    public function test_database_serializes_concurrent_duplicate_live_vid_creation(): void
    {
        $company = Company::factory()->create();
        $config = config('database.connections.pgsql');
        $values = ['host' => $config['host'], 'port' => $config['port'], 'dbname' => 'ewnet_test', 'user' => $config['username'], 'password' => $config['password']];
        $dsn = implode(' ', array_map(fn ($key, $value) => $key."='".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value)."'", array_keys($values), $values));
        $first = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        $second = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($first);
        $this->assertNotFalse($second);

        try {
            foreach ([$first, $second] as $connection) {
                $this->assertSame('ewnet_test', pg_fetch_result(pg_query($connection, 'select current_database()'), 0, 0));
                pg_query($connection, "SET statement_timeout = '10s'");
                pg_query($connection, 'BEGIN');
            }

            $sql = sprintf("INSERT INTO vlans (company_id, vid, name, created_at, updated_at) VALUES (%d, 100, 'Concurrent', now(), now())", $company->id);
            $this->assertNotFalse(pg_query($first, $sql));
            $this->assertTrue(pg_send_query($second, $sql));

            $deadline = microtime(true) + 5;
            do {
                $activity = DB::selectOne('SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?', [pg_get_pid($second)]);
                if ($activity?->wait_event_type === 'Lock') {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);

            $this->assertSame('Lock', DB::selectOne('SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?', [pg_get_pid($second)])?->wait_event_type);
            $this->assertNotFalse(pg_query($first, 'COMMIT'));
            $result = pg_get_result($second);
            $this->assertSame(PGSQL_FATAL_ERROR, pg_result_status($result));
            $this->assertSame('23505', pg_result_error_field($result, PGSQL_DIAG_SQLSTATE));
            pg_query($second, 'ROLLBACK');
        } finally {
            pg_close($first);
            pg_close($second);
        }
    }
}
