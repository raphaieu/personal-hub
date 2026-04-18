<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessContactWhatsAppMessage implements ShouldQueue
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
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Log::debug('ProcessContactWhatsAppMessage stub', [
            'message_log_id' => $this->messageLogId,
            'correlation_id' => $this->correlationId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessContactWhatsAppMessage failed', [
            'message_log_id' => $this->messageLogId,
            'correlation_id' => $this->correlationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
