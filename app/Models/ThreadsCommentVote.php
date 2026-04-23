<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'threads_comment_id',
    'session_fingerprint',
    'vote',
])]
class ThreadsCommentVote extends Model
{
    /**
     * @return BelongsTo<ThreadsComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(ThreadsComment::class, 'threads_comment_id');
    }

    protected function casts(): array
    {
        return [
            'vote' => 'integer',
        ];
    }
}
