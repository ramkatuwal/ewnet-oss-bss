<?php

use App\Models\NetworkPort;
use App\Models\User;
use App\Services\Network\NetworkPortFiberTerminationAttachmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached() || ! $app->environment('testing')
    || config('cache.default') !== 'array'
    || DB::selectOne('select current_database() as db')->db !== 'ewnet_test') {
    throw new RuntimeException('Unsafe concurrency worker environment');
}
DB::statement("SET statement_timeout = '15s'");
DB::selectOne("SELECT set_config('application_name', ?, false)", [$argv[1]]);
$user = User::findOrFail($argv[3]);
if ($argv[2] === 'disconnect') {
    Sanctum::actingAs($user);
    $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle(
        Request::create('/api/v1/network-port-fiber-attachments/'.$argv[4], 'DELETE', server: ['HTTP_ACCEPT' => 'application/json']),
    );
    echo $response->getStatusCode();
} elseif ($argv[2] === 'attach') {
    app(NetworkPortFiberTerminationAttachmentService::class)->attach(NetworkPort::findOrFail($argv[4]), (int) $argv[5], $user);
    echo 'created';
} elseif ($argv[2] === 'raw-attach') {
    $port = NetworkPort::findOrFail($argv[4]);
    DB::table('network_port_fiber_termination_attachments')->insert([
        'network_port_id' => $port->id, 'fiber_termination_id' => $argv[5], 'company_id' => $port->company_id,
    ]);
    echo 'created';
} else {
    throw new RuntimeException('Unknown concurrency worker operation');
}
