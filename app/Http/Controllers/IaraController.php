<?php

namespace App\Http\Controllers;

use App\Enums\AiTask;
use App\Http\Requests\Iara\IaraCompletionRequest;
use App\Services\NeuronAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class IaraController extends Controller
{
    public function __invoke(IaraCompletionRequest $request, NeuronAIService $ai): JsonResponse
    {
        /** @var array{prompt: string, mode?: string|null, system?: string|null, expect_json?: bool} $data */
        $data = $request->validated();

        $task = AiTask::tryFromHttp($data['mode'] ?? null);

        $result = $ai->complete(
            userPrompt: $data['prompt'],
            task: $task,
            systemPromptOverride: $data['system'] ?? null,
            expectJson: $request->boolean('expect_json'),
        );

        Log::info('iara.request', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'mode' => $task->value,
            'success' => $result->success,
            'ai_provider' => $result->provider ?: null,
            'latency_ms' => $result->latencyMs,
            'fallback_used' => $result->fallbackUsed,
        ]);

        $status = $result->success ? 200 : 503;

        return response()->json($result->toArray(config('app.debug')), $status);
    }
}
