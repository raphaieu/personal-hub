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

    /**
     * @param  list<array{data: string, mime: string}>  $images
     *
     * @see AiRouterService::completeDirect()
     */
    public function completeDirect(
        string $providerKey,
        string $model,
        AiTask $task,
        string $userPrompt,
        ?string $systemPromptOverride = null,
        array $images = [],
    ): AiCompletionResult {
        return $this->router->completeDirect(
            providerKey: $providerKey,
            model: $model,
            task: $task,
            userPrompt: $userPrompt,
            systemPromptOverride: $systemPromptOverride,
            images: $images,
        );
    }
}
