<?php

namespace App\Services;

use App\Data\AiCompletionResult;
use App\Enums\AiTask;
use Illuminate\Support\Facades\Log;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\HttpException;
use NeuronAI\Exceptions\ProviderException;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\OpenAI\OpenAI;
use NeuronAI\Providers\OpenAILike;
use Throwable;

final class AiRouterService
{
    public function __construct(
        private readonly OllamaService $ollama,
    ) {}

    /**
     * Runs the configured provider chain with timeouts and fallback (see config/ai.php).
     */
    public function complete(
        string $userPrompt,
        AiTask $task,
        ?string $systemPromptOverride = null,
        bool $expectJson = false,
    ): AiCompletionResult {
        $system = $systemPromptOverride ?? $task->systemPrompt();

        $useOllamaFirst = $this->shouldAttemptOllamaFirst($task, $userPrompt);

        /** @var list<array{provider: string, model: string, call: callable(): AssistantMessage}> $steps */
        $steps = [];

        if ($useOllamaFirst && $this->ollama->enabled()) {
            $steps[] = [
                'provider' => 'ollama',
                'model' => (string) config('services.ollama.model'),
                'call' => function () use ($system, $userPrompt): AssistantMessage {
                    $timeout = (float) config('ai.ollama_timeout_simple', 10);
                    $provider = $this->ollama->makeProvider($timeout);

                    return $this->invokeProvider($provider, $system, $userPrompt);
                },
            ];
        }

        $groq = $this->groqProvider();
        if ($groq !== null) {
            $steps[] = [
                'provider' => 'groq',
                'model' => (string) config('services.groq.model'),
                'call' => function () use ($groq, $system, $userPrompt): AssistantMessage {
                    return $this->invokeProvider($groq, $system, $userPrompt);
                },
            ];
        }

        $anthropic = $this->anthropicProvider();
        if ($anthropic !== null) {
            $steps[] = [
                'provider' => 'anthropic',
                'model' => (string) config('services.anthropic.model'),
                'call' => function () use ($anthropic, $system, $userPrompt): AssistantMessage {
                    return $this->invokeProvider($anthropic, $system, $userPrompt);
                },
            ];
        }

        $openai = $this->openAiProvider();
        if ($openai !== null) {
            $steps[] = [
                'provider' => 'openai',
                'model' => (string) config('services.openai.model'),
                'call' => function () use ($openai, $system, $userPrompt): AssistantMessage {
                    return $this->invokeProvider($openai, $system, $userPrompt);
                },
            ];
        }

        if ($steps === []) {
            return new AiCompletionResult(
                success: false,
                text: '',
                provider: '',
                model: '',
                latencyMs: 0,
                fallbackUsed: false,
                errorType: 'no_provider',
                errorDetail: 'Nenhum provedor de IA configurado (defina Ollama e/ou chaves Groq/Anthropic/OpenAI).',
            );
        }

        $fallbackUsed = false;
        $lastError = null;
        $lastErrorType = null;

        foreach ($steps as $index => $step) {
            $started = hrtime(true);

            try {
                /** @var AssistantMessage $assistant */
                $assistant = ($step['call'])();
                $latencyMs = (int) round((hrtime(true) - $started) / 1_000_000);

                $text = trim((string) ($assistant->getContent() ?? ''));

                if ($this->responseIsInvalid($text, $expectJson)) {
                    throw new ProviderException('Resposta vazia ou JSON inválido para o modo solicitado.');
                }

                if ($index > 0) {
                    $fallbackUsed = true;
                }

                Log::info('ai.completion', [
                    'ai_provider' => $step['provider'],
                    'ai_model' => $step['model'],
                    'latency_ms' => $latencyMs,
                    'fallback_used' => $fallbackUsed,
                    'ai_task' => $task->value,
                ]);

                return new AiCompletionResult(
                    success: true,
                    text: $text,
                    provider: $step['provider'],
                    model: $step['model'],
                    latencyMs: $latencyMs,
                    fallbackUsed: $fallbackUsed,
                    meta: config('app.debug') ? ['stop_reason' => $assistant->stopReason()] : null,
                );
            } catch (Throwable $e) {
                $fallbackUsed = true;
                $lastError = $e;
                $lastErrorType = $this->classifyThrowable($e);

                Log::warning('ai.completion_failure', [
                    'ai_provider' => $step['provider'],
                    'ai_model' => $step['model'],
                    'ai_task' => $task->value,
                    'error_type' => $lastErrorType,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return new AiCompletionResult(
            success: false,
            text: '',
            provider: '',
            model: '',
            latencyMs: 0,
            fallbackUsed: $fallbackUsed,
            errorType: $lastErrorType ?? 'unknown',
            errorDetail: $lastError?->getMessage(),
        );
    }

    private function shouldAttemptOllamaFirst(AiTask $task, string $userPrompt): bool
    {
        if (! $task->prefersLocalFirst()) {
            return false;
        }

        $threshold = (int) config('ai.prompt_long_threshold_chars', 2000);

        return mb_strlen($userPrompt) <= $threshold;
    }

    private function invokeProvider(
        AIProviderInterface $provider,
        ?string $system,
        string $userPrompt,
    ): AssistantMessage {
        $provider->systemPrompt($system);

        $message = $provider->chat(new Message(MessageRole::USER, $userPrompt));

        if (! $message instanceof AssistantMessage) {
            throw new ProviderException('Resposta inesperada do provedor.');
        }

        return $message;
    }

    private function responseIsInvalid(string $text, bool $expectJson): bool
    {
        if ($text === '') {
            return true;
        }

        if ($expectJson) {
            json_decode($text, true);

            return json_last_error() !== JSON_ERROR_NONE;
        }

        return false;
    }

    private function classifyThrowable(Throwable $e): string
    {
        $msg = strtolower($e->getMessage());

        if ($e instanceof HttpException) {
            if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
                return 'timeout';
            }

            return 'http';
        }

        if ($e instanceof ProviderException) {
            if (str_contains($msg, 'json') || str_contains($msg, 'inválido')) {
                return 'invalid_response';
            }
        }

        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
            return 'timeout';
        }

        if (str_contains($msg, 'connection') || str_contains($msg, 'could not resolve')) {
            return 'connection';
        }

        return 'provider';
    }

    private function groqProvider(): ?OpenAILike
    {
        $key = config('services.groq.api_key');
        if (! filled($key)) {
            return null;
        }

        $baseUri = rtrim((string) config('services.groq.url'), '/');

        return new OpenAILike(
            baseUri: $baseUri,
            key: (string) $key,
            model: (string) config('services.groq.model'),
            parameters: [],
            strict_response: false,
            httpClient: new GuzzleHttpClient([], (float) config('ai.groq_timeout', 30)),
        );
    }

    private function anthropicProvider(): ?Anthropic
    {
        $key = config('services.anthropic.api_key');
        if (! filled($key)) {
            return null;
        }

        return new Anthropic(
            key: (string) $key,
            model: (string) config('services.anthropic.model'),
            version: '2023-06-01',
            max_tokens: 8192,
            parameters: [],
            httpClient: new GuzzleHttpClient([], (float) config('ai.anthropic_timeout', 60)),
        );
    }

    private function openAiProvider(): ?OpenAI
    {
        $key = config('services.openai.api_key');
        if (! filled($key)) {
            return null;
        }

        return new OpenAI(
            key: (string) $key,
            model: (string) config('services.openai.model'),
            parameters: [],
            strict_response: false,
            httpClient: new GuzzleHttpClient([], (float) config('ai.openai_timeout', 60)),
        );
    }
}
