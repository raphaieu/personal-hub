<?php

namespace App\Http\Requests\Webhook;

use Illuminate\Foundation\Http\FormRequest;

class StoreWhatsAppWebhookRequest extends FormRequest
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
            'event' => ['nullable', 'string', 'max:191'],
            'instance' => ['nullable', 'string', 'max:191'],
            'data' => ['nullable', 'array'],
            'destination' => ['nullable', 'string', 'max:255'],
            'apikey' => ['nullable', 'string'],
            'sender' => ['nullable', 'string'],
        ];
    }
}
