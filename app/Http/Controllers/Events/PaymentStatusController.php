<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Services\Events\EventPaymentStatusService;
use Illuminate\Http\JsonResponse;

final class PaymentStatusController extends Controller
{
    public function __construct(
        private readonly EventPaymentStatusService $paymentStatusService,
    ) {}

    public function __invoke(string $token): JsonResponse
    {
        return response()->json($this->paymentStatusService->buildPayload($token));
    }
}
