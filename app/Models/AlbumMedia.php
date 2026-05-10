<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'album_id',
    'type',
    'original_path',
    'thumb_path',
    'medium_path',
    'video_thumb_path',
    'filename_original',
    'display_name',
    'mime_type',
    'size_bytes',
    'width',
    'height',
    'duration_seconds',
    'sort_position',
    'processing_status',
    'uploaded_by',
    'contributor_id',
    'metadata',
])]
class AlbumMedia extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'album_media';

    /**
     * @return BelongsTo<Album, $this>
     */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /**
     * @return BelongsTo<Contributor, $this>
     */
    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_seconds' => 'integer',
            'sort_position' => 'integer',
            'metadata' => 'array',
        ];
    }
}
