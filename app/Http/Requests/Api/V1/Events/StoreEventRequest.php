<?php

namespace App\Http\Requests\Api\V1\Events;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:events,slug'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
