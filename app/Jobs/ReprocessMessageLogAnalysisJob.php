<?php

namespace App\Jobs;

use App\Services\Analysis\ProcessMessageLogAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReprocessMessageLogAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $messageLogId,
    ) {
        $this->onQueue('ai');
    }

    public function handle(ProcessMessageLogAnalysisService $analysisService): void
    {
        $analysisService->process($this->messageLogId);
    }
}
