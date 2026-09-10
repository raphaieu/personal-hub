<?php

declare(strict_types=1);

namespace App\Livewire\Analyses;

use App\Models\AnalysisProfile;
use App\Models\MessageLog;
use App\Models\MonitoredSource;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class HubPage extends Component
{
    use WithPagination;

    #[Url(as: 'perfil')]
    public string $profileFilter = '';

    #[Url(as: 'fonte')]
    public string $sourceFilter = '';

    #[Url(as: 'inicio')]
    public string $dateFrom = '';

    #[Url(as: 'fim')]
    public string $dateTo = '';

    #[Url(as: 'processamento')]
    public string $statusFilter = '';

    #[Url(as: 'categoria')]
    public string $categoryFilter = '';

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'incluir_sem_fonte')]
    public bool $includeUnmonitored = false;

    public function mount(): void
    {
        Gate::authorize('viewAny', MessageLog::class);
    }

    public function updatedProfileFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSourceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedIncludeUnmonitored(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'profileFilter',
            'sourceFilter',
            'dateFrom',
            'dateTo',
            'statusFilter',
            'categoryFilter',
            'search',
            'includeUnmonitored',
        ]);
        $this->resetPage();
    }

    public function render(): View
    {
        $profileOptions = $this->profileOptions();

        return view('livewire.analyses.hub-page', [
            'messages' => $this->messagesQuery()->paginate(20),
            'profileOptions' => $profileOptions,
            'profileNames' => collect($profileOptions)->pluck('name', 'slug')->all(),
            'sourceOptions' => MonitoredSource::query()
                ->orderBy('label')
                ->orderBy('id')
                ->get(['id', 'label', 'identifier']),
            'statusOptions' => $this->statusOptions(),
            'categoryOptions' => $this->categoryOptions(),
        ])->layout('layouts.app');
    }

    /**
     * @return Builder<MessageLog>
     */
    private function messagesQuery(): Builder
    {
        $profileExpression = $this->profileSlugExpression();
        $summaryExpression = $this->summaryExpression();
        $scoreExpression = $this->normalizedScoreExpression();
        $categoryExpression = $this->categoryExpression();

        $query = MessageLog::query()
            ->select([
                'id',
                'monitored_source_id',
                'message_type',
                'ai_pipeline_status',
                'is_processed',
                'created_at',
            ])
            ->selectRaw('substr(body, 1, 241) as body_excerpt')
            ->selectRaw("{$profileExpression} as analysis_profile_slug")
            ->selectRaw("{$summaryExpression} as analysis_summary")
            ->selectRaw("{$scoreExpression} as normalized_relevance_score")
            ->selectRaw("{$categoryExpression} as display_category")
            ->selectRaw($this->offersCountExpression().' as offers_count')
            ->with('monitoredSource:id,label,identifier')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! $this->includeUnmonitored) {
            $query->whereNotNull('monitored_source_id');
        }

        if (ctype_digit($this->sourceFilter)) {
            $query->where('monitored_source_id', (int) $this->sourceFilter);
        }

        if ($this->profileFilter !== '') {
            $query->whereRaw("{$profileExpression} = ?", [$this->profileFilter]);
        }

        if ($this->statusFilter === '__waiting__') {
            $query->where(function (Builder $statusQuery): void {
                $statusQuery->whereNull('ai_pipeline_status')
                    ->orWhere('ai_pipeline_status', '');
            });
        } elseif ($this->statusFilter !== '') {
            $query->where('ai_pipeline_status', $this->statusFilter);
        }

        if ($this->categoryFilter !== '') {
            $query->whereRaw("{$categoryExpression} = ?", [$this->categoryFilter]);
        }

        if ($this->validDate($this->dateFrom)) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->validDate($this->dateTo)) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $metadataText = DB::getDriverName() === 'pgsql' ? 'metadata::text' : 'CAST(metadata AS TEXT)';
            $operator = DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

            $query->where(function (Builder $searchQuery) use ($like, $metadataText, $operator): void {
                $searchQuery
                    ->whereRaw("body {$operator} ?", [$like])
                    ->orWhereRaw("{$metadataText} {$operator} ?", [$like]);
            });
        }

        return $query;
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    private function profileOptions(): array
    {
        $options = [];

        $profiles = AnalysisProfile::query()
            ->where(function (Builder $query): void {
                $query->where('channel', 'whatsapp')->orWhereNull('channel');
            })
            ->orderBy('name')
            ->get(['slug', 'name', 'is_active']);

        foreach ($profiles as $profile) {
            $options[$profile->slug] = [
                'slug' => $profile->slug,
                'name' => $profile->name.($profile->is_active ? '' : ' (inativo)'),
            ];
        }

        $expression = $this->profileSlugExpression();
        $historicSlugs = MessageLog::query()
            ->whereNotNull('monitored_source_id')
            ->selectRaw("{$expression} as profile_slug")
            ->whereRaw("{$expression} IS NOT NULL")
            ->distinct()
            ->pluck('profile_slug');

        foreach ($historicSlugs as $slug) {
            if (is_string($slug) && $slug !== '' && ! isset($options[$slug])) {
                $options[$slug] = ['slug' => $slug, 'name' => $slug.' (histórico)'];
            }
        }

        uasort($options, static fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

        return array_values($options);
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        $labels = [
            '__waiting__' => 'Aguardando processamento',
            'classified' => 'Classificado',
            'pending_media_processing' => 'Aguardando mídia',
            'pending_text_extraction' => 'Aguardando extração de texto',
            'skipped_no_profile' => 'Sem perfil',
        ];

        $statuses = MessageLog::query()
            ->whereNotNull('monitored_source_id')
            ->whereNotNull('ai_pipeline_status')
            ->where('ai_pipeline_status', '<>', '')
            ->distinct()
            ->orderBy('ai_pipeline_status')
            ->pluck('ai_pipeline_status');

        foreach ($statuses as $status) {
            if (is_string($status) && ! isset($labels[$status])) {
                $labels[$status] = $status;
            }
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    private function categoryOptions(): array
    {
        $categories = [];
        $expression = $this->categoryExpression();

        $persisted = MessageLog::query()
            ->whereNotNull('monitored_source_id')
            ->selectRaw("{$expression} as result_category")
            ->whereRaw("{$expression} IS NOT NULL")
            ->distinct()
            ->pluck('result_category');

        foreach ($persisted as $category) {
            if (is_string($category) && trim($category) !== '') {
                $categories[trim($category)] = true;
            }
        }

        $profiles = AnalysisProfile::query()
            ->where(function (Builder $query): void {
                $query->where('channel', 'whatsapp')->orWhereNull('channel');
            })
            ->get(['allowed_categories']);

        foreach ($profiles as $profile) {
            foreach (is_array($profile->allowed_categories) ? $profile->allowed_categories : [] as $category) {
                if (is_string($category) && trim($category) !== '') {
                    $categories[trim($category)] = true;
                }
            }
        }

        $values = array_keys($categories);
        natcasesort($values);

        return array_values($values);
    }

    private function profileSlugExpression(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return "COALESCE(NULLIF(metadata->'analysis'->>'profile_slug', ''), NULLIF(metadata->'analysis'->'provider_meta'->>'profile_slug', ''))";
        }

        return "COALESCE(NULLIF(json_extract(metadata, '$.analysis.profile_slug'), ''), NULLIF(json_extract(metadata, '$.analysis.provider_meta.profile_slug'), ''))";
    }

    private function summaryExpression(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return "COALESCE(NULLIF(metadata->'analysis'->>'summary', ''), NULLIF(metadata->'analysis'->'raw_normalized'->>'summary', ''))";
        }

        return "COALESCE(NULLIF(json_extract(metadata, '$.analysis.summary'), ''), NULLIF(json_extract(metadata, '$.analysis.raw_normalized.summary'), ''))";
    }

    private function normalizedScoreExpression(): string
    {
        return DB::getDriverName() === 'pgsql'
            ? "metadata->'analysis'->>'relevance_score'"
            : "json_extract(metadata, '$.analysis.relevance_score')";
    }

    private function categoryExpression(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return "COALESCE(NULLIF(category, ''), NULLIF(metadata->'analysis'->'raw_normalized'->>'category_slug', ''), NULLIF(metadata->'analysis'->'raw_normalized'->>'category', ''))";
        }

        return "COALESCE(NULLIF(category, ''), NULLIF(json_extract(metadata, '$.analysis.raw_normalized.category_slug'), ''), NULLIF(json_extract(metadata, '$.analysis.raw_normalized.category'), ''))";
    }

    private function offersCountExpression(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return "CASE WHEN json_typeof(metadata->'analysis'->'raw_normalized'->'offers') = 'array' THEN json_array_length(metadata->'analysis'->'raw_normalized'->'offers') ELSE NULL END";
        }

        return "CASE WHEN json_type(metadata, '$.analysis.raw_normalized.offers') = 'array' THEN json_array_length(metadata, '$.analysis.raw_normalized.offers') ELSE NULL END";
    }

    private function validDate(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
