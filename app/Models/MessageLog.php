<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'monitored_source_id',
    'chat_jid',
    'sender_jid',
    'direction',
    'message_type',
    'body',
    'mentions',
    'quoted_evolution_message_id',
    'intent',
    'sentiment',
    'confidence',
    'category',
    'metadata',
    'is_processed',
    'transcription_text',
    'transcription_provider',
    'transcribed_at',
    'vision_summary',
    'vision_provider',
    'ai_pipeline_status',
    'evolution_message_id',
])]
class MessageLog extends Model
{
    /**
     * @return BelongsTo<MonitoredSource, $this>
     */
    public function monitoredSource(): BelongsTo
    {
        return $this->belongsTo(MonitoredSource::class);
    }

    /**
     * @return HasMany<MessageAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    /**
     * @return HasMany<Reminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    protected function casts(): array
    {
        return [
            'mentions' => 'array',
            'metadata' => 'array',
            'confidence' => 'decimal:4',
            'is_processed' => 'boolean',
            'transcribed_at' => 'datetime',
        ];
    }
}
