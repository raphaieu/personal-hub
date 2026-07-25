<?php

namespace App\Models;

use App\Enums\Events\EventStatus;
use App\Enums\Events\GuestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id',
    'slug',
    'status',
    'title',
    'starts_at',
    'ends_at',
    'timezone',
    'capacity',
    'requires_ref',
    'requires_turnstile',
    'requires_photo',
    'registration_open',
    'skip_email_confirmation',
    'guest_form_schema_json',
    'theme_json',
    'content_json',
    'flyer_path',
    'og_image_path',
    'published_at',
    'invite_template_key',
    'closed_message',
    'terms_url',
    'privacy_url',
    'album_id',
    'requires_payment',
    'ticket_amount_cents',
    'mercado_pago_account_id',
])]
class Event extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class, 'album_id');
    }

    /**
     * @return HasMany<Guest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /**
     * @return HasMany<ReferralLink, $this>
     */
    public function referralLinks(): HasMany
    {
        return $this->hasMany(ReferralLink::class);
    }

    /**
     * @return BelongsTo<MercadoPagoAccount, $this>
     */
    public function mercadoPagoAccount(): BelongsTo
    {
        return $this->belongsTo(MercadoPagoAccount::class);
    }

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'requires_ref' => 'boolean',
            'requires_turnstile' => 'boolean',
            'requires_photo' => 'boolean',
            'registration_open' => 'boolean',
            'skip_email_confirmation' => 'boolean',
            'guest_form_schema_json' => 'array',
            'theme_json' => 'array',
            'content_json' => 'array',
            'published_at' => 'datetime',
            'requires_payment' => 'boolean',
            'ticket_amount_cents' => 'integer',
        ];
    }

    public function reservedGuestsCount(): int
    {
        return $this->guests()
            ->whereIn('status', [
                GuestStatus::Confirmed,
                GuestStatus::PendingPayment,
            ])
            ->count();
    }

    public function occupancyCount(): int
    {
        return $this->requires_payment
            ? $this->reservedGuestsCount()
            : $this->confirmedGuestsCount();
    }

    public function isEnded(): bool
    {
        if ($this->status === EventStatus::Ended || $this->status === EventStatus::Archived) {
            return true;
        }

        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function confirmedGuestsCount(): int
    {
        return $this->guests()
            ->where('status', GuestStatus::Confirmed)
            ->count();
    }

    public function requiresEmailConfirmation(): bool
    {
        return ! $this->requires_payment && ! $this->skip_email_confirmation;
    }
}
