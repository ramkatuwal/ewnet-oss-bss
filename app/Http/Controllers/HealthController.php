<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $status = [];
        $overall = 'healthy';

        try {
            DB::connection()->getPdo();
            $status['database'] = 'connected';
        } catch (\Exception $e) {
            $status['database'] = 'failed: ' . $e->getMessage();
            $overall = 'unhealthy';
        }

        try {
            Cache::store('redis')->put('health_check', true, 10);
            $status['redis'] = 'connected';
        } catch (\Exception $e) {
            $status['redis'] = 'failed: ' . $e->getMessage();
            $overall = 'unhealthy';
        }

        try {
            Storage::disk('local')->put('health_check.txt', 'ok');
            $status['storage'] = 'writable';
            Storage::disk('local')->delete('health_check.txt');
        } catch (\Exception $e) {
            $status['storage'] = 'failed: ' . $e->getMessage();
            $overall = 'unhealthy';
        }

        return response()->json([
            'status' => $overall,
            'checks' => $status,
            'timestamp' => now()->toIso8601String(),
            'application' => 'EWNET OSS/BSS',
            'version' => '1.0.0'
        ]);
    }
}
