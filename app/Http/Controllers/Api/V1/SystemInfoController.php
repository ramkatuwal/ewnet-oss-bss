<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
                'user' => get_current_user(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time') . 's',
            ],
            'services' => [
                'postgresql' => $this->checkPostgres(),
                'redis' => $this->checkRedis(),
                'horizon' => $this->checkHorizon(),
                'nginx' => $this->checkNginx(),
            ],
            'git' => $this->getGitInfo(),
        ];

        // Audit: system.info.viewed
        AuditService::log('system.info.viewed', 'success', null, [
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => $data]);
    }

    protected function getNodeVersion(): ?string
    {
        try {
            $output = @shell_exec('node -v 2>/dev/null');
            if ($output && !str_contains($output, "not found") && !str_contains($output, "No such file")) {
                return trim($output);
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getComposerVersion(): ?string
    {
        try {
            $output = @shell_exec('composer --version 2>/dev/null');
            if ($output && preg_match('/Composer version (\d+\.\d+\.\d+)/', $output, $matches)) {
                return $matches[1];
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
            \Illuminate\Support\Facades\Redis::ping();
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    protected function checkHorizon(): array
    {
        try {
            // Check if Horizon supervisor is running by checking Redis
            $redis = \Illuminate\Support\Facades\Redis::connection();
            $horizonKey = 'horizon:supervisors';
            $supervisors = $redis->smembers($horizonKey);
            
            if (!empty($supervisors)) {
                // Check if any supervisor is active (has recent activity)
                foreach ($supervisors as $supervisor) {
                    $lastActivity = $redis->get("horizon:supervisor:{$supervisor}:last_activity_at");
                    if ($lastActivity) {
                        $lastActivityTime = (int)$lastActivity;
                        $now = time();
                        // Consider active if activity within last 60 seconds
                        if (($now - $lastActivityTime) < 60) {
                            return ['status' => 'running'];
                        }
                    }
                }
            }
            
            // Fallback: check if horizon:status command works (when running as root)
            $output = @shell_exec('php artisan horizon:status 2>/dev/null');
            if ($output && (str_contains($output, 'running') || str_contains($output, 'Horizon is running'))) {
                return ['status' => 'running'];
            }
            
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
            // Try checking if web container is responding on port 80
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

            // Read branch from HEAD
            $headFile = $gitDir . '/HEAD';
            $branch = null;
            $commit = null;
            
            if (file_exists($headFile)) {
                $headContent = trim(file_get_contents($headFile));
                
                if (strpos($headContent, 'ref: ') === 0) {
                    // It's a branch reference
                    $branch = str_replace('ref: refs/heads/', '', $headContent);
                    
                    // Read the commit hash from the branch ref file
                    $refFile = $gitDir . '/refs/heads/' . $branch;
                    if (file_exists($refFile)) {
                        $commit = trim(file_get_contents($refFile));
                    }
                } else {
                    // It's a detached HEAD (direct commit hash)
                    $commit = $headContent;
                }
            }

            // Read tags that point to this commit
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
