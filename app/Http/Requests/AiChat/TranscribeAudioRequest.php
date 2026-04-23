<?php

namespace App\Http\Requests\AiChat;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class TranscribeAudioRequest extends FormRequest
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
            'audio' => ['required', 'file', 'max:51200'],
            'engine' => ['required', 'string', 'in:openai,groq'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $engine = (string) $this->input('engine');
            if ($engine === 'openai' && ! filled(config('services.openai.api_key'))) {
                $validator->errors()->add('engine', 'OpenAI não configurado.');
            }
            if ($engine === 'groq' && ! filled(config('services.groq.api_key'))) {
                $validator->errors()->add('engine', 'Groq não configurado.');
            }
        });
    }
}
