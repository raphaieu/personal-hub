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

    /**
     * @param  list<int>  $commentIds
     */
    public function __construct(
        public readonly array $commentIds,
    ) {
        $this->onQueue('ai');
    }

    public function handle(ThreadsClassificationService $classificationService): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, ThreadsComment> $comments */
        $comments = ThreadsComment::query()
            ->whereIn('id', $this->commentIds)
            ->get();

        foreach ($comments as $comment) {
            $classificationService->classifyComment($comment);
        }

        Log::info('threads.comments.classified', [
            'count' => $comments->count(),
        ]);
    }
}
