<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class DebugController extends Controller
{
    public function status(Request $request)
    {
        try {
            // Check if user has permission
            if (!$request->user() || !$request->user()->hasPermissionTo('system.debug.view')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json([
                'status' => 'ok',
                'timestamp' => now()->toIso8601String(),
                'environment' => env('APP_ENV'),
                'debug' => env('APP_DEBUG', false),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Debug endpoint error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function logs(Request $request)
    {
        try {
            if (!$request->user() || !$request->user()->hasPermissionTo('system.debug.view')) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Simple log viewer
            $logPath = storage_path('logs/laravel.log');
            if (!file_exists($logPath)) {
                return response()->json(['logs' => 'No log file found']);
            }

            $logs = file_get_contents($logPath);
            $lines = explode("\n", $logs);
            $lastLines = array_slice($lines, -100);

            return response()->json([
                'logs' => implode("\n", $lastLines),
                'count' => count($lastLines)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
