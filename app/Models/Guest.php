<?php


namespace App\Models;

use App\Enums\Events\GuestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'referral_link_id',
    'name',
    'email',
    'phone',
    'photo_path',
    'birth_year',
    'custom_data',
    'status',
    'consent_terms_at',
    'invite_sent_at',
    'email_confirmation_token',
    'email_confirmed_at',
    'checked_in_at',
])]
class Guest extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<ReferralLink, $this>
     */
    public function referralLink(): BelongsTo
    {
        return $this->belongsTo(ReferralLink::class);
    }

    protected function casts(): array
    {
        return [
            'status' => GuestStatus::class,
            'birth_year' => 'integer',
            'custom_data' => 'array',
            'consent_terms_at' => 'datetime',
            'invite_sent_at' => 'datetime',
            'email_confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }
}
