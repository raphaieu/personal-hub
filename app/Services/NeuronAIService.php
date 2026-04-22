<?php

namespace App\Services;

use App\Data\AiCompletionResult;
use App\Enums\AiTask;

/**
 * Façade do Hub para inferência — delega política de roteamento ao AiRouterService.
 */
class NeuronAIService
{
    public function __construct(
        private readonly AiRouterService $router,
    ) {}

    /**
     * @see AiRouterService::complete()
     */
    public function complete(
        string $userPrompt,
        AiTask $task,
        ?string $systemPromptOverride = null,
        bool $expectJson = false,
    ): AiCompletionResult {
        return $this->router->complete(
            userPrompt: $userPrompt,
            task: $task,
            systemPromptOverride: $systemPromptOverride,
            expectJson: $expectJson,
        );
    }
}
