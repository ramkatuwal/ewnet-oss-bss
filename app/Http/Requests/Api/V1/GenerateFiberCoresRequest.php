<?php

namespace App\Http\Requests\Api\V1;

use App\Models\FiberCore;
use Illuminate\Foundation\Http\FormRequest;

class GenerateFiberCoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FiberCore::class);
    }

    public function rules(): array
    {
        return [
            'start_core_number' => ['required', 'integer', 'min:1'],
            'count' => ['required', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
