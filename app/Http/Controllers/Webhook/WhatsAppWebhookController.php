<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppWebhookController extends Controller
{
    /**
     * Recebe eventos da Evolution API (ex.: messages.upsert). Por ora só registra o payload para inspeção.
     */
    public function __invoke(Request $request): JsonResponse
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
                    'received_header_names' => array_keys($request->headers->all()),
                    'has_json_apikey' => $request->has('apikey'),
                ]);

                abort(401, 'Unauthorized');
            }
        }

        $correlationId = Str::uuid()->toString();

        $payloadForLog = $request->all();
        if (array_key_exists('apikey', $payloadForLog)) {
            $payloadForLog['apikey'] = '[redacted]';
        }

        Log::info('whatsapp webhook payload', [
            'correlation_id' => $correlationId,
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'query' => $request->query(),
            'payload' => $payloadForLog,
        ]);

        return response()->json([
            'ok' => true,
            'correlation_id' => $correlationId,
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
