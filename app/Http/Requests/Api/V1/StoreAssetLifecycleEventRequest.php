<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AssetLifecycleEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetLifecycleEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assets.lifecycle.create');
    }

    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::in(AssetLifecycleEvent::EVENT_TYPES)],
            'status_before' => ['nullable', 'string', 'max:50'],
            'status_after' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
            'event_date' => ['nullable', 'date'],
        ];
    }
}
