<?php

namespace App\Jobs\Events;

use App\Enums\Events\GuestStatus;
use App\Models\Guest;
use App\Services\Events\EventPaymentGuestCleanupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class ExpireUnpaidEventGuestsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(EventPaymentGuestCleanupService $cleanupService): void
    {
        Guest::query()
            ->where('status', GuestStatus::PendingPayment)
            ->where('payment_expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(50, function ($guests) use ($cleanupService): void {
                foreach ($guests as $guest) {
                    try {
                        $cleanupService->expireIfNeeded($guest);
                    } catch (\Throwable $exception) {
                        Log::error('events.payment.expire_failed', [
                            'guest_id' => $guest->id,
                            'message' => $exception->getMessage(),
                        ]);
                        report($exception);
                    }
                }
            });
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('events.payment.expire_job_failed', [
            'message' => $exception?->getMessage(),
        ]);
    }
}
