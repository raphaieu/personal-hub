<?php

namespace App\Models;

use App\Enums\Events\GuestPaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'guest_id',
    'mercado_pago_account_id',
    'preference_id',
    'payment_id',
    'status',
    'amount_cents',
    'currency',
    'mp_last_payload',
    'paid_at',
])]
class GuestPayment extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
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
            'status' => GuestPaymentStatus::class,
            'amount_cents' => 'integer',
            'mp_last_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }
}
