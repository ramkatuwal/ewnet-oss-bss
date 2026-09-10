<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoutingInstanceConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('ewnet_test', DB::selectOne('select current_database() as db')->db);
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
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

    public function test_database_serializes_duplicate_name_and_default_creation(): void
    {
        $company = Company::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $company->id, 'site_id' => Site::factory()->create(['company_id' => $company->id])->id, 'category' => 'NETWORK']);
        $config = config('database.connections.pgsql');
        $values = ['host' => $config['host'], 'port' => $config['port'], 'dbname' => 'ewnet_test', 'user' => $config['username'], 'password' => $config['password']];
        $dsn = implode(' ', array_map(fn ($key, $value) => $key."='".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value)."'", array_keys($values), $values));
        $first = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        $second = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($first);
        $this->assertNotFalse($second);

        try {
            foreach ([$first, $second] as $connection) {
                pg_query($connection, "SET statement_timeout = '10s'");
                pg_query($connection, 'BEGIN');
            }
            $sql = sprintf("INSERT INTO routing_instances (asset_id, company_id, name, kind, created_at, updated_at) VALUES (%d, %d, 'main', 'default', now(), now())", $asset->id, $company->id);
            $this->assertNotFalse(pg_query($first, $sql));
            $this->assertTrue(pg_send_query($second, $sql));
            $deadline = microtime(true) + 5;
            do {
                $event = DB::selectOne('SELECT wait_event_type FROM pg_stat_activity WHERE pid = ?', [pg_get_pid($second)])?->wait_event_type;
                if ($event === 'Lock') {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame('Lock', $event);
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

    public function test_database_rejects_creation_racing_asset_retirement(): void
    {
        $company = Company::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $company->id, 'site_id' => Site::factory()->create(['company_id' => $company->id])->id, 'category' => 'NETWORK']);
        $config = config('database.connections.pgsql');
        $values = ['host' => $config['host'], 'port' => $config['port'], 'dbname' => 'ewnet_test', 'user' => $config['username'], 'password' => $config['password']];
        $dsn = implode(' ', array_map(fn ($key, $value) => $key."='".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value)."'", array_keys($values), $values));
        $retiring = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        $creating = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($retiring);
        $this->assertNotFalse($creating);

        try {
            pg_query($retiring, 'BEGIN');
            $this->assertNotFalse(pg_query($retiring, sprintf('UPDATE assets SET deleted_at = now() WHERE id = %d', $asset->id)));
            pg_query($creating, "SET statement_timeout = '10s'");
            pg_query($creating, 'BEGIN');
            $sql = sprintf("INSERT INTO routing_instances (asset_id, company_id, name, kind, created_at, updated_at) VALUES (%d, %d, 'main', 'default', now(), now())", $asset->id, $company->id);
            $this->assertTrue(pg_send_query($creating, $sql));
            $this->assertNotFalse(pg_query($retiring, 'COMMIT'));
            $result = pg_get_result($creating);
            $this->assertSame(PGSQL_FATAL_ERROR, pg_result_status($result));
            $this->assertSame('23503', pg_result_error_field($result, PGSQL_DIAG_SQLSTATE));
            pg_query($creating, 'ROLLBACK');
        } finally {
            pg_close($retiring);
            pg_close($creating);
        }
    }
}
