<?php

declare(strict_types=1);

namespace App\Modules\Sync\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the envelope only — device_id and the shape of each entity.
 * What is inside an entity's data is the intake contracts' problem: they
 * already throw a clear, typed exception for anything wrong with it, and
 * duplicating that checking here would be two places to keep in sync with
 * one set of rules.
 */
class PushSyncRequest extends FormRequest
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
            'device_label' => ['nullable', 'string', 'max:255'],
            'entities' => ['required', 'array', 'min:1', 'max:50'],
            'entities.*.client_id' => ['required', 'string', 'max:26'],
            'entities.*.entity_type' => ['required', 'string', 'in:order,visit_outcome'],
            'entities.*.data' => ['required', 'array'],
        ];
    }
}
