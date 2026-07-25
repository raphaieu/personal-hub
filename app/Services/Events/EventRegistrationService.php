<?php

namespace App\Services\Events;

use App\DTO\Events\EventRegistrationResult;
use App\Enums\Events\GuestStatus;
use App\Mail\Events\GuestInterestConfirmationMail;
use App\Models\Event;
use App\Models\Guest;
use App\Models\ReferralLink;
use App\Services\Events\MercadoPago\MercadoPagoCheckoutService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EventRegistrationService
{
    /**
     * Campos do schema com coluna própria na tabela guests — todo o resto
     * habilitado no schema é persistido em custom_data.
     */
    private const NATIVE_GUEST_FIELDS = ['name', 'email', 'phone', 'birth_year', 'photo', 'consent_terms'];

    public function __construct(
        private readonly EventPublicConfigService $configService,
        private readonly TurnstileVerifier $turnstileVerifier,
        private readonly MercadoPagoCheckoutService $checkoutService,
        private readonly GuestTicketIssuanceService $ticketIssuanceService,
    ) {}

    /**
     * @throws ValidationException
     */
    public function createFromPublicRequest(Event $event, Request $request): EventRegistrationResult
    {
        if ($event->requires_turnstile && ! $this->turnstileVerifier->verify((string) $request->header('X-Turnstile-Token'))) {
            throw ValidationException::withMessages([
                'turnstile' => ['Validação anti-bot falhou. Atualize a página e tente novamente.'],
            ]);
        }

        if ($event->requires_payment) {
            $this->assertPaymentConfigured($event);
        }

        $fields = $this->configService->resolvedFormFields($event);
        $rules = $this->buildValidationRules($fields);
        $validator = Validator::make($request->all(), $rules);
        $validator->sometimes('consent_terms', ['accepted'], fn (): bool => true);

        $validated = $validator->validate();

        $email = strtolower(trim((string) $validated['email']));
        $duplicate = Guest::query()
            ->where('event_id', $event->id)
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        if ($duplicate) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Este e-mail já está cadastrado neste evento. Caso não tenha recebido seu ingresso, entre em contato com o organizador.',
            ], 409));
        }

        $referralLinkId = null;
        if ($event->requires_ref) {
            $token = $request->header('X-Ref-Token');
            $link = ReferralLink::query()
                ->where('event_id', $event->id)
                ->where('token', (string) $token)
                ->first();

            if ($link === null || ! $link->isValidNow()) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Seu link de convite é inválido ou expirou. Solicite um novo ao organizador.',
                ], 401));
            }
            $referralLinkId = $link->id;
        }

        $requiresPayment = (bool) $event->requires_payment;
        $skipEmailConfirmation = ! $requiresPayment && (bool) $event->skip_email_confirmation;
        $reservationMinutes = (int) config('events.payment_reservation_minutes', 15);

        $guest = DB::transaction(function () use ($event, $validated, $email, $referralLinkId, $request, $fields, $requiresPayment, $skipEmailConfirmation, $reservationMinutes): Guest {
            $event->refresh();

            if ($event->capacity !== null && $event->occupancyCount() >= $event->capacity) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Infelizmente todas as vagas foram preenchidas.',
                ], 409));
            }

            $guestAttributes = [
                'event_id' => $event->id,
                'referral_link_id' => $referralLinkId,
                'name' => $validated['name'],
                'email' => $email,
                'phone' => isset($validated['phone']) ? (string) $validated['phone'] : null,
                'birth_year' => isset($validated['birth_year']) ? (int) $validated['birth_year'] : null,
                'custom_data' => $this->extractCustomData($fields, $validated),
                'consent_terms_at' => now(),
            ];

            if ($requiresPayment) {
                $guestAttributes['status'] = GuestStatus::PendingPayment;
                $guestAttributes['payment_return_token'] = Str::random(64);
                $guestAttributes['payment_expires_at'] = now()->addMinutes($reservationMinutes);
                $guestAttributes['email_confirmation_token'] = null;
            } elseif ($skipEmailConfirmation) {
                $guestAttributes['status'] = GuestStatus::Confirmed;
                $guestAttributes['email_confirmed_at'] = now();
                $guestAttributes['email_confirmation_token'] = null;
            } else {
                $guestAttributes['status'] = GuestStatus::PendingEmail;
                $guestAttributes['email_confirmation_token'] = Str::random(64);
            }

            $guest = Guest::query()->create($guestAttributes);

            $photoField = $this->firstEnabledField($fields, 'photo');
            if ($photoField !== null && ($photoField['enabled'] ?? false)) {
                /** @var UploadedFile|null $file */
                $file = $request->file('photo');
                if (($photoField['required'] ?? false) && ($file === null || ! $file->isValid())) {
                    $guest->delete();

                    throw ValidationException::withMessages([
                        'photo' => ['Envie uma foto válida.'],
                    ]);
                }

                if ($file !== null && $file->isValid()) {
                    $dir = "events/guests/{$guest->id}";
                    $path = $file->store($dir, (string) config('events.guest_photos_disk', 's3'));
                    $guest->forceFill(['photo_path' => $path])->save();
                }
            }

            if ($referralLinkId !== null) {
                ReferralLink::query()->whereKey($referralLinkId)->increment('used_count');
            }

            return $guest->fresh();
        });

        $guest->loadMissing('event.mercadoPagoAccount');
        $event->loadMissing('mercadoPagoAccount');

        if ($requiresPayment) {
            $checkoutUrl = $this->checkoutService->createCheckout($guest, $event);

            return new EventRegistrationResult(
                guest: $guest,
                flow: 'checkout',
                checkoutUrl: $checkoutUrl,
            );
        }

        if ($skipEmailConfirmation) {
            $this->ticketIssuanceService->queueTicketEmail($guest);

            return new EventRegistrationResult(
                guest: $guest,
                flow: 'ticket_sent',
            );
        }

        Mail::to($guest->email)->queue(new GuestInterestConfirmationMail($guest));

        return new EventRegistrationResult(
            guest: $guest,
            flow: 'email_confirmation',
        );
    }

    private function assertPaymentConfigured(Event $event): void
    {
        if ($event->ticket_amount_cents === null || $event->ticket_amount_cents <= 0) {
            throw ValidationException::withMessages([
                'payment' => ['Este evento não possui valor de ingresso configurado.'],
            ]);
        }

        $account = $event->mercadoPagoAccount;
        if ($account === null) {
            throw ValidationException::withMessages([
                'payment' => ['Este evento não possui conta Mercado Pago configurada.'],
            ]);
        }

        if ($account->owner_id !== $event->owner_id) {
            throw ValidationException::withMessages([
                'payment' => ['Conta Mercado Pago inválida para este evento.'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function buildValidationRules(array $fields): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'consent_terms' => ['required', 'accepted'],
        ];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '' || $name === 'name' || $name === 'email') {
                continue;
            }

            $enabled = (bool) ($field['enabled'] ?? false);
            if (! $enabled) {
                continue;
            }

            $required = (bool) ($field['required'] ?? false);
            $suffix = $required ? 'required' : 'nullable';

            match ($name) {
                'phone' => $rules['phone'] = [$suffix, 'string', 'max:32'],
                'birth_year' => $rules['birth_year'] = [$suffix, 'integer', 'min:'.(int) ($field['min'] ?? 1940), 'max:'.(int) ($field['max'] ?? 2010)],
                'photo' => $rules['photo'] = array_values(array_filter([
                    $suffix === 'required' ? 'required' : 'nullable',
                    'file',
                    'image',
                    'max:5120',
                ])),
                default => $rules[$name] = $this->customFieldRules($field, $suffix),
            };
        }

        return $rules;
    }

    /**
     * Regras para campos custom do schema (valores persistidos em custom_data).
     *
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function customFieldRules(array $field, string $suffix): array
    {
        return match ((string) ($field['type'] ?? 'text')) {
            'number' => array_values(array_filter([
                $suffix,
                'integer',
                isset($field['min']) ? 'min:'.(int) $field['min'] : null,
                isset($field['max']) ? 'max:'.(int) $field['max'] : null,
            ])),
            'email' => [$suffix, 'email', 'max:255'],
            'tel' => [$suffix, 'string', 'max:32'],
            'textarea' => [$suffix, 'string', 'max:'.(int) ($field['maxLength'] ?? 2000)],
            'select' => [$suffix, 'string', Rule::in(array_map('strval', (array) ($field['options'] ?? [])))],
            default => [$suffix, 'string', 'max:'.(int) ($field['maxLength'] ?? 255)],
        };
    }

    /**
     * Extrai do payload validado os campos custom do schema (não nativos).
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>|null
     */
    private function extractCustomData(array $fields, array $validated): ?array
    {
        $custom = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '' || in_array($name, self::NATIVE_GUEST_FIELDS, true)) {
                continue;
            }
            if (! ($field['enabled'] ?? false)) {
                continue;
            }
            if (array_key_exists($name, $validated)) {
                $custom[$name] = $validated[$name];
            }
        }

        return $custom !== [] ? $custom : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function firstEnabledField(array $fields, string $name): ?array
    {
        foreach ($fields as $field) {
            if (($field['name'] ?? '') === $name) {
                return $field;
            }
        }

        return null;
    }
}
