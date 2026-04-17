<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'message_log_id',
    'kind',
    'original_file_name',
    'mime_type',
    'storage_path',
    'file_bytes',
    'duration_seconds',
    'width',
    'height',
    'sha256',
    'metadata',
])]
class MessageAttachment extends Model
{
    /**
     * @return BelongsTo<MessageLog, $this>
     */
    public function messageLog(): BelongsTo
    {
        return $this->belongsTo(MessageLog::class);
    }

    protected function casts(): array
    {
        return [
            'file_bytes' => 'integer',
            'duration_seconds' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
        ];
    }
}
