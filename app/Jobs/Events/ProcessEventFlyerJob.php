<?php

namespace App\Jobs\Events;

use App\Models\Event;
use App\Services\Events\EventFlyerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessEventFlyerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $eventId,
    ) {
        $this->onQueue('ai');
    }

    public function handle(EventFlyerService $flyerService): void
    {
        $event = Event::query()->find($this->eventId);

        if ($event === null || $event->flyer_path === null) {
            return;
        }

        $flyerService->process($event);
    }

    /**
     * Rede de segurança: o service nunca lança, mas se o job morrer de outra
     * forma (timeout, OOM), o organizador não fica preso no "processando".
     */
    public function failed(Throwable $exception): void
    {
        Log::error('events.flyer_job_failed', [
            'event_id' => $this->eventId,
            'error' => $exception->getMessage(),
        ]);

        Event::query()->whereKey($this->eventId)->update(['ai_status' => 'failed']);
    }
}
