<?php

namespace App\Contracts;

use App\Data\AiCompletionResult;
use App\Enums\AiTask;

interface AiVisionServiceInterface
{
    /**
     * Cadeia com fallback para prompts com imagem (visão).
     *
     * @param  list<array{data: string, mime: string}>  $images  Base64 cru (sem prefixo data:)
     */
    public function completeWithVision(
        string $userPrompt,
        AiTask $task,
        array $images,
        ?string $systemPromptOverride = null,
        bool $expectJson = false,
    ): AiCompletionResult;
}
