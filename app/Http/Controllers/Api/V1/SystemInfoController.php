<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class SystemInfoController extends Controller
{
    public function index(Request $request)
    {
        // Check authorization
        if (!$request->user() || !$request->user()->can('system.info.view')) {
            abort(403, 'Unauthorized to view system information');
        }

        $data = [
            'application' => [
                'name' => config('app.name'),
                'environment' => config('app.env'),
                'url' => config('app.url'),
                'debug' => config('app.debug'),
            ],
            'runtime' => [
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'node' => $this->getNodeVersion(),
                'composer' => $this->getComposerVersion(),
            ],
            'container' => [
                'os' => php_uname('s') . ' ' . php_uname('r'),
                'architecture' => php_uname('m'),
                'hostname' => gethostname(),
                'user' => get_current_user(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time') . 's',
            ],
            'system' => [
                'cpu' => $this->getCpuInfo(),
                'memory' => $this->getMemoryInfo(),
                'disk' => $this->getDiskInfo(),
                'uptime' => $this->getUptime(),
            ],
            'services' => [
                'postgresql' => $this->checkPostgres(),
                'redis' => $this->checkRedis(),
                'horizon' => $this->checkHorizon(),
                'nginx' => $this->checkNginx(),
            ],
            'git' => $this->getGitInfo(),
        ];

        AuditService::log('system.info.viewed', 'success', null, [
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => $data]);
    }

    protected function getNodeVersion(): ?string
    {
        try {
            $process = new \Symfony\Component\Process\Process(['node', '-v']);
            $process->setTimeout(2);
            $process->run();
            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getComposerVersion(): ?string
    {
        try {
            $process = new \Symfony\Component\Process\Process(['composer', '--version']);
            $process->setTimeout(2);
            $process->run();
            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                if (preg_match('/Composer version (\d+\.\d+\.\d+)/', $output, $matches)) {
                    return $matches[1];
                }
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getCpuInfo(): array
    {
        try {
            $stat = @file_get_contents('/proc/stat');
            if ($stat) {
                $lines = explode("\n", $stat);
                foreach ($lines as $line) {
                    if (str_starts_with($line, 'cpu ')) {
                        $parts = preg_split('/\s+/', trim($line));
                        if (count($parts) >= 8) {
                            $user = (int)$parts[1];
                            $nice = (int)$parts[2];
                            $system = (int)$parts[3];
                            $idle = (int)$parts[4];
                            $iowait = (int)$parts[5];
                            $irq = (int)$parts[6];
                            $softirq = (int)$parts[7];
                            $total = $user + $nice + $system + $idle + $iowait + $irq + $softirq;
                            $usage = $total > 0 ? round(100 - ($idle / $total * 100), 1) : 0;
                            return [
                                'usage_percent' => $usage,
                                'cores' => (int)shell_exec('nproc 2>/dev/null') ?: 1,
                            ];
                        }
                        break;
                    }
                }
            }
            return ['usage_percent' => 0, 'cores' => 1];
        } catch (\Exception $e) {
            return ['usage_percent' => 0, 'cores' => 1, 'error' => $e->getMessage()];
        }
    }

    protected function getMemoryInfo(): array
    {
        try {
            $meminfo = @file_get_contents('/proc/meminfo');
            if ($meminfo) {
                $total = $available = 0;
                $lines = explode("\n", $meminfo);
                foreach ($lines as $line) {
                    if (str_starts_with($line, 'MemTotal:')) {
                        $total = (int)preg_replace('/[^0-9]/', '', $line);
                    }
                    if (str_starts_with($line, 'MemAvailable:')) {
                        $available = (int)preg_replace('/[^0-9]/', '', $line);
                    }
                }
                if ($total > 0) {
                    $totalMB = round($total / 1024, 1);
                    $availableMB = round($available / 1024, 1);
                    $usedMB = round($totalMB - $availableMB, 1);
                    return [
                        'total_mb' => $totalMB,
                        'used_mb' => $usedMB,
                        'available_mb' => $availableMB,
                        'usage_percent' => round(($usedMB / $totalMB) * 100, 1),
                    ];
                }
            }
            return ['total_mb' => 0, 'used_mb' => 0, 'available_mb' => 0, 'usage_percent' => 0];
        } catch (\Exception $e) {
            return ['total_mb' => 0, 'used_mb' => 0, 'available_mb' => 0, 'usage_percent' => 0];
        }
    }

    protected function getDiskInfo(): array
    {
        try {
            $path = base_path();
            $total = disk_total_space($path);
            $free = disk_free_space($path);
            if ($total > 0) {
                $totalGB = round($total / 1024 / 1024 / 1024, 2);
                $usedGB = round(($total - $free) / 1024 / 1024 / 1024, 2);
                $freeGB = round($free / 1024 / 1024 / 1024, 2);
                return [
                    'total_gb' => $totalGB,
                    'used_gb' => $usedGB,
                    'free_gb' => $freeGB,
                    'usage_percent' => round((($total - $free) / $total) * 100, 1),
                    'mount_point' => $path,
                ];
            }
            return ['total_gb' => 0, 'used_gb' => 0, 'free_gb' => 0, 'usage_percent' => 0];
        } catch (\Exception $e) {
            return ['total_gb' => 0, 'used_gb' => 0, 'free_gb' => 0, 'usage_percent' => 0];
        }
    }

    protected function getUptime(): ?string
    {
        try {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = (int)explode(' ', $uptime)[0];
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                if ($days > 0) {
                    return "{$days}d {$hours}h {$minutes}m";
                }
                return "{$hours}h {$minutes}m";
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function checkPostgres(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    protected function checkRedis(): array
    {
        try {
            Redis::ping();
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    protected function checkHorizon(): array
    {
        try {
            // Get the raw Redis client to bypass the Laravel prefix
            $redis = Redis::connection()->client();

            // Use SCAN to find supervisor keys (KEYS applies the prefix, SCAN does not)
            $iterator = null;
            $supervisorKeys = [];
            do {
                $batch = $redis->scan($iterator, 'ewnet_horizon:supervisor:*', 10);
                if ($batch === false) break;
                foreach ($batch as $key) {
                    $supervisorKeys[] = $key;
                }
            } while ($iterator != 0);

            if (!empty($supervisorKeys)) {
                return ['status' => 'running'];
            }

            // Check if masters zset has entries
            $masters = $redis->zRange('ewnet_horizon:masters', 0, -1);
            if (!empty($masters)) {
                return ['status' => 'running'];
            }

            // Check if supervisors zset has entries
            $supervisors = $redis->zRange('ewnet_horizon:supervisors', 0, -1);
            if (!empty($supervisors)) {
                return ['status' => 'running'];
            }

            // No Horizon found
            return ['status' => 'stopped'];
        } catch (\Exception $e) {
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }
    }

    protected function checkNginx(): array
    {
        try {
            $response = Http::timeout(2)->get('http://web/health');
            if ($response->successful()) {
                return ['status' => 'healthy'];
            }
            return ['status' => 'unhealthy'];
        } catch (\Exception $e) {
            try {
                $fp = @fsockopen('web', 80, $errno, $errstr, 1);
                if ($fp) {
                    fclose($fp);
                    return ['status' => 'healthy'];
                }
                return ['status' => 'unhealthy', 'error' => $errstr];
            } catch (\Exception $e2) {
                return ['status' => 'unknown', 'error' => $e2->getMessage()];
            }
        }
    }

    protected function getGitInfo(): array
    {
        try {
            $gitDir = base_path('.git');

            if (!is_dir($gitDir)) {
                return [
                    'commit' => null,
                    'branch' => null,
                    'tag' => null,
                ];
            }

            $headFile = $gitDir . '/HEAD';
            $branch = null;
            $commit = null;

            if (file_exists($headFile)) {
                $headContent = trim(file_get_contents($headFile));

                if (strpos($headContent, 'ref: ') === 0) {
                    $branch = str_replace('ref: refs/heads/', '', $headContent);
                    $refFile = $gitDir . '/refs/heads/' . $branch;
                    if (file_exists($refFile)) {
                        $commit = trim(file_get_contents($refFile));
                    }
                } else {
                    $commit = $headContent;
                }
            }

            $tag = null;
            $tagsDir = $gitDir . '/refs/tags';
            if ($commit && is_dir($tagsDir)) {
                $tags = scandir($tagsDir);
                foreach ($tags as $tagName) {
                    if ($tagName === '.' || $tagName === '..') continue;
                    $tagFile = $tagsDir . '/' . $tagName;
                    if (file_exists($tagFile)) {
                        $tagCommit = trim(file_get_contents($tagFile));
                        if ($tagCommit === $commit) {
                            $tag = $tagName;
                            break;
                        }
                    }
                }
            }

            return [
                'commit' => $commit ? substr($commit, 0, 7) : null,
                'branch' => $branch,
                'tag' => $tag,
            ];
        } catch (\Exception $e) {
            return [
                'commit' => null,
                'branch' => null,
                'tag' => null,
            ];
        }
    }
}
