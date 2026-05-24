<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Events\MercadoPago\MercadoPagoWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MercadoPagoWebhookController extends Controller
{
    public function __construct(
        private readonly MercadoPagoWebhookService $webhookService,
    ) {}

    public function __invoke(Request $request): Response
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        $queryPaymentId = $request->query('data.id') ?? $request->query('id');

        $this->webhookService->handle(
            $payload,
            is_string($queryPaymentId) ? $queryPaymentId : null,
        );

        return response()->noContent();
    }
}
