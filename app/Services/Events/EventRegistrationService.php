<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Enums\Events\GuestStatus;
use App\Mail\Events\GuestInterestConfirmationMail;
use App\Models\Event;
use App\Models\Guest;
use App\Models\ReferralLink;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EventRegistrationService
{
    public function __construct(
        private readonly EventPublicConfigService $configService,
        private readonly TurnstileVerifier $turnstileVerifier,
    ) {}

    /**
     * @throws ValidationException
     */
    public function createFromPublicRequest(Event $event, Request $request): Guest
    {
        if ($event->requires_turnstile && ! $this->turnstileVerifier->verify((string) $request->header('X-Turnstile-Token'))) {
            throw ValidationException::withMessages([
                'turnstile' => ['Validação anti-bot falhou. Atualize a página e tente novamente.'],
            ]);
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

        $guest = DB::transaction(function () use ($event, $validated, $email, $referralLinkId, $request, $fields): Guest {
            $event->refresh();

            if ($event->capacity !== null && $event->confirmedGuestsCount() >= $event->capacity) {
                throw new HttpResponseException(response()->json([
                    'success' => false,
                    'message' => 'Infelizmente todas as vagas foram preenchidas.',
                ], 409));
            }

            $guest = Guest::query()->create([
                'event_id' => $event->id,
                'referral_link_id' => $referralLinkId,
                'name' => $validated['name'],
                'email' => $email,
                'phone' => isset($validated['phone']) ? (string) $validated['phone'] : null,
                'birth_year' => isset($validated['birth_year']) ? (int) $validated['birth_year'] : null,
                'custom_data' => null,
                'status' => GuestStatus::PendingEmail,
                'email_confirmation_token' => Str::random(64),
                'consent_terms_at' => now(),
            ]);

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
                    $path = $file->store($dir, 'local');
                    $guest->forceFill(['photo_path' => $path])->save();
                }
            }

            if ($referralLinkId !== null) {
                ReferralLink::query()->whereKey($referralLinkId)->increment('used_count');
            }

            return $guest->fresh();
        });

        $guest->loadMissing('event');

        Mail::to($guest->email)->queue(new GuestInterestConfirmationMail($guest));

        return $guest;
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
                default => null,
            };
        }

        return $rules;
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
