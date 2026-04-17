<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'kind',
    'body',
    'file_path',
    'url_title',
    'url_description',
    'url_image',
    'category',
    'is_archived',
    'message_log_id',
])]
class Reminder extends Model
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
            'is_archived' => 'boolean',
        ];
    }
}
