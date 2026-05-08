<?php

namespace App\Services\Analysis;

use App\Data\Analysis\AnalysisExecutionInput;
use App\Data\Analysis\StructuredAnalysisResult;
use App\Enums\AiTask;
use App\Services\NeuronAIService;
use Illuminate\Support\Arr;
use RuntimeException;

final class AnalysisExecutionService
{
    public function __construct(
        private readonly NeuronAIService $aiService,
    ) {}

    public function execute(AnalysisExecutionInput $input): StructuredAnalysisResult
    {
        $task = $this->resolveTask($input);
        $completion = $this->aiService->complete(
            userPrompt: $this->buildPrompt($input),
            task: $task,
            systemPromptOverride: $input->profile->system_prompt,
            expectJson: true,
        );

        if (! $completion->success) {
            throw new RuntimeException($completion->errorDetail ?? 'Falha ao executar análise de conteúdo.');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($completion->text, true);
        if (! is_array($payload)) {
            throw new RuntimeException('A IA retornou um payload inválido para análise.');
        }

        $this->validateBySchema($payload, $input->profile->output_schema);

        $summary = $this->stringOrNull($this->resolveMappedValue($payload, $input, 'summary', 'summary'));
        $category = $this->stringOrNull($this->resolveMappedValue($payload, $input, 'category', 'category_slug'));
        $score = $this->floatOrNull($this->resolveMappedValue($payload, $input, 'relevance_score', 'relevance_score'));
        $confidence = $this->floatOrNull($this->resolveMappedValue($payload, $input, 'confidence', 'confidence'));
        $sentiment = $this->stringOrNull($this->resolveMappedValue($payload, $input, 'sentiment', 'sentiment'));
        $labels = $this->resolveLabels($payload);

        return new StructuredAnalysisResult(
            status: 'completed',
            summary: $summary,
            category: $category,
            labels: $labels,
            relevanceScore: $score,
            confidence: $confidence,
            sentiment: $sentiment,
            rawNormalized: $payload,
            providerMeta: [
                'provider' => $completion->provider,
                'model' => $completion->model,
                'latency_ms' => $completion->latencyMs,
                'fallback_used' => $completion->fallbackUsed,
                'profile_id' => $input->profile->id,
                'profile_slug' => $input->profile->slug,
            ],
        );
    }

    private function buildPrompt(AnalysisExecutionInput $input): string
    {
        $sourceMetadata = $input->item->source !== []
            ? json_encode($input->item->source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '{}';

        return implode("\n", [
            'Analise o item abaixo e responda estritamente em JSON válido conforme o schema esperado.',
            'Nao use markdown.',
            '',
            'Canal:',
            $input->item->channel,
            '',
            'Tipo do item:',
            $input->item->itemType,
            '',
            'ID externo:',
            $input->item->externalId,
            '',
            'Conteudo:',
            (string) ($input->item->contentText ?? ''),
            '',
            'Metadados da source:',
            (string) $sourceMetadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $schema
     */
    private function validateBySchema(array $payload, ?array $schema): void
    {
        if (! is_array($schema) || $schema === []) {
            return;
        }

        $requiredFields = Arr::get($schema, 'required');
        if (is_array($requiredFields)) {
            foreach ($requiredFields as $field) {
                if (! is_string($field) || ! array_key_exists($field, $payload)) {
                    throw new RuntimeException('Payload de análise sem campo obrigatório: '.$field);
                }
            }
        }

        $properties = Arr::get($schema, 'properties');
        if (! is_array($properties)) {
            return;
        }

        foreach ($properties as $field => $config) {
            if (! array_key_exists($field, $payload) || ! is_array($config)) {
                continue;
            }

            $type = Arr::get($config, 'type');
            if (! is_string($type)) {
                continue;
            }

            if (! $this->matchesType($payload[$field], $type)) {
                throw new RuntimeException("Campo {$field} não corresponde ao tipo esperado {$type}.");
            }
        }
    }

    private function resolveTask(AnalysisExecutionInput $input): AiTask
    {
        $taskValue = Arr::get($input->profile->settings, 'ai_task');
        if (is_string($taskValue)) {
            $enum = AiTask::tryFrom($taskValue);
            if ($enum instanceof AiTask) {
                return $enum;
            }
        }

        return AiTask::Classification;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveMappedValue(array $payload, AnalysisExecutionInput $input, string $targetKey, string $defaultKey): mixed
    {
        $sourceField = Arr::get($input->profile->settings, 'result_mapping.'.$targetKey);
        if (is_string($sourceField) && $sourceField !== '') {
            return Arr::get($payload, $sourceField);
        }

        return Arr::get($payload, $defaultKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function resolveLabels(array $payload): array
    {
        $labels = Arr::get($payload, 'labels');
        if (! is_array($labels)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $label): ?string {
            if (! is_string($label)) {
                return null;
            }

            $value = trim($label);

            return $value === '' ? null : $value;
        }, $labels)));
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value),
            default => true,
        };
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function floatOrNull(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
