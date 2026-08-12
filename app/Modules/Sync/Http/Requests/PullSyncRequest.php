<?php

declare(strict_types=1);

namespace App\Modules\Sync\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PullSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:26'],
            'entity_type' => ['required', 'string'],
            'cursor' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
