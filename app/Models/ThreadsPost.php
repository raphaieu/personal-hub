<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'threads_source_id',
    'external_id',
    'post_url',
    'author_handle',
    'author_name',
    'content',
    'published_at',
    'scraped_at',
    'raw_payload',
])]
class ThreadsPost extends Model
{
    /**
     * @return BelongsTo<ThreadsSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ThreadsSource::class, 'threads_source_id');
    }

    /**
     * @return HasMany<ThreadsComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ThreadsComment::class);
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'scraped_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }
}
