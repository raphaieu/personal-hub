<?php

namespace App\Services\Evolution;

use Illuminate\Support\Facades\Config;

/**
 * Parses Baileys / Evolution `messages.upsert` payloads into flat message records.
 * Does not embed base64 blobs — only safe metadata flags for logging and jobs.
 */
final class EvolutionMessagesUpsertExtractor
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function extract(array $payload): array
    {
        if (! empty($payload['from']) && ! empty($payload['body']) && is_string($payload['body'])) {
            return $this->legacySingleBodyFormat($payload);
        }

        // Evolution API v2 → webhook `data` costuma vir como um único objeto (key + message + messageType …),
        // não como `{ messages: [ … ] }`. Esse formato plano usa o ramo elseif abaixo.
        $messages = [];
        if (isset($payload['messages']) && is_array($payload['messages'])) {
            $messages = $payload['messages'];
        } elseif (isset($payload['message']) || isset($payload['key'])) {
            $messages = [$payload];
        }

        $items = [];

        foreach ($messages as $msg) {
            if (! is_array($msg)) {
                continue;
            }

            $remoteJid = $msg['key']['remoteJid'] ?? null;
            if (! is_string($remoteJid) || $remoteJid === '') {
                continue;
            }

            if (str_contains($remoteJid, 'status@broadcast')) {
                continue;
            }

            if (! isset($msg['message']) || ! is_array($msg['message'])) {
                continue;
            }

            $fromMe = ($msg['key']['fromMe'] ?? false) === true;

            $participantJid = null;
            $senderJid = null;
            if (str_contains($remoteJid, '@g.us')) {
                $participantJid = $msg['key']['participantAlt'] ?? $msg['key']['participant'] ?? null;
                $senderJid = is_string($participantJid) ? $participantJid : null;
            } else {
                $senderJid = $remoteJid;
            }

            $messageId = $msg['key']['id'] ?? null;
            $messageId = is_string($messageId) ? $messageId : null;

            $parsed = $this->parseMessageContent($msg);
            if ($parsed === null) {
                continue;
            }

            $direction = $fromMe ? 'outbound' : 'inbound';

            $items[] = [
                'chat_jid' => $remoteJid,
                'sender_jid' => is_string($senderJid) ? $senderJid : null,
                'participant_jid' => is_string($participantJid) ? $participantJid : null,
                'from_me' => $fromMe,
                'evolution_message_id' => $messageId,
                'message_type' => $parsed['message_type'],
                'body' => $parsed['body'],
                'mentions' => $parsed['mentions'],
                'quoted_evolution_message_id' => $parsed['quoted_evolution_message_id'],
                'direction' => $direction,
                'metadata' => $parsed['metadata'],
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function legacySingleBodyFormat(array $payload): array
    {
        $phone = preg_replace('/\D/', '', (string) $payload['from']);
        if ($phone === '') {
            return [];
        }

        $body = (string) $payload['body'];

        return [[
            'chat_jid' => $phone.'@s.whatsapp.net',
            'sender_jid' => $phone.'@s.whatsapp.net',
            'participant_jid' => null,
            'from_me' => false,
            'evolution_message_id' => null,
            'message_type' => (string) ($payload['type'] ?? 'text'),
            'body' => $body,
            'mentions' => [],
            'quoted_evolution_message_id' => null,
            'direction' => 'inbound',
            'metadata' => ['legacy_format' => true],
        ]];
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array{
     *     message_type: string,
     *     body: ?string,
     *     mentions: array<int, string>,
     *     quoted_evolution_message_id: ?string,
     *     metadata: array<string, mixed>
     * }|null
     */
    private function parseMessageContent(array $msg): ?array
    {
        $m = $msg['message'];
        if (! is_array($m)) {
            return null;
        }

        $m = $this->unwrapBaileysWrappers($m);

        $mentions = [];
        $quotedId = null;
        $metadata = [
            'has_base64' => false,
            'has_image_url' => false,
            'has_media_url' => false,
            'media_url_source' => null,
        ];

        $extended = $m['extendedTextMessage'] ?? null;
        if (is_array($extended)) {
            $ctx = $extended['contextInfo'] ?? null;
            if (is_array($ctx)) {
                if (isset($ctx['mentionedJid']) && is_array($ctx['mentionedJid'])) {
                    foreach ($ctx['mentionedJid'] as $jid) {
                        if (is_string($jid) && $jid !== '') {
                            $mentions[] = $jid;
                        }
                    }
                }
                $stanza = $ctx['stanzaId'] ?? null;
                if (is_string($stanza) && $stanza !== '') {
                    $quotedId = $stanza;
                }
            }
        }

        $baseUrl = rtrim((string) Config::get('services.evolution.url', ''), '/');

        if (isset($m['mediaUrl']) && is_string($m['mediaUrl'])) {
            $metadata['has_media_url'] = true;
            $metadata['media_url'] = $m['mediaUrl'];
            $metadata['media_url_source'] = 'message.mediaUrl';
        } elseif (isset($msg['mediaUrl']) && is_string($msg['mediaUrl'])) {
            $metadata['has_media_url'] = true;
            $metadata['media_url'] = $msg['mediaUrl'];
            $metadata['media_url_source'] = 'item.mediaUrl';
        }

        if (isset($m['base64']) && is_string($m['base64']) && $m['base64'] !== '') {
            $metadata['has_base64'] = true;
        } elseif (isset($msg['base64']) && is_string($msg['base64']) && $msg['base64'] !== '') {
            $metadata['has_base64'] = true;
        }

        $conversation = $m['conversation'] ?? null;
        if (is_string($conversation) && trim($conversation) !== '') {
            return [
                'message_type' => 'text',
                'body' => $conversation,
                'mentions' => $mentions,
                'quoted_evolution_message_id' => $quotedId,
                'metadata' => $metadata,
            ];
        }

        $extendedTextBlock = $m['extendedTextMessage'] ?? null;
        $extText = is_array($extendedTextBlock) && isset($extendedTextBlock['text']) && is_string($extendedTextBlock['text'])
            ? $extendedTextBlock['text']
            : null;

        if (is_string($extText) && trim($extText) !== '') {
            return [
                'message_type' => 'text',
                'body' => $extText,
                'mentions' => $mentions,
                'quoted_evolution_message_id' => $quotedId,
                'metadata' => $metadata,
            ];
        }

        if (isset($m['reactionMessage']) && is_array($m['reactionMessage'])) {
            $rx = $m['reactionMessage'];
            $emoji = $rx['text'] ?? null;
            $targetId = $rx['key']['id'] ?? null;

            return [
                'message_type' => 'reaction',
                'body' => is_string($emoji) ? $emoji : null,
                'mentions' => $mentions,
                'quoted_evolution_message_id' => is_string($targetId) ? $targetId : $quotedId,
                'metadata' => array_merge($metadata, [
                    'reaction_target_remote_jid' => $rx['key']['remoteJid'] ?? null,
                    'reaction_from_me' => $rx['key']['fromMe'] ?? null,
                ]),
            ];
        }

        if (isset($m['imageMessage']) && is_array($m['imageMessage'])) {
            $im = $m['imageMessage'];
            $caption = $im['caption'] ?? null;
            $caption = is_string($caption) ? $caption : null;

            if (isset($im['url']) && is_string($im['url'])) {
                $metadata['has_image_url'] = true;
                $url = $im['url'];
                if ($url !== '' && str_starts_with($url, '/') && $baseUrl !== '') {
                    $url = $baseUrl.$url;
                }
                $metadata['image_url'] = $url;
            }

            if (isset($im['mediaUrl']) && is_string($im['mediaUrl'])) {
                $metadata['has_media_url'] = true;
                $metadata['media_url'] = $im['mediaUrl'];
                $metadata['media_url_source'] = 'imageMessage.mediaUrl';
            }

            return [
                'message_type' => 'image',
                'body' => $caption !== null && trim($caption) !== '' ? $caption : null,
                'mentions' => $mentions,
                'quoted_evolution_message_id' => $quotedId,
                'metadata' => $metadata,
            ];
        }

        if (isset($m['videoMessage']) && is_array($m['videoMessage'])) {
            $vm = $m['videoMessage'];
            $caption = $vm['caption'] ?? null;

            return [
                'message_type' => 'video',
                'body' => is_string($caption) && trim($caption) !== '' ? $caption : null,
                'mentions' => $mentions,
                'quoted_evolution_message_id' => $quotedId,
                'metadata' => $metadata,
            ];
        }

        if (isset($m['audioMessage']) && is_array($m['audioMessage'])) {
            return [
                'message_type' => 'audio',
                'body' => null,
                'mentions' => $mentions,
                'quoted_evolution_message_id' => $quotedId,
                'metadata' => $metadata,
            ];
        }

        if (isset($m['documentMessage']) && is_array($m['documentMessage'])) {
            $dm = $m['documentMessage'];
            $caption = $dm['caption'] ?? null;
            $fileName = $dm['fileName'] ?? null;
            $meta = $metadata;
            if (is_string($fileName) && $fileName !== '') {
                $meta['file_name'] = $fileName;
            }

            return [
                'message_type' => 'document',
                'body' => is_string($caption) && trim($caption) !== '' ? $caption : null,
                'mentions' => $mentions,
                'quoted_evolution_message_id' => $quotedId,
                'metadata' => $meta,
            ];
        }

        if (isset($m['stickerMessage'])) {
            return [
                'message_type' => 'sticker',
                'body' => null,
                'mentions' => $mentions,
                'quoted_evolution_message_id' => $quotedId,
                'metadata' => $metadata,
            ];
        }

        // Só texto estendido (ex.: reply sem corpo legível) — depois de mídia para não mascarar image+audio
        if (is_array($extendedTextBlock)) {
            return [
                'message_type' => 'text',
                'body' => (is_string($extText) && trim($extText) !== '') ? $extText : null,
                'mentions' => $mentions,
                'quoted_evolution_message_id' => $quotedId,
                'metadata' => array_merge($metadata, ['extended_text_empty' => true]),
            ];
        }

        return [
            'message_type' => 'unknown',
            'body' => null,
            'mentions' => $mentions,
            'quoted_evolution_message_id' => $quotedId,
            'metadata' => array_merge($metadata, ['raw_message_keys' => array_keys($m)]),
        ];
    }

    /**
     * Baileys envolve mídia/texto em ephemeral / viewOnce / caption wrappers — precisamos do inner message.
     *
     * @param  array<string, mixed>  $m
     * @return array<string, mixed>
     */
    private function unwrapBaileysWrappers(array $m): array
    {
        for ($i = 0; $i < 16; $i++) {
            $next = null;

            if (isset($m['ephemeralMessage']['message']) && is_array($m['ephemeralMessage']['message'])) {
                $next = $m['ephemeralMessage']['message'];
            } elseif (isset($m['viewOnceMessage']['message']) && is_array($m['viewOnceMessage']['message'])) {
                $next = $m['viewOnceMessage']['message'];
            } elseif (isset($m['viewOnceMessageV2']['message']) && is_array($m['viewOnceMessageV2']['message'])) {
                $next = $m['viewOnceMessageV2']['message'];
            } elseif (isset($m['documentWithCaptionMessage']['message']) && is_array($m['documentWithCaptionMessage']['message'])) {
                $next = $m['documentWithCaptionMessage']['message'];
            } elseif (isset($m['editedMessage']['message']) && is_array($m['editedMessage']['message'])) {
                $next = $m['editedMessage']['message'];
            } elseif (isset($m['albumMessage']['message']) && is_array($m['albumMessage']['message'])) {
                $next = $m['albumMessage']['message'];
            }

            if ($next === null) {
                break;
            }

            $m = $next;
        }

        return $m;
    }
}
