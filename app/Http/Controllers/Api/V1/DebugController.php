<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DebugController extends Controller
{
    private const LOG_LEVELS = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

    private const MAX_WINDOW = 10000;

    public function status(Request $request)
    {
        try {
            if (! $request->user() || ! $request->user()->hasPermissionTo('system.debug.view')) {
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
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function logs(Request $request)
    {
        if (! $request->user() || ! $request->user()->hasPermissionTo('system.debug.view')) {
            abort(403, 'Unauthorized');
        }

        $type = $request->input('type', 'laravel');
        if (! in_array($type, ['nginx', 'laravel'], true)) {
            abort(400, 'Invalid log type');
        }

        // Legacy flat-array contract when pagination is not requested
        if (! $request->has('page')) {
            $limit = min((int) $request->input('limit', 100), 500);

            return response()->json($type === 'nginx'
                ? $this->getNginxLogs($limit)
                : $this->getLaravelLogs($limit));
        }

        $perPage = max(1, min((int) $request->input('per_page', 50), 200));
        $page = max(1, (int) $request->input('page', 1));
        $window = max(1, min((int) $request->input('window', 3000), self::MAX_WINDOW));

        $filters = [
            'search' => mb_substr((string) $request->input('search', ''), 0, 200),
            'level' => $type === 'laravel' ? $request->input('level') : null,
            'status_class' => $type === 'nginx' ? $request->input('status_class') : null,
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $entries = $type === 'nginx'
            ? $this->getNginxLogsStructured($window)
            : $this->getLaravelLogsStructured($window);

        $filtered = array_values(array_filter($entries, fn ($entry) => $this->matchesFilters($entry, $filters, $type)));

        $total = count($filtered);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
        $to = min($total, $page * $perPage);
        $items = $page > $lastPage ? [] : array_slice($filtered, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
                'window' => $window,
            ],
        ]);
    }

    public function summary(Request $request)
    {
        if (! $request->user() || ! $request->user()->hasPermissionTo('system.debug.view')) {
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
            \Log::debug('Docker logs failed: '.$e->getMessage());
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
            'top_ips' => [], 'top_paths' => [],
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

        if (empty($lines)) {
            return $defaultSummary;
        }

        $summary = $defaultSummary;
        $ipCounts = [];
        $pathCounts = [];

        foreach ($lines as $line) {
            if (preg_match('/^(?<ip>[\d\.]+) .* "(?<method>\w+) (?<path>[^ ]+) [^"]+" (?<status>\d+)/', $line, $matches)) {
                $summary['total']++;
                $status = (int) $matches['status'];

                if ($status >= 200 && $status < 300) {
                    $summary['2xx']++;
                } elseif ($status >= 300 && $status < 400) {
                    $summary['3xx']++;
                } elseif ($status >= 400 && $status < 500) {
                    $summary['4xx']++;
                } elseif ($status >= 500) {
                    $summary['5xx']++;
                }

                if (isset($summary[$status])) {
                    $summary[$status]++;
                }

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
            if (empty($line)) {
                continue;
            }
            if (preg_match('/^(?<ip>[\d\.]+) - - \[(?<time>[^\]]+)\] "(?<method>\w+) (?<path>[^ ]+) [^"]+" (?<status>\d+) (?<size>\d+) "(?<referer>[^"]*)" "(?<agent>[^"]*)"/', $line, $matches)) {
                $parsed[] = [
                    'ip' => $matches['ip'],
                    'time' => $matches['time'],
                    'method' => $matches['method'],
                    'path' => $matches['path'],
                    'status' => (int) $matches['status'],
                    'size' => (int) $matches['size'],
                    'agent' => $matches['agent'],
                    'classification' => $this->classifyRequest($matches['path'], (int) $matches['status']),
                ];
            }
        }

        return $parsed;
    }

    protected function getLaravelLogs(int $limit): array
    {
        $path = storage_path('logs/laravel.log');
        if (! File::exists($path)) {
            return [];
        }

        $lines = File::lines($path)->reverse()->take($limit)->toArray();
        $parsed = [];

        foreach ($lines as $line) {
            if (preg_match('/\[(?<time>[^\]]+)\] (?<env>\w+)\.(?<level>\w+): (?<message>.+)/', $line, $matches)) {
                $msg = $matches['message'];
                $msg = preg_replace('/(password|token|secret|key)=\S+/i', '$1=[REDACTED]', $msg);

                $parsed[] = [
                    'time' => $matches['time'],
                    'level' => $matches['level'],
                    'message' => substr($msg, 0, 200),
                ];
            }
        }

        return array_reverse($parsed);
    }

    protected function getLaravelLogsStructured(int $window): array
    {
        $path = storage_path('logs/laravel.log');
        if (! File::exists($path)) {
            return [];
        }

        $lines = File::lines($path)->reverse()->take($window)->toArray();
        $entries = [];

        foreach ($lines as $line) {
            if (preg_match('/\[(?<time>[^\]]+)\] (?<env>\w+)\.(?<level>\w+): (?<message>.+)/', $line, $matches)) {
                $level = strtolower($matches['level']);
                if (! in_array($level, self::LOG_LEVELS, true)) {
                    continue;
                }
                $msg = preg_replace('/(password|token|secret|key)=\S+/i', '$1=[REDACTED]', $matches['message']);

                $entries[] = [
                    'time' => $matches['time'],
                    'ts' => $this->safeParseTime($matches['time']),
                    'level' => $level,
                    'message' => mb_substr((string) $msg, 0, 200),
                    'ip' => null,
                    'method' => null,
                    'path' => null,
                    'status' => null,
                    'classification' => $level,
                ];
            }
        }

        return $entries;
    }

    protected function getNginxLogsStructured(int $window): array
    {
        $logFile = '/var/log/nginx/access.log';
        $raw = [];

        if (File::exists($logFile)) {
            $raw = File::lines($logFile)->reverse()->take($window)->toArray();
        } else {
            try {
                $process = new Process(['docker', 'logs', 'ewnet-web', '--tail', $window]);
                $process->run();
                if ($process->isSuccessful()) {
                    $raw = array_slice(array_reverse(explode("\n", trim($process->getOutput()))), 0, $window);
                }
            } catch (\Exception $e) {
                \Log::debug('Docker logs failed: '.$e->getMessage());
            }
        }

        $entries = [];
        foreach ($raw as $line) {
            if (empty($line)) {
                continue;
            }
            if (preg_match('/^(?<ip>[\d\.]+) - - \[(?<time>[^\]]+)\] "(?<method>\w+) (?<path>[^ ]+) [^"]+" (?<status>\d+) (?<size>\d+) "(?<referer>[^"]*)" "(?<agent>[^"]*)"/', $line, $matches)) {
                $status = (int) $matches['status'];
                $entries[] = [
                    'time' => $matches['time'],
                    'ts' => $this->safeParseTime($matches['time']),
                    'level' => null,
                    'message' => null,
                    'ip' => $matches['ip'],
                    'method' => $matches['method'],
                    'path' => $matches['path'],
                    'status' => $status,
                    'classification' => $this->classifyRequest($matches['path'], $status),
                ];
            }
        }

        return $entries;
    }

    protected function matchesFilters(array $entry, array $filters, string $type): bool
    {
        if ($type === 'laravel' && ! empty($filters['level'])) {
            if (strcasecmp((string) $entry['level'], (string) $filters['level']) !== 0) {
                return false;
            }
        }

        if ($type === 'nginx' && ! empty($filters['status_class'])) {
            $status = (int) $entry['status'];
            $class = (string) $filters['status_class'];
            $matches = ($class === '2xx') ? ($status >= 200 && $status < 300)
                : (($class === '3xx') ? ($status >= 300 && $status < 400)
                : (($class === '4xx') ? ($status >= 400 && $status < 500)
                : (($class === '5xx') ? ($status >= 500) : true)));
            if (! $matches) {
                return false;
            }
        }

        if (! empty($filters['search'])) {
            $needle = mb_strtolower($filters['search']);
            $haystack = $type === 'laravel'
                ? mb_strtolower((string) ($entry['message'] ?? ''))
                : mb_strtolower(implode(' ', [
                    (string) ($entry['ip'] ?? ''),
                    (string) ($entry['method'] ?? ''),
                    (string) ($entry['path'] ?? ''),
                    (string) ($entry['agent'] ?? ''),
                    (string) ($entry['status'] ?? ''),
                ]));
            if (! str_contains($haystack, $needle)) {
                return false;
            }
        }

        if (! empty($filters['date_from'])) {
            $from = $this->safeParseTime($filters['date_from'].' 00:00:00');
            if ($from && (! $entry['ts'] || $entry['ts']->lt($from))) {
                return false;
            }
        }

        if (! empty($filters['date_to'])) {
            $to = $this->safeParseTime($filters['date_to'].' 23:59:59');
            if ($to && (! $entry['ts'] || $entry['ts']->gt($to))) {
                return false;
            }
        }

        return true;
    }

    protected function safeParseTime(?string $time): ?Carbon
    {
        if (! $time) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('d/M/Y:H:i:s O', $time);
            if ($parsed !== false) {
                return $parsed;
            }
        } catch (\Throwable $e) {
            // fall through to generic parsing
        }

        try {
            return Carbon::parse($time);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function classifyRequest(string $path, int $status): string
    {
        if (str_contains($path, '.env') || str_contains($path, '.git') || str_contains($path, 'wp-admin') || str_contains($path, 'phpmyadmin')) {
            return 'SECURITY PROBE';
        }
        if ($status >= 500) {
            return 'ERROR';
        }
        if ($status >= 400) {
            return 'CLIENT ERROR';
        }

        return 'NORMAL';
    }
}
