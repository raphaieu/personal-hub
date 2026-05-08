<?php

namespace App\Data\Analysis;

use App\Models\AnalysisProfile;

readonly class AnalysisExecutionInput
{
    public function __construct(
        public NormalizedContentItem $item,
        public AnalysisProfile $profile,
    ) {}
}
