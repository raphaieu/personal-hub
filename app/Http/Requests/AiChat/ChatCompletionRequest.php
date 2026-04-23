<?php

namespace App\Http\Requests\AiChat;

use App\Services\AiChatCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ChatCompletionRequest extends FormRequest
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
        $maxImages = (int) config('ai_chat.max_images_per_message', 4);

        return [
            'provider' => ['required', 'string', 'in:ollama,groq,anthropic,openai'],
            'model' => ['required', 'string', 'max:128'],
            'message' => ['nullable', 'string', 'max:100000'],
            'system' => ['nullable', 'string', 'max:8000'],
            'images' => ['nullable', 'array', 'max:'.$maxImages],
            'images.*.data' => ['required_with:images', 'string'],
            'images.*.mime' => ['required_with:images', 'string', 'in:image/jpeg,image/png,image/gif,image/webp'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $images = $this->input('images');
        if (! is_array($images)) {
            return;
        }

        foreach ($images as $i => $img) {
            if (! is_array($img)) {
                continue;
            }
            $data = $img['data'] ?? '';
            if (is_string($data) && str_starts_with($data, 'data:')) {
                $parts = explode(',', $data, 2);
                $images[$i]['data'] = $parts[1] ?? $data;
            }
        }

        $this->merge(['images' => array_values($images)]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $msg = $this->input('message');
            $images = $this->input('images', []);
            if (($msg === null || trim((string) $msg) === '') && (! is_array($images) || $images === [])) {
                $validator->errors()->add('message', 'Informe uma mensagem ou ao menos uma imagem.');
            }

            /** @var AiChatCatalogService $catalog */
            $catalog = app(AiChatCatalogService::class);
            $provider = (string) $this->input('provider');
            $model = (string) $this->input('model');

            $tags = $provider === 'ollama' ? $catalog->ollamaTagNames() : [];
            if (! $catalog->isModelAllowed($provider, $model, $tags)) {
                $validator->errors()->add('model', 'Modelo não permitido para este provedor.');
            }

            $caps = $catalog->capabilitiesFor($provider, $model, $tags);
            if (is_array($images) && $images !== [] && ! $caps['vision']) {
                $validator->errors()->add('images', 'Este modelo não suporta visão (imagem).');
            }

            $maxKb = (int) config('ai_chat.max_image_payload_kb');
            foreach ($images ?? [] as $i => $img) {
                if (! is_array($img)) {
                    continue;
                }
                $raw = base64_decode((string) ($img['data'] ?? ''), true);
                if ($raw === false) {
                    $validator->errors()->add('images.'.$i.'.data', 'Base64 inválido.');

                    continue;
                }
                if (strlen($raw) > $maxKb * 1024) {
                    $validator->errors()->add('images.'.$i.'.data', "Imagem acima de {$maxKb} KiB.");
                }
            }
        });
    }
}
