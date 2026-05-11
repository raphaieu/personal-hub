<?php


namespace App\Services\Events;

use App\Enums\Events\EventStatus;
use App\Models\Event;
use App\Models\ReferralLink;
use Carbon\CarbonInterface;

final class EventPublicConfigService
{
    /**
     * Default form fields aligned with docs/events/API_Contract.md (subset for MVP).
     *
     * @var array<int, array<string, mixed>>
     */
    private const DEFAULT_FORM_FIELDS = [
        [
            'name' => 'name',
            'type' => 'text',
            'label' => 'Nome completo',
            'placeholder' => 'Seu nome',
            'required' => true,
            'enabled' => true,
            'maxLength' => 255,
        ],
        [
            'name' => 'email',
            'type' => 'email',
            'label' => 'E-mail',
            'placeholder' => 'seu@email.com',
            'required' => true,
            'enabled' => true,
            'maxLength' => 255,
        ],
        [
            'name' => 'phone',
            'type' => 'tel',
            'label' => 'WhatsApp',
            'placeholder' => '(00) 00000-0000',
            'required' => false,
            'enabled' => true,
            'mask' => '(00) 00000-0000',
        ],
        [
            'name' => 'photo',
            'type' => 'file',
            'label' => 'Sua melhor foto',
            'required' => true,
            'enabled' => true,
            'accept' => 'image/jpeg,image/png,image/webp',
            'maxSizeBytes' => 5_242_880,
            'hint' => 'Foto de rosto, máx. 5MB',
        ],
        [
            'name' => 'birth_year',
            'type' => 'number',
            'label' => 'Ano de nascimento',
            'required' => false,
            'enabled' => false,
            'min' => 1940,
            'max' => 2010,
        ],
    ];

    /**
     * Resolved field definitions for validation and {@see self::mergeFormFields()}.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolvedFormFields(Event $event): array
    {
        return $this->mergeFormFields($event);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Event $event, ?string $refQueryToken): array
    {
        $tz = $event->timezone ?: 'America/Sao_Paulo';
        $startsAt = $event->starts_at?->clone()->timezone($tz);
        $endsAt = $event->ends_at?->clone()->timezone($tz);

        $eventPayload = [
            'slug' => $event->slug,
            'title' => $event->title,
            'status' => $this->publicStatus($event),
            'startsAt' => $this->iso8601($startsAt),
            'endsAt' => $this->iso8601($endsAt),
        ];

        $referral = $this->resolveReferral($event, $refQueryToken);
        $registration = $this->buildRegistrationBlock($event, $referral);

        $data = [
            'event' => $eventPayload,
            'registration' => $registration['registration'],
        ];

        if ($registration['form'] !== null) {
            $data['form'] = $registration['form'];
        } else {
            $data['form'] = null;
        }

        $data['ref'] = $event->isEnded() ? null : $referral['ref_block'];

        if ($event->isEnded()) {
            $data['album'] = [
                'available' => false,
                'totalPhotos' => 0,
            ];
        }

        return $data;
    }

    private function publicStatus(Event $event): string
    {
        if ($event->isEnded()) {
            return 'ended';
        }

        return $event->status->value;
    }

    private function iso8601(?CarbonInterface $dt): ?string
    {
        return $dt?->toIso8601String();
    }

    /**
     * @return array{registration: array<string, mixed>, form: ?array<string, mixed>, ref_block: array<string, mixed>|null}
     */
    private function buildRegistrationBlock(Event $event, array $referral): array
    {
        $refBlock = $referral['ref_block'];

        if ($event->status === EventStatus::Draft) {
            return [
                'registration' => [
                    'open' => false,
                    'closedMessage' => 'Este evento não está disponível.',
                    'spotsLeft' => null,
                    'totalCapacity' => $event->capacity,
                    'requiresRef' => $event->requires_ref,
                    'requiresTurnstile' => $event->requires_turnstile,
                ],
                'form' => null,
                'ref_block' => $refBlock,
            ];
        }

        if ($event->isEnded()) {
            return [
                'registration' => [
                    'open' => false,
                    'closedMessage' => $event->closed_message ?? 'Este evento já aconteceu! Confira as fotos no álbum.',
                    'spotsLeft' => null,
                    'totalCapacity' => $event->capacity,
                    'requiresRef' => false,
                    'requiresTurnstile' => false,
                ],
                'form' => null,
                'ref_block' => null,
            ];
        }

        if ($event->requires_ref && ! ($refBlock['valid'] ?? false)) {
            return [
                'registration' => [
                    'open' => false,
                    'closedMessage' => 'Este é um evento privado. Solicite um link de convite ao organizador.',
                    'spotsLeft' => null,
                    'totalCapacity' => $event->capacity,
                    'requiresRef' => true,
                    'requiresTurnstile' => $event->requires_turnstile,
                ],
                'form' => null,
                'ref_block' => $refBlock,
            ];
        }

        if (! $event->registration_open) {
            return [
                'registration' => [
                    'open' => false,
                    'closedMessage' => $event->closed_message ?? 'As inscrições para este evento foram encerradas.',
                    'spotsLeft' => $this->spotsLeft($event),
                    'totalCapacity' => $event->capacity,
                    'requiresRef' => $event->requires_ref,
                    'requiresTurnstile' => $event->requires_turnstile,
                ],
                'form' => null,
                'ref_block' => $refBlock,
            ];
        }

        $spotsLeft = $this->spotsLeft($event);
        if ($event->capacity !== null && $spotsLeft !== null && $spotsLeft <= 0) {
            return [
                'registration' => [
                    'open' => false,
                    'closedMessage' => 'As inscrições para este evento foram encerradas.',
                    'spotsLeft' => 0,
                    'totalCapacity' => $event->capacity,
                    'requiresRef' => $event->requires_ref,
                    'requiresTurnstile' => $event->requires_turnstile,
                ],
                'form' => null,
                'ref_block' => $refBlock,
            ];
        }

        $termsUrl = $event->terms_url ?? config('events.default_terms_url');
        $privacyUrl = $event->privacy_url ?? config('events.default_privacy_url');

        return [
            'registration' => [
                'open' => true,
                'closedMessage' => null,
                'spotsLeft' => $spotsLeft,
                'totalCapacity' => $event->capacity,
                'requiresRef' => $event->requires_ref,
                'requiresTurnstile' => $event->requires_turnstile,
            ],
            'form' => [
                'fields' => $this->mergeFormFields($event),
                'termsUrl' => $termsUrl,
                'privacyUrl' => $privacyUrl,
                'submitLabel' => 'Confirmar presença',
            ],
            'ref_block' => $refBlock,
        ];
    }

