<?php

namespace App\Services\Evolution;

/**
 * Unwraps Evolution webhook JSON variants so downstream parsers see a stable shape.
 */
final class EvolutionWebhookPayloadNormalizer
{
    /**
     * Evolution envia nomes variados (ex.: MESSAGES_UPSERT, messages-upsert, SEND_MESSAGE).
     * Normaliza para um valor canônico interno.
     */
    public static function normalizeEventName(?string $raw): ?string
    {
        if ($raw === null || ! is_string($raw)) {
            return null;
        }

        $e = strtolower(trim($raw));

        return match ($e) {
            'messages_upsert',
            'messages-upsert',
            'messages.upsert' => 'messages.upsert',

            'send_message',
            'send-message',
            'send.message' => 'send.message',

            default => $e,
        };
    }

    /**
     * Eventos cujo campo `data` segue o mesmo formato Baileys de mensagem(ns) para o extrator.
     *
     * @see https://doc.evolution-api.com/v2/en/configuration/webhooks (MESSAGES_UPSERT, SEND_MESSAGE)
     */
    public static function isMessagePayloadEvent(?string $normalizedCanonical): bool
    {
        return in_array($normalizedCanonical, ['messages.upsert', 'send.message'], true);
    }

    /**
     * Returns the inner `data` object/array for the Evolution webhook body.
     * Some builds nest the Baileys payload under `data.data`.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    public static function unwrapDataPayload(array $body): ?array
    {
        $payload = $body['data'] ?? null;
        if (! is_array($payload)) {
            return null;
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            $inner = $payload['data'];
            if (isset($inner['key']) || isset($inner['messages']) || isset($inner['message'])) {
                return $inner;
            }
        }

        return $payload;
    }
}
