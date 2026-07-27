<?php

namespace App\Http\Requests\Api\V1\Events;

use Illuminate\Foundation\Http\FormRequest;

final class FlyerDraftRequest extends FormRequest
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
            'flyer' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'flyer.required' => 'Envie o flyer do evento.',
            'flyer.image' => 'O arquivo precisa ser uma imagem.',
            'flyer.mimes' => 'Formatos aceitos: JPG, PNG ou WebP.',
            'flyer.max' => 'O flyer pode ter no máximo 10MB.',
        ];
    }
}
