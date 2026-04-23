<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'threads_post_id',
    'threads_category_id',
    'external_id',
    'parent_external_id',
    'author_handle',
    'author_name',
    'content',
    'commented_at',
    'scraped_at',
    'status',
    'is_public',
    'is_featured',
    'ai_relevance_score',
    'ai_summary',
    'ai_meta',
    'upvotes',
    'downvotes',
    'score_total',
    'raw_payload',
])]
class ThreadsComment extends Model
{
    /**
     * @return BelongsTo<ThreadsPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(ThreadsPost::class, 'threads_post_id');
    }

    /**
     * @return BelongsTo<ThreadsCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ThreadsCategory::class, 'threads_category_id');
    }

    /**
     * @return HasMany<ThreadsCommentVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(ThreadsCommentVote::class);
    }

    protected function casts(): array
    {
        return [
            'commented_at' => 'datetime',
            'scraped_at' => 'datetime',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'ai_relevance_score' => 'decimal:2',
            'ai_meta' => 'array',
            'upvotes' => 'integer',
            'downvotes' => 'integer',
            'score_total' => 'integer',
            'raw_payload' => 'array',
        ];
    }
}
