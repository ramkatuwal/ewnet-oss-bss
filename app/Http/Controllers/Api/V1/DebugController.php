<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DebugController extends Controller
{
    public function status(Request $request)
    {
        try {
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
        if (!$request->user() || !$request->user()->hasPermissionTo('system.debug.view')) {
            abort(403, 'Unauthorized');
        }

        $type = $request->input('type', 'laravel');
        $limit = min($request->input('limit', 100), 500);

        if ($type === 'nginx') {
            return response()->json($this->getNginxLogs($limit));
        } elseif ($type === 'laravel') {
            return response()->json($this->getLaravelLogs($limit));
        }

        abort(400, 'Invalid log type');
    }

    public function summary(Request $request)
    {
        if (!$request->user() || !$request->user()->hasPermissionTo('system.debug.view')) {
            abort(403, 'Unauthorized');
        }

        return response()->json($this->getNginxSummary());
    }

    protected function getNginxLogs(int $limit): array
    {
        // Attempt to read from mounted log file first (if configured)
        $logFile = '/var/log/nginx/access.log';
        if (File::exists($logFile)) {
            $lines = File::lines($logFile)->reverse()->take($limit)->toArray();
            return $this->parseNginxLines(array_reverse($lines));
        }

        // Fallback: Try docker logs (requires docker socket/CLI in container)
        try {
            $process = new Process(['docker', 'logs', 'ewnet-web', '--tail', $limit]);
            $process->run();

            if ($process->isSuccessful()) {
                return $this->parseNginxLines(explode("\n", trim($process->getOutput())));
            }
        } catch (\Exception $e) {
            \Log::debug('Docker logs failed: ' . $e->getMessage());
        }

        // Return empty array if no logs found
        return [];
    }

    protected function getNginxSummary(): array
    {
        $defaultSummary = [
            'total' => 0, '2xx' => 0, '3xx' => 0, '4xx' => 0, '5xx' => 0,
            '401' => 0, '403' => 0, '404' => 0, '429' => 0, 
            '500' => 0, '502' => 0, '503' => 0, '504' => 0,
            'top_ips' => [], 'top_paths' => []
        ];

        // Attempt to read from mounted log file
        $logFile = '/var/log/nginx/access.log';
        $lines = [];
        
        if (File::exists($logFile)) {
            $lines = File::lines($logFile)->reverse()->take(1000)->toArray();
        } else {
            // Fallback to docker logs
            try {
                $process = new Process(['docker', 'logs', 'ewnet-web', '--tail', '1000']);
                $process->run();
                if ($process->isSuccessful()) {
                    $lines = explode("\n", trim($process->getOutput()));
                }
            } catch (\Exception $e) {
                return $defaultSummary;
            }
        }

        if (empty($lines)) return $defaultSummary;

        $summary = $defaultSummary;
        $ipCounts = [];
        $pathCounts = [];

        foreach ($lines as $line) {
            if (preg_match('/^(?<ip>[\d\.]+) .* "(?<method>\w+) (?<path>[^ ]+) [^"]+" (?<status>\d+)/', $line, $matches)) {
                $summary['total']++;
                $status = (int)$matches['status'];
                
                if ($status >= 200 && $status < 300) $summary['2xx']++;
                elseif ($status >= 300 && $status < 400) $summary['3xx']++;
                elseif ($status >= 400 && $status < 500) $summary['4xx']++;
                elseif ($status >= 500) $summary['5xx']++;

                if (isset($summary[$status])) $summary[$status]++;

                $ipCounts[$matches['ip']] = ($ipCounts[$matches['ip']] ?? 0) + 1;
                $pathCounts[$matches['path']] = ($pathCounts[$matches['path']] ?? 0) + 1;
            }
        }

        arsort($ipCounts);
        arsort($pathCounts);
        $summary['top_ips'] = array_slice(array_keys($ipCounts), 0, 10);
        $summary['top_paths'] = array_slice(array_keys($pathCounts), 0, 10);

        return $summary;
    }

    protected function parseNginxLines(array $lines): array
    {
        $parsed = [];
        foreach ($lines as $line) {
            if (empty($line)) continue;
            if (preg_match('/^(?<ip>[\d\.]+) - - \[(?<time>[^\]]+)\] "(?<method>\w+) (?<path>[^ ]+) [^"]+" (?<status>\d+) (?<size>\d+) "(?<referer>[^"]*)" "(?<agent>[^"]*)"/', $line, $matches)) {
                $parsed[] = [
                    'ip' => $matches['ip'],
                    'time' => $matches['time'],
                    'method' => $matches['method'],
                    'path' => $matches['path'],
                    'status' => (int)$matches['status'],
                    'size' => (int)$matches['size'],
                    'agent' => $matches['agent'],
                    'classification' => $this->classifyRequest($matches['path'], (int)$matches['status'])
                ];
            }
        }
        return $parsed;
    }

    protected function getLaravelLogs(int $limit): array
    {
        $path = storage_path('logs/laravel.log');
        if (!File::exists($path)) return [];

        $lines = File::lines($path)->reverse()->take($limit)->toArray();
        $parsed = [];

        foreach ($lines as $line) {
            if (preg_match('/\[(?<time>[^\]]+)\] (?<env>\w+)\.(?<level>\w+): (?<message>.+)/', $line, $matches)) {
                $msg = $matches['message'];
                $msg = preg_replace('/(password|token|secret|key)=\S+/i', '$1=[REDACTED]', $msg);
                
                $parsed[] = [
                    'time' => $matches['time'],
                    'level' => $matches['level'],
                    'message' => substr($msg, 0, 200)
                ];
            }
        }

        return array_reverse($parsed);
    }

    protected function classifyRequest(string $path, int $status): string
    {
        if (str_contains($path, '.env') || str_contains($path, '.git') || str_contains($path, 'wp-admin') || str_contains($path, 'phpmyadmin')) {
            return 'SECURITY PROBE';
        }
        if ($status >= 500) return 'ERROR';
        if ($status >= 400) return 'CLIENT ERROR';
        return 'NORMAL';
    }
}
