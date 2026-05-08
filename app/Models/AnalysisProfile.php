<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'description',
    'channel',
    'analysis_type',
    'system_prompt',
    'output_schema',
    'allowed_categories',
    'score_threshold',
    'settings',
    'is_active',
])]
class AnalysisProfile extends Model
{
    public const THREADS_OPPORTUNITIES_SLUG = 'threads-opportunities';

    /** @var list<string> */
    public const THREADS_ALLOWED_CATEGORIES = [
        'emprego-fixo',
        'temporario',
        'freela',
        'renda-extra',
        'outros',
    ];

    /**
     * @return HasMany<ThreadsSource, $this>
     */
    public function threadsSources(): HasMany
    {
        return $this->hasMany(ThreadsSource::class);
    }

    /**
     * @return HasMany<MonitoredSource, $this>
     */
    public function monitoredSources(): HasMany
    {
        return $this->hasMany(MonitoredSource::class);
    }

    /**
     * @return HasMany<ThreadsCategory, $this>
     */
    public function threadsCategories(): HasMany
    {
        return $this->hasMany(ThreadsCategory::class);
    }

    public static function defaultThreadsProfileId(): ?int
    {
        $id = self::query()
            ->where('slug', self::THREADS_OPPORTUNITIES_SLUG)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function isDefaultThreadsProfile(): bool
    {
        return $this->slug === self::THREADS_OPPORTUNITIES_SLUG;
    }

    protected function casts(): array
    {
        return [
            'output_schema' => 'array',
            'allowed_categories' => 'array',
            'score_threshold' => 'decimal:2',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
