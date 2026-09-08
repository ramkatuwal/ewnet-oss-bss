<?php

namespace App\Http\Resources\V1;

use App\Services\Fim\StrandPath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StrandPathResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var StrandPath $path */
        $path = $this->resource;

        return $path->toArray();
    }
}
