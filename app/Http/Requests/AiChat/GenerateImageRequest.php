<?php

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;

class GenerateImageRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['nullable', 'string', 'in:1024x1024,1024x1792,1792x1024'],
            'response_format' => ['nullable', 'string', 'in:url,b64_json'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! filled(config('services.openai.api_key'))) {
                $validator->errors()->add('prompt', 'OpenAI não configurado para geração de imagens.');
            }
        });
    }
}
