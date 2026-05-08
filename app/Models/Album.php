<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'parent_id',
    'slug',
    'title',
    'description',
    'cover_media_id',
    'access_type',
    'password_hash',
    'token',
    'token_expires_at',
    'one_time_used_at',
    'download_enabled',
    'sort_order',
    'is_locked',
    'thumb_width',
    'thumb_height',
    'thumb_quality',
    'contribution_invite_token',
    'contribution_upload_ttl_hours',
])]
class Album extends Model
{
    use HasUuids;
    use SoftDeletes;

    /**
     * @return BelongsTo<Album, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Album, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<AlbumMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(AlbumMedia::class);
    }

    /**
     * @return HasMany<AccessAttempt, $this>
     */
    public function accessAttempts(): HasMany
    {
        return $this->hasMany(AccessAttempt::class);
    }

    /**
     * @return HasMany<AlbumLockout, $this>
     */
    public function lockouts(): HasMany
    {
        return $this->hasMany(AlbumLockout::class);
    }

    /**
     * @return HasMany<Contributor, $this>
     */
    public function contributors(): HasMany
    {
        return $this->hasMany(Contributor::class);
    }

    /**
     * @return BelongsTo<AlbumMedia, $this>
     */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(AlbumMedia::class, 'cover_media_id');
    }

    protected function casts(): array
    {
        return [
            'download_enabled' => 'boolean',
            'is_locked' => 'boolean',
            'thumb_width' => 'integer',
            'thumb_height' => 'integer',
            'thumb_quality' => 'integer',
            'contribution_upload_ttl_hours' => 'integer',
            'token_expires_at' => 'datetime',
            'one_time_used_at' => 'datetime',
        ];
    }
}
