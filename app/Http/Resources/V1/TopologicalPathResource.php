<?php

namespace App\Http\Resources\V1;

use App\Services\Fim\TopologicalPath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopologicalPathResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var TopologicalPath $path */
        $path = $this->resource;

        return $path->toArray();
    }
}
