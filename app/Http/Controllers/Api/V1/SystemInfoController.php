<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SystemInfoResource;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;

class SystemInfoController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewInfo', \App\Models\SystemSetting::class);

        $data = [
            'application' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'url' => config('app.url'),
            ],
            'runtime' => [
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'node' => $this->getNodeVersion(),
                'composer' => $this->getComposerVersion(),
            ],
            'container' => [
                'hostname' => gethostname(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'services' => [
                'postgresql' => $this->checkPostgresql(),
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

        return new SystemInfoResource($data);
    }

    protected function getNodeVersion(): ?string
    {
        try {
            $output = shell_exec('node -v 2>&1');
            if ($output && !str_contains($output, "not found") && !str_contains($output, "No such file")) { return trim($output); } return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function getComposerVersion(): ?string
    {
        try {
            $output = shell_exec('composer -V 2>&1');
            if ($output && preg_match('/(\d+\.\d+\.\d+)/', $output, $matches)) {
                return $matches[1];
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function checkPostgresql(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    protected function checkRedis(): array
    {
        try {
            Redis::connection()->ping();
            return ['status' => 'healthy'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    protected function checkHorizon(): array
    {
        try {
            $output = shell_exec('php artisan horizon:status 2>&1');
            if (str_contains($output, 'running')) {
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
            $response = Http::timeout(2)->get('http://web/health 2>/dev/null');
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
                return ['status' => 'unhealthy'];
            } catch (\Exception $e2) {
                return ['status' => 'unknown'];
            }
        }
    }

    protected function getGitInfo(): array
    {
        try {
            $commit = shell_exec('git log -1 --format="%H" 2>&1');
            $branch = shell_exec('git branch --show-current 2>&1');
            $tag = shell_exec('git tag --points-at HEAD 2>&1');

            return [
                'commit' => $commit ? substr(trim($commit), 0, 7) : null,
                'branch' => $branch ? trim($branch) : null,
                'tag' => $tag ? trim($tag) : null,
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
