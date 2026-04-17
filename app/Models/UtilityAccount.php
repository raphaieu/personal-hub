<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kind',
    'account_ref',
    'label',
    'due_day',
    'reminder_lead_days',
    'is_active',
    'last_scraped_at',
])]
class UtilityAccount extends Model
{
    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    protected function casts(): array
    {
        return [
            'due_day' => 'integer',
            'reminder_lead_days' => 'integer',
            'is_active' => 'boolean',
            'last_scraped_at' => 'datetime',
        ];
    }
}
