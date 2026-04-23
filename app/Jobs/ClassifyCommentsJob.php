<?php

namespace App\Jobs;

use App\Models\ThreadsComment;
use App\Services\Threads\ThreadsClassificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ClassifyCommentsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $commentId,
        public readonly bool $force = false,
    ) {
        $this->onQueue('ai');
    }

    public function handle(ThreadsClassificationService $classificationService): void
    {
        $comment = ThreadsComment::query()->find($this->commentId);
        if (! $comment) {
            return;
        }

        if (! $this->force && $comment->ai_summary !== null) {
            return;
        }

        $classificationService->classifyComment($comment);

        Log::info('threads.comments.classified', [
            'comment_id' => $comment->id,
            'forced' => $this->force,
        ]);
    }
}
