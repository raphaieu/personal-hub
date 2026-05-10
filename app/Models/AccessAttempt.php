<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'album_id',
    'ip',
    'user_agent',
    'attempted_at',
    'succeeded',
])]
class AccessAttempt extends Model
{
    /**
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'succeeded' => 'boolean',
        ];
    }
}