    private function spotsLeft(Event $event): ?int
    {
        if ($event->capacity === null) {
            return null;
        }

        $used = $event->confirmedGuestsCount();

        return max(0, $event->capacity - $used);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mergeFormFields(Event $event): array
    {
        $custom = $event->guest_form_schema_json;
        if (! is_array($custom) || $custom === []) {
            return self::DEFAULT_FORM_FIELDS;
        }

        // MVP: if schema provides full fields array use it; else merge keys by name.
        if (isset($custom['fields']) && is_array($custom['fields'])) {
            return $custom['fields'];
        }

        return self::DEFAULT_FORM_FIELDS;
    }

    /**
     * @return array{ref_block: array<string, mixed>|null, link: ?ReferralLink}
     */
    private function resolveReferral(Event $event, ?string $refQueryToken): array
    {
        if (! $event->requires_ref) {
            return [
                'ref_block' => null,
                'link' => null,
            ];
        }

        if ($refQueryToken === null || $refQueryToken === '') {
            return [
                'ref_block' => [
                    'valid' => false,
                    'name' => null,
                    'expiresAt' => null,
                ],
                'link' => null,
            ];
        }

        $link = ReferralLink::query()
            ->where('event_id', $event->id)
            ->where('token', $refQueryToken)
            ->first();

        if ($link === null || ! $link->isValidNow()) {
            return [
                'ref_block' => [
                    'valid' => false,
                    'name' => null,
                    'expiresAt' => null,
                ],
                'link' => null,
            ];
        }

        return [
            'ref_block' => [
                'valid' => true,
                'name' => $link->name,
                'expiresAt' => $link->expires_at?->clone()->timezone($event->timezone ?? 'America/Sao_Paulo')->toIso8601String(),
            ],
            'link' => $link,
        ];
    }
}
