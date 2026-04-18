<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Webhook\StoreWhatsAppWebhookRequest;
use App\Services\WebhookRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    /**
     * Recebe eventos da Evolution API (ex.: messages.upsert, SEND_MESSAGE), valida credencial e roteia para jobs.
     */
    public function __invoke(StoreWhatsAppWebhookRequest $request, WebhookRouterService $router): JsonResponse
    {
        $secret = config('services.evolution.webhook_secret');

        if (filled($secret)) {
            $expected = trim((string) $secret);
            $provided = $this->extractProvidedCredential($request);

            if ($provided === null || ! hash_equals($expected, $provided)) {
                Log::warning('whatsapp webhook: credencial inválida ou ausente', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'reason' => $provided === null ? 'absent' : 'mismatch',
                ]);

                abort(401, 'Unauthorized');
            }
        }

        $correlationId = Str::uuid()->toString();
        $routing = $router->route($request, $correlationId);

        if (config('app.debug')) {
            Log::debug('whatsapp webhook', [
                'correlation_id' => $correlationId,
                'instance' => $request->input('instance'),
                'routing' => $routing,
            ]);
        }

        return response()->json([
            'ok' => true,
            'correlation_id' => $correlationId,
            'routing' => $routing,
        ]);
    }

    /**
     * Headers primeiro (proxy / webhook com headers na instância); depois JSON — a Evolution envia `apikey` no body
     * (WebhookController.emit, campo apikey em webhookData).
     */
    private function extractProvidedCredential(Request $request): ?string
    {
        $apikey = $request->header('apikey');
        if (is_string($apikey) && $apikey !== '') {
            return trim($apikey);
        }

        $bearer = $request->bearerToken();
        if (is_string($bearer) && $bearer !== '') {
            return trim($bearer);
        }

        $xApiKey = $request->header('x-api-key');
        if (is_string($xApiKey) && $xApiKey !== '') {
            return trim($xApiKey);
        }

        $bodyApikey = $request->input('apikey');
        if (is_string($bodyApikey) && $bodyApikey !== '') {
            return trim($bodyApikey);
        }

        return null;
    }
}
