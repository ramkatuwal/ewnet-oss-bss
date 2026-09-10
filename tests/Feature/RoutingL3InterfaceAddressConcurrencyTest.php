<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Company;
use App\Models\RoutingInstance;
use App\Models\RoutingL3Interface;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoutingL3InterfaceAddressConcurrencyTest extends TestCase
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

    public function test_database_serializes_same_host_creation_with_different_prefixes(): void
    {
        $company = Company::factory()->create();
        $asset = Asset::factory()->create(['company_id' => $company->id, 'site_id' => Site::factory()->create(['company_id' => $company->id])->id, 'category' => 'NETWORK']);
        $instance = RoutingInstance::create(['asset_id' => $asset->id, 'company_id' => $company->id, 'name' => 'default', 'kind' => 'default']);
        $interface = RoutingL3Interface::create(['routing_instance_id' => $instance->id, 'asset_id' => $asset->id, 'company_id' => $company->id, 'name' => 'lo0', 'kind' => 'loopback']);
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
            $sql = sprintf("INSERT INTO routing_l3_interface_addresses (routing_l3_interface_id, routing_instance_id, asset_id, company_id, address, prefix_length, address_role, created_at, updated_at) VALUES (%d, %d, %d, %d, '%s', 24, 'secondary', now(), now())", $interface->id, $instance->id, $asset->id, $company->id, '10.0.0.1/24');
            $this->assertNotFalse(pg_query($first, $sql));
            $this->assertTrue(pg_send_query($second, str_replace(["'10.0.0.1/24'", ' 24,'], ["'10.0.0.1/25'", ' 25,'], $sql)));
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
}
