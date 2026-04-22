<?php

namespace App\Data;

readonly class AiCompletionResult
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public bool $success,
        public string $text,
        public string $provider,
        public string $model,
        public int $latencyMs,
        public bool $fallbackUsed,
        public ?string $errorType = null,
        public ?string $errorDetail = null,
        public ?array $meta = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withDebugRaw = false): array
    {
        $payload = [
            'ok' => $this->success,
            'content' => $this->success ? $this->text : null,
            'provider' => $this->provider ?: null,
            'model' => $this->model ?: null,
            'latency_ms' => $this->latencyMs,
            'fallback_used' => $this->fallbackUsed,
        ];

        if (! $this->success) {
            $payload['error'] = $this->errorDetail ?? $this->errorType;
            $payload['error_type'] = $this->errorType;
        }

        if ($withDebugRaw && $this->meta !== null) {
            $payload['meta'] = $this->meta;
        }

        return $payload;
    }
}
