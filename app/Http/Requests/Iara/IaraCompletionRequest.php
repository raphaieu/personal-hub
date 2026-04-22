<?php

namespace App\Http\Requests\Iara;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IaraCompletionRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'min:1', 'max:100000'],
            'mode' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'classification',
                    'classify',
                    'sentiment',
                    'summary_short',
                    'chat',
                    'chat_default',
                    'default',
                    'chat_long',
                    'long',
                ]),
            ],
            'system' => ['sometimes', 'nullable', 'string', 'max:16000'],
            'expect_json' => ['sometimes', 'boolean'],
        ];
    }
}
