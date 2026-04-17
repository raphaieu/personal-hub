<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kind',
    'identifier',
    'label',
    'permissions',
    'is_active',
    'notes',
    'media_storage_prefix',
])]
class MonitoredSource extends Model
{
    /**
     * @return HasMany<MessageLog, $this>
     */
    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    /**
     * Usuários com papel explícito nesta fonte (ex.: admin de grupo — V2).
     *
     * @return BelongsToMany<User, $this>
     */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'monitored_source_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
