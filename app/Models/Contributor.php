<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'album_id',
    'email',
    'email_verified',
    'verify_token',
    'verify_expires_at',
    'upload_token',
    'upload_expires_at',
])]
class Contributor extends Model
{
    use HasUuids;

    protected $table = 'contributors';

    /**
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * @return HasMany<AlbumMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(AlbumMedia::class, 'contributor_id');
    }

    protected function casts(): array
    {
        return [
            'email_verified' => 'boolean',
            'verify_expires_at' => 'datetime',
            'upload_expires_at' => 'datetime',
        ];
    }
}
