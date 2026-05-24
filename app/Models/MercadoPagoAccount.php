<?php

namespace App\Models;

use App\Enums\Events\MercadoPagoEnvironment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'owner_id',
    'label',
    'public_key',
    'access_token',
    'environment',
])]
class MercadoPagoAccount extends Model
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
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'environment' => MercadoPagoEnvironment::class,
        ];
    }
}
