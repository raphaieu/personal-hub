<?php

namespace App\Data\Analysis;

readonly class StructuredAnalysisResult
{
    /**
     * @param  list<string>  $labels
     * @param  array<string, mixed>  $rawNormalized
     * @param  array<string, mixed>  $providerMeta
     */
    public function __construct(
        public string $status,
        public ?string $summary,
        public ?string $category,
        public array $labels,
        public ?float $relevanceScore,
        public ?float $confidence,
        public ?string $sentiment,
        public array $rawNormalized,
        public array $providerMeta,
    ) {}
}
