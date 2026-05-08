<?php

namespace App\Services\Analysis\Adapters;

use App\Data\Analysis\NormalizedContentItem;
use App\Models\MessageLog;

final class MessageLogToNormalizedContentAdapter
{
    public function fromMessageLog(MessageLog $messageLog): NormalizedContentItem
    {
        return new NormalizedContentItem(
            channel: 'whatsapp',
            itemType: 'whatsapp_message',
            externalId: (string) ($messageLog->evolution_message_id ?: 'message-log-'.$messageLog->id),
            contentText: $messageLog->body,
            source: [
                'monitored_source_id' => $messageLog->monitored_source_id,
                'chat_jid' => $messageLog->chat_jid,
                'sender_jid' => $messageLog->sender_jid,
                'message_type' => $messageLog->message_type,
                'direction' => $messageLog->direction,
            ],
            attributes: [
                'message_log_id' => $messageLog->id,
            ],
        );
    }
}
