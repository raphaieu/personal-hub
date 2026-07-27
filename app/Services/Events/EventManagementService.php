<?php

namespace App\Services\Events;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EventManagementService
{
    public function __construct(
        private readonly ThemeService $themeService,
    ) {}

    /**
     * Cria evento em rascunho para o organizador (self-service).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $owner, array $data): Event
    {
        return Event::query()->create([
            'owner_id' => $owner->id,
            'slug' => $this->resolveSlug($data['slug'] ?? null, (string) $data['title']),
            'status' => EventStatus::Draft,
            'title' => $data['title'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'timezone' => $data['timezone'] ?? 'America/Sao_Paulo',
            'capacity' => $data['capacity'] ?? null,
            'registration_open' => false,
        ]);
    }

    /**
     * Atualização parcial — inclui tema (com sanitização de CSS) e conteúdo das seções.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(Event $event, array $data): Event
    {
        if (array_key_exists('slug', $data) && $data['slug'] !== $event->slug) {
            // Slug congela na publicação para não quebrar links já divulgados.
            if ($event->status !== EventStatus::Draft) {
                throw ValidationException::withMessages([
                    'slug' => ['O endereço não pode ser alterado após a publicação.'],
                ]);
            }
            $data['slug'] = $this->resolveSlug($data['slug'], $event->title, $event);
        }

        if (array_key_exists('theme', $data)) {
            $theme = $data['theme'];
            if (is_array($theme) && array_key_exists('customCss', $theme)) {
                $theme['customCss'] = $this->themeService->sanitizeCustomCss($theme['customCss']);
            }
            $event->theme_json = $theme;
            unset($data['theme']);
        }

        if (array_key_exists('content', $data)) {
            $event->content_json = $data['content'];
            unset($data['content']);
        }

        $event->fill($data)->save();

        return $event->refresh();
    }

    /**
     * Publica o evento — exige e-mail verificado, data definida e respeita o
     * limite de publicados simultâneos da conta gratuita.
     *
     * @throws ValidationException
     */
    public function publish(User $owner, Event $event): Event
    {
        if (! $owner->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Verifique seu e-mail antes de publicar um evento.'],
            ]);
        }

        if ($event->status === EventStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => ['Eventos arquivados não podem ser republicados.'],
            ]);
        }

        if ($event->starts_at === null) {
            throw ValidationException::withMessages([
                'starts_at' => ['Defina a data do evento antes de publicar.'],
            ]);
        }

        $limit = (int) config('events.limits.max_published_events', 3);
        $publishedCount = Event::query()
            ->where('owner_id', $owner->id)
            ->where('status', EventStatus::Published)
            ->whereKeyNot($event->id)
            ->count();

        if ($publishedCount >= $limit) {
            throw ValidationException::withMessages([
                'limit' => ["Limite de {$limit} eventos publicados simultâneos atingido na conta gratuita."],
            ]);
        }

        $event->forceFill([
            'status' => EventStatus::Published,
            'published_at' => $event->published_at ?? now(),
            'registration_open' => true,
        ])->save();

        return $event->refresh();
    }

    public function archive(Event $event): Event
    {
        $event->forceFill([
            'status' => EventStatus::Archived,
            'registration_open' => false,
        ])->save();

        return $event->refresh();
    }

    /**
     * Limites da conta gratuita e uso atual do organizador.
     *
     * @return array<string, mixed>
     */
    public function limitsPayload(User $user): array
    {
        return [
            'limits' => config('events.limits', []),
            'usage' => [
                'totalEvents' => Event::query()->where('owner_id', $user->id)->count(),
                'publishedEvents' => Event::query()
                    ->where('owner_id', $user->id)
                    ->where('status', EventStatus::Published)
                    ->count(),
            ],
        ];
    }

    /**
     * Payload completo do evento para a API autenticada.
     *
     * @return array<string, mixed>
     */
    public function payload(Event $event): array
    {
        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'status' => $event->status->value,
            'title' => $event->title,
            'startsAt' => $event->starts_at?->toIso8601String(),
            'endsAt' => $event->ends_at?->toIso8601String(),
            'timezone' => $event->timezone,
            'capacity' => $event->capacity,
            'registrationOpen' => (bool) $event->registration_open,
            'requiresRef' => (bool) $event->requires_ref,
            'requiresTurnstile' => (bool) $event->requires_turnstile,
            'requiresPhoto' => (bool) $event->requires_photo,
            'requiresPayment' => (bool) $event->requires_payment,
            'skipEmailConfirmation' => (bool) $event->skip_email_confirmation,
            'ticketAmountCents' => $event->ticket_amount_cents,
            'closedMessage' => $event->closed_message,
            'termsUrl' => $event->terms_url,
            'privacyUrl' => $event->privacy_url,
            'theme' => $event->theme_json,
            'content' => $event->content_json,
            'flyerPath' => $event->flyer_path,
            'ogImagePath' => $event->og_image_path,
            'ogImageUrl' => $this->ogImageUrl($event),
            'aiStatus' => $event->ai_status,
            'publicUrl' => rtrim((string) config('events.frontend_url'), '/').'/e/'.$event->slug,
            'counts' => [
                'guests' => $event->guests_count ?? $event->guests()->count(),
                'confirmed' => $event->confirmed_guests_count ?? $event->confirmedGuestsCount(),
                'checkedIn' => $event->guests()->whereNotNull('checked_in_at')->count(),
            ],
            'publishedAt' => $event->published_at?->toIso8601String(),
            'createdAt' => $event->created_at?->toIso8601String(),
            'updatedAt' => $event->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Slug único a partir do título (usado também pela extração de flyer via IA).
     */
    public function availableSlug(string $title, ?Event $ignore = null): string
    {
        return $this->resolveSlug(null, $title, $ignore);
    }

    /**
     * Resolve slug único a partir do informado (ou do título), rejeitando reservados.
     *
     * @throws ValidationException
     */
    private function resolveSlug(?string $slug, string $title, ?Event $ignore = null): string
    {
        $base = Str::slug($slug !== null && $slug !== '' ? $slug : $title);
        if ($base === '') {
            $base = 'evento';
        }

        if (in_array($base, config('events.reserved_slugs', []), true)) {
            throw ValidationException::withMessages([
                'slug' => ['Este endereço é reservado pela plataforma.'],
            ]);
        }

        $candidate = $base;
        $suffix = 2;
        while ($this->slugExists($candidate, $ignore)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugExists(string $slug, ?Event $ignore): bool
    {
        return Event::query()
            ->where('slug', $slug)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();
    }

    /**
     * Query base da listagem do dono, já com contadores para evitar N+1.
     *
     * @return Builder<Event>
     */
    public function ownedEventsQuery(User $user): Builder
    {
        return Event::query()
            ->where('owner_id', $user->id)
            ->withCount([
                'guests',
                'guests as confirmed_guests_count' => fn ($query) => $query->where('status', GuestStatus::Confirmed),
            ])
            ->latest();
    }

    private function ogImageUrl(Event $event): ?string
    {
        if ($event->og_image_path === null) {
            return null;
        }

        try {
            return Storage::disk('s3')->url($event->og_image_path);
        } catch (\Throwable) {
            return null;
        }
    }
}
