<?php

namespace App\Services;

use App\Data\WebhookRouteResolution;
use App\Enums\WhatsAppInboundRoute;
use App\Jobs\ProcessContactWhatsAppMessage;
use App\Jobs\ProcessGroupWhatsAppMessage;
use App\Jobs\ProcessPersonalWhatsAppMessage;
use App\Models\MessageLog;
use App\Models\MonitoredSource;
use App\Services\Evolution\EvolutionMessagesUpsertExtractor;
use App\Services\Evolution\EvolutionWebhookPayloadNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class WebhookRouterService
{
    public function __construct(
        private readonly EvolutionMessagesUpsertExtractor $extractor,
    ) {}

    /**
     * @return array{
     *     event: ?string,
     *     parsed_messages: int,
     *     persisted_logs: int,
     *     jobs_dispatched: int,
     *     skipped_duplicate_ids: int,
     *     skipped_event: bool
     * }
     */
    public function route(Request $request, string $correlationId): array
    {
        $body = $request->all();
        $eventRaw = $body['event'] ?? null;
        $event = EvolutionWebhookPayloadNormalizer::normalizeEventName(is_string($eventRaw) ? $eventRaw : null);

        if (! EvolutionWebhookPayloadNormalizer::isMessagePayloadEvent($event)) {
            Log::debug('whatsapp webhook: evento ignorado pelo roteador', [
                'correlation_id' => $correlationId,
                'event' => $event,
                'event_raw' => is_string($eventRaw) ? $eventRaw : null,
            ]);

            return [
                'event' => $event,
                'parsed_messages' => 0,
                'persisted_logs' => 0,
                'jobs_dispatched' => 0,
                'skipped_duplicate_ids' => 0,
                'skipped_event' => true,
            ];
        }

        $dataPayload = EvolutionWebhookPayloadNormalizer::unwrapDataPayload($body) ?? [];

        $parsed = $this->extractor->extract($dataPayload);

        $persisted = 0;
        $jobs = 0;
        $skippedDup = 0;

        foreach ($parsed as $item) {
            $evolutionMessageId = $item['evolution_message_id'] ?? null;
            if (is_string($evolutionMessageId) && $evolutionMessageId !== '') {
                $exists = MessageLog::query()->where('evolution_message_id', $evolutionMessageId)->exists();
                if ($exists) {
                    $skippedDup++;

                    continue;
                }
            }

            $resolution = $this->resolveRoute($item);

            $metadata = array_merge(
                is_array($item['metadata'] ?? null) ? $item['metadata'] : [],
                [
                    'correlation_id' => $correlationId,
                    'routing' => $resolution->route->value,
                    'instance' => $body['instance'] ?? null,
                ]
            );

            if ($resolution->route === WhatsAppInboundRoute::Ignored) {
                $metadata['ignored_reason'] = 'no_matching_rule';
            }

            $log = MessageLog::query()->create([
                'monitored_source_id' => $resolution->monitoredSourceId,
                'chat_jid' => $item['chat_jid'],
                'sender_jid' => $item['sender_jid'] ?? null,
                'direction' => $item['direction'],
                'message_type' => $item['message_type'],
                'body' => $item['body'] ?? null,
                'mentions' => $item['mentions'] ?? [],
                'quoted_evolution_message_id' => $item['quoted_evolution_message_id'] ?? null,
                'metadata' => $metadata,
                'is_processed' => $resolution->route === WhatsAppInboundRoute::Ignored,
                'evolution_message_id' => is_string($evolutionMessageId) ? $evolutionMessageId : null,
            ]);

            $persisted++;

            Log::debug('whatsapp message persisted', [
                'correlation_id' => $correlationId,
                'message_log_id' => $log->id,
                'routing' => $resolution->route->value,
                'chat_jid' => $item['chat_jid'],
                'direction' => $item['direction'],
                'message_type' => $item['message_type'],
                'evolution_message_id' => $evolutionMessageId,
                'from_me' => $item['from_me'] ?? null,
            ]);

            if ($this->dispatchForRoute($resolution, $log->id, $correlationId)) {
                $jobs++;
            }
        }

        return [
            'event' => $event,
            'parsed_messages' => count($parsed),
            'persisted_logs' => $persisted,
            'jobs_dispatched' => $jobs,
            'skipped_duplicate_ids' => $skippedDup,
            'skipped_event' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveRoute(array $item): WebhookRouteResolution
    {
        $chatJid = $item['chat_jid'];
        if (! is_string($chatJid)) {
            return new WebhookRouteResolution(WhatsAppInboundRoute::Ignored);
        }

        $fromMe = ($item['from_me'] ?? false) === true;

        if ($fromMe && str_ends_with($chatJid, '@s.whatsapp.net')) {
            $selfSource = MonitoredSource::query()
                ->where('identifier', $chatJid)
                ->where('kind', 'self')
                ->where('is_active', true)
                ->first();

            return new WebhookRouteResolution(
                WhatsAppInboundRoute::Personal,
                $selfSource?->id,
            );
        }

        $notesSoloJid = config('services.whatsapp.notes_solo_group_jid');
        if (is_string($notesSoloJid) && trim($notesSoloJid) !== '' && $chatJid === trim($notesSoloJid)) {
            $soloGroupSource = MonitoredSource::query()
                ->where('identifier', $chatJid)
                ->where('kind', 'group')
                ->where('is_active', true)
                ->first();

            return new WebhookRouteResolution(
                WhatsAppInboundRoute::Personal,
                $soloGroupSource?->id,
            );
        }

        $contact = MonitoredSource::query()
            ->where('identifier', $chatJid)
            ->where('kind', 'contact')
            ->where('is_active', true)
            ->first();

        if ($contact !== null) {
            return new WebhookRouteResolution(WhatsAppInboundRoute::Contact, $contact->id);
        }

        $group = MonitoredSource::query()
            ->where('identifier', $chatJid)
            ->where('kind', 'group')
            ->where('is_active', true)
            ->first();

        if ($group !== null) {
            return new WebhookRouteResolution(WhatsAppInboundRoute::Group, $group->id);
        }

        return new WebhookRouteResolution(WhatsAppInboundRoute::Ignored);
    }

    private function dispatchForRoute(WebhookRouteResolution $resolution, int $messageLogId, string $correlationId): bool
    {
        match ($resolution->route) {
            WhatsAppInboundRoute::Personal => ProcessPersonalWhatsAppMessage::dispatch($messageLogId, $correlationId),
            WhatsAppInboundRoute::Contact => ProcessContactWhatsAppMessage::dispatch($messageLogId, $correlationId),
            WhatsAppInboundRoute::Group => ProcessGroupWhatsAppMessage::dispatch($messageLogId, $correlationId),
            WhatsAppInboundRoute::Ignored => null,
        };

        return $resolution->route !== WhatsAppInboundRoute::Ignored;
    }
}
