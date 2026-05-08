<?php

namespace App\Jobs;

use App\Services\Analysis\ProcessMessageLogAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPersonalWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $messageLogId,
        public readonly string $correlationId,
    ) {
        $this->onQueue('ai');
    }

    public function handle(ProcessMessageLogAnalysisService $analysisService): void
    {
        $analysisService->process($this->messageLogId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessPersonalWhatsAppMessage failed', [
            'message_log_id' => $this->messageLogId,
            'correlation_id' => $this->correlationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
