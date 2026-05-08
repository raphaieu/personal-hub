<?php

namespace App\Data\Analysis;

readonly class NormalizedContentItem
{
    /**
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $channel,
        public string $itemType,
        public string $externalId,
        public ?string $contentText,
        public array $source = [],
        public array $attributes = [],
    ) {}
}
