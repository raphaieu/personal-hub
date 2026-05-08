<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'description',
    'is_active',
    'sort_order',
    'analysis_profile_id',
])]
class ThreadsCategory extends Model
{
    /**
     * @return BelongsTo<AnalysisProfile, $this>
     */
    public function analysisProfile(): BelongsTo
    {
        return $this->belongsTo(AnalysisProfile::class);
    }

    /**
     * @return HasMany<ThreadsComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ThreadsComment::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
