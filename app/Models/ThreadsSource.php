<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type',
    'label',
    'keyword',
    'target_url',
    'is_active',
    'last_scraped_at',
    'settings',
    'analysis_profile_id',
])]
class ThreadsSource extends Model
{
    /**
     * @return BelongsTo<AnalysisProfile, $this>
     */
    public function analysisProfile(): BelongsTo
    {
        return $this->belongsTo(AnalysisProfile::class);
    }

    /**
     * @return HasMany<ThreadsPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ThreadsPost::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_scraped_at' => 'datetime',
            'settings' => 'array',
        ];
    }
}
