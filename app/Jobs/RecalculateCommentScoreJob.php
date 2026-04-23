<?php

namespace App\Jobs;

use App\Models\ThreadsComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecalculateCommentScoreJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $commentId,
    ) {}

    public function handle(): void
    {
        $comment = ThreadsComment::query()->find($this->commentId);
        if ($comment === null) {
            return;
        }

        $upvotes = (int) $comment->votes()->where('vote', 1)->count();
        $downvotes = (int) $comment->votes()->where('vote', -1)->count();

        $comment->forceFill([
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'score_total' => $upvotes - $downvotes,
        ])->save();
    }
}
