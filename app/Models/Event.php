<?php

declare(strict_types=1);

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
    'registration_open',
    'guest_form_schema_json',
    'invite_template_key',
    'closed_message',
    'terms_url',
    'privacy_url',
    'album_id',
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

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'requires_ref' => 'boolean',
            'requires_turnstile' => 'boolean',
            'registration_open' => 'boolean',
            'guest_form_schema_json' => 'array',
        ];
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
}
