<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Services\Events\EventPaymentStatusService;
use Illuminate\Contracts\View\View;

final class PaymentReturnController extends Controller
{
    public function __construct(
        private readonly EventPaymentStatusService $paymentStatusService,
    ) {}

    public function __invoke(string $token): View
    {
        $initialPayload = $this->paymentStatusService->buildPayload($token);

        return view('events.payment-return', [
            'token' => $token,
            'initialPayload' => $initialPayload,
            'pollIntervalMs' => (int) config('events.payment_poll_interval_seconds', 2) * 1000,
            'reservationMinutes' => (int) config('events.payment_reservation_minutes', 15),
        ]);
    }
}
