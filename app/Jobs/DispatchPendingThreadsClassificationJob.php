<?php

namespace App\Jobs;

use App\Models\ThreadsComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchPendingThreadsClassificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $batchSize = 1,
        public readonly ?int $spacingSeconds = null,
        public readonly bool $force = false,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $spacing = $this->spacingSeconds ?? (int) env('THREADS_AI_DISPATCH_SPACING_SECONDS', 30);
        $batchSize = max(1, $this->batchSize);

        $query = ThreadsComment::query()
            ->orderBy('id')
            ->limit($batchSize);

        if (! $this->force) {
            $query->whereNull('ai_summary');
        }

        $commentIds = $query->pluck('id')->all();

        foreach ($commentIds as $index => $commentId) {
            $delaySeconds = max(0, $index * max(0, $spacing));

            ClassifyCommentsJob::dispatch((int) $commentId, $this->force)
                ->delay(now()->addSeconds($delaySeconds));
        }

        Log::info('threads.comments.classification_dispatched', [
            'count' => count($commentIds),
            'batch_size' => $batchSize,
            'spacing_seconds' => $spacing,
            'forced' => $this->force,
        ]);
    }
}
