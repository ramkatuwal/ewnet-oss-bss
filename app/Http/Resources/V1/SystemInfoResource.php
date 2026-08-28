<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemInfoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'application' => [
                'name' => $this['application']['name'] ?? config('app.name'),
                'environment' => $this['application']['environment'] ?? app()->environment(),
                'url' => $this['application']['url'] ?? config('app.url'),
            ],
            'runtime' => [
                'laravel' => $this['runtime']['laravel'] ?? app()->version(),
                'php' => $this['runtime']['php'] ?? PHP_VERSION,
                'node' => $this['runtime']['node'] ?? null,
                'composer' => $this['runtime']['composer'] ?? null,
            ],
            'container' => [
                'hostname' => $this['container']['hostname'] ?? gethostname(),
                'memory_limit' => $this['container']['memory_limit'] ?? ini_get('memory_limit'),
                'max_execution_time' => $this['container']['max_execution_time'] ?? ini_get('max_execution_time'),
            ],
            'services' => $this['services'] ?? [],
            'git' => $this['git'] ?? [],
        ];
    }
}
