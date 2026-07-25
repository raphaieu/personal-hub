<?php

namespace App\Http\Requests\Api\V1\Events;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEventRequest extends FormRequest
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
        $event = $this->route('event');
        $hexColor = ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'title' => ['sometimes', 'string', 'max:120'],
            'slug' => [
                'sometimes', 'string', 'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('events', 'slug')->ignore($event?->id),
            ],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'requires_ref' => ['sometimes', 'boolean'],
            'requires_turnstile' => ['sometimes', 'boolean'],
            'requires_photo' => ['sometimes', 'boolean'],
            'registration_open' => ['sometimes', 'boolean'],
            'skip_email_confirmation' => ['sometimes', 'boolean'],
            'closed_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'terms_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'privacy_url' => ['sometimes', 'nullable', 'url', 'max:255'],

            // Tema visual da landing (§4.1 da spec da plataforma)
            'theme' => ['sometimes', 'array'],
            'theme.colors' => ['sometimes', 'array'],
            'theme.colors.primary' => $hexColor,
            'theme.colors.secondary' => $hexColor,
            'theme.colors.background' => $hexColor,
            'theme.colors.surface' => $hexColor,
            'theme.colors.text' => $hexColor,
            'theme.colors.textMuted' => ['sometimes', 'string', 'max:64'],
            'theme.fonts' => ['sometimes', 'array'],
            'theme.fonts.heading' => ['sometimes', 'string', Rule::in(config('events.allowed_fonts', []))],
            'theme.fonts.body' => ['sometimes', 'string', Rule::in(config('events.allowed_fonts', []))],
            'theme.mode' => ['sometimes', Rule::in(['dark', 'light'])],
            'theme.borderRadius' => ['sometimes', 'string', 'max:16'],
            'theme.customCss' => ['sometimes', 'nullable', 'string', 'max:5120'],

            // Conteúdo das seções da landing (§4.2 da spec da plataforma)
            'content' => ['sometimes', 'array'],
            'content.*.enabled' => ['sometimes', 'boolean'],
            'content.hero.headline' => ['sometimes', 'nullable', 'string', 'max:120'],
            'content.hero.subheadline' => ['sometimes', 'nullable', 'string', 'max:300'],
            'content.hero.badge' => ['sometimes', 'nullable', 'string', 'max:60'],
            'content.info.dateLabel' => ['sometimes', 'nullable', 'string', 'max:60'],
            'content.info.dateSub' => ['sometimes', 'nullable', 'string', 'max:60'],
            'content.info.timeLabel' => ['sometimes', 'nullable', 'string', 'max:60'],
            'content.info.timeSub' => ['sometimes', 'nullable', 'string', 'max:60'],
            'content.info.locationName' => ['sometimes', 'nullable', 'string', 'max:120'],
            'content.info.locationSub' => ['sometimes', 'nullable', 'string', 'max:160'],
            'content.info.mapUrl' => ['sometimes', 'nullable', 'url', 'max:500'],
            'content.countdown.title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'content.rules.title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'content.rules.items' => ['sometimes', 'array', 'max:12'],
            'content.rules.items.*.icon' => ['sometimes', 'string', Rule::in(config('events.allowed_rule_icons', []))],
            'content.rules.items.*.title' => ['required_with:content.rules.items', 'string', 'max:80'],
            'content.rules.items.*.text' => ['sometimes', 'nullable', 'string', 'max:200'],
            'content.about.title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'content.about.text' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'content.faq.items' => ['sometimes', 'array', 'max:20'],
            'content.faq.items.*.q' => ['required_with:content.faq.items', 'string', 'max:200'],
            'content.faq.items.*.a' => ['required_with:content.faq.items', 'string', 'max:1000'],
            'content.form.title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'content.form.submitLabel' => ['sometimes', 'nullable', 'string', 'max:60'],
            'content.album.title' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
