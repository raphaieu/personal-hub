<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'utility_account_id',
    'billing_reference',
    'due_date',
    'amount_total',
    'amount_water',
    'amount_sewage',
    'amount_service',
    'water_consumption_m3',
    'status',
    'payment_date',
    'pdf_path',
    'raw_payload',
    'scraped_at',
    'last_notified_at',
])]
class Invoice extends Model
{
    /**
     * @return BelongsTo<UtilityAccount, $this>
     */
    public function utilityAccount(): BelongsTo
    {
        return $this->belongsTo(UtilityAccount::class);
    }

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_total' => 'decimal:2',
            'amount_water' => 'decimal:2',
            'amount_sewage' => 'decimal:2',
            'amount_service' => 'decimal:2',
            'water_consumption_m3' => 'integer',
            'payment_date' => 'date',
            'raw_payload' => 'array',
            'scraped_at' => 'datetime',
            'last_notified_at' => 'datetime',
        ];
    }
}
