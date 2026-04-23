<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Catálogo do chat: config estático + tags Ollama + opcionalmente GET /v1/models (Groq/OpenAI/Anthropic).
 */
final class AiChatCatalogService
{
    private const ANTHROPIC_MODELS_URL = 'https://api.anthropic.com/v1/models';

    private const OPENAI_MODELS_URL = 'https://api.openai.com/v1/models';

    public function __construct(
        private readonly OllamaService $ollama,
    ) {}

    /**
     * Lista nomes de modelos instalados no Ollama (cache).
     *
     * @return list<string>
     */
    public function ollamaTagNames(): array
    {
        if (! $this->ollama->enabled()) {
            return [];
        }

        $ttl = (int) config('ai_chat.ollama_tags_cache_ttl_seconds', 60);

        return Cache::remember('ai_chat.ollama_tag_names', $ttl, function (): array {
            $base = $this->ollama->apiBaseUrl();
            $url = rtrim($base, '/').'/tags';
            $timeout = (float) config('ai_chat.ollama_tags_timeout_seconds', 5);

            try {
                $response = Http::timeout($timeout)->acceptJson()->get($url);
                if (! $response->successful()) {
                    Log::warning('ai_chat.ollama_tags_http', ['status' => $response->status(), 'url' => $url]);

                    return [];
                }

                /** @var array<string, mixed> $json */
                $json = $response->json();
                $models = $json['models'] ?? [];
                if (! is_array($models)) {
                    return [];
                }

                $names = [];
                foreach ($models as $item) {
                    if (is_array($item) && isset($item['name']) && is_string($item['name'])) {
                        $names[] = $item['name'];
                    }
                }

                return array_values(array_unique($names));
            } catch (\Throwable $e) {
                Log::warning('ai_chat.ollama_tags_failed', ['message' => $e->getMessage()]);

                return [];
            }
        });
    }

    /**
     * IDs permitidos para validação (estático ∪ API remota em cache).
     *
     * @return list<string>
     */
    public function mergedModelIds(string $provider): array
    {
        $provider = strtolower($provider);

        $staticIds = array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['id'] ?? ''),
            config('ai_chat.providers.'.$provider, [])
        )));

        if (! (bool) config('ai_chat.fetch_remote_models', true)) {
            return $staticIds;
        }

        $hasKey = match ($provider) {
            'groq' => filled(config('services.groq.api_key')),
            'openai' => filled(config('services.openai.api_key')),
            'anthropic' => filled(config('services.anthropic.api_key')),
            default => false,
        };

        if (! $hasKey) {
            return $staticIds;
        }

        $ttl = (int) config('ai_chat.remote_models_cache_ttl_seconds', 300);

        return Cache::remember('ai_chat.merged_ids.'.$provider, $ttl, function () use ($provider, $staticIds): array {
            $remote = match ($provider) {
                'groq' => $this->fetchGroqModelIdsFromApi(),
                'openai' => $this->fetchOpenAiModelIdsFromApi(),
                'anthropic' => $this->fetchAnthropicModelIdsFromApi(),
                default => [],
            };

            return array_values(array_unique([...$staticIds, ...$remote]));
        });
    }

    /**
     * Heurística simples para modelos Ollama sem entrada no catálogo estático.
     */
    public function inferOllamaCapabilities(string $modelId): array
    {
        $lower = strtolower($modelId);

        $vision = str_contains($lower, 'llava')
            || str_contains($lower, 'moondream')
            || str_contains($lower, 'vision')
            || str_contains($lower, 'vl')
            || str_contains($lower, 'bakllava');

        return [
            'vision' => $vision,
            'audio_in_chat' => false,
            'transcription' => false,
            'image_generation' => false,
        ];
    }

    /**
     * @return array{id: string, label: string, vision: bool, audio_in_chat: bool, transcription: bool, image_generation: bool}|null
     */
    public function findStaticModel(string $provider, string $modelId): ?array
    {
        /** @var array<string, list<array<string, mixed>>> $providers */
        $providers = config('ai_chat.providers', []);

        foreach ($providers[$provider] ?? [] as $row) {
            if (($row['id'] ?? null) === $modelId) {
                return [
                    'id' => (string) $row['id'],
                    'label' => (string) ($row['label'] ?? $row['id']),
                    'vision' => (bool) ($row['vision'] ?? false),
                    'audio_in_chat' => (bool) ($row['audio_in_chat'] ?? false),
                    'transcription' => (bool) ($row['transcription'] ?? false),
                    'image_generation' => (bool) ($row['image_generation'] ?? false),
                ];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $ollamaNames
     * @return array{vision: bool, audio_in_chat: bool, transcription: bool, image_generation: bool}
     */
    public function capabilitiesFor(string $provider, string $modelId, array $ollamaNames = []): array
    {
        $static = $this->findStaticModel($provider, $modelId);
        if ($static !== null) {
            return [
                'vision' => $static['vision'],
                'audio_in_chat' => $static['audio_in_chat'],
                'transcription' => $static['transcription'],
                'image_generation' => $static['image_generation'],
            ];
        }

        if ($provider === 'ollama') {
            return $this->inferOllamaCapabilities($modelId);
        }

        return match ($provider) {
            'groq' => $this->inferGroqRemoteCapabilities($modelId),
            'openai' => $this->inferOpenAiRemoteCapabilities($modelId),
            'anthropic' => $this->inferAnthropicRemoteCapabilities($modelId),
            default => [
                'vision' => false,
                'audio_in_chat' => false,
                'transcription' => false,
                'image_generation' => false,
            ],
        };
    }

    public function isModelAllowed(string $provider, string $modelId, array $ollamaNames): bool
    {
        if ($this->findStaticModel($provider, $modelId) !== null) {
            return true;
        }

        if ($provider === 'ollama') {
            if ($ollamaNames !== [] && in_array($modelId, $ollamaNames, true)) {
                return true;
            }

            return $modelId === (string) config('services.ollama.model');
        }

        return match ($provider) {
            'groq', 'openai', 'anthropic' => in_array($modelId, $this->mergedModelIds($provider), true),
            default => false,
        };
    }

    /**
     * Payload para GET chat-options.
     *
     * @return array<string, mixed>
     */
    public function buildOptionsPayload(): array
    {
        $ollamaNames = $this->ollamaTagNames();

        $providers = [];

        if ($this->ollama->enabled()) {
            $models = [];
            foreach ($ollamaNames as $name) {
                $cap = $this->inferOllamaCapabilities($name);
                $models[] = [
                    'id' => $name,
                    'label' => $name,
                    'vision' => $cap['vision'],
                    'audio_in_chat' => $cap['audio_in_chat'],
                    'transcription' => $cap['transcription'],
                    'image_generation' => $cap['image_generation'],
                ];
            }

            $defaultModel = (string) config('services.ollama.model');
            if ($models === [] && $defaultModel !== '') {
                $cap = $this->inferOllamaCapabilities($defaultModel);
                $models[] = [
                    'id' => $defaultModel,
                    'label' => $defaultModel.' (fallback .env — /api/tags indisponível)',
                    'vision' => $cap['vision'],
                    'audio_in_chat' => false,
                    'transcription' => false,
                    'image_generation' => false,
                ];
            }

            $providers[] = [
                'id' => 'ollama',
                'label' => 'Ollama',
                'models' => $models,
                'hint' => $ollamaNames === []
                    ? 'OLLAMA_ENABLED=true mas /api/tags não retornou lista — confira OLLAMA_BASE_URL (no Docker use o IP/hostname que o PHP alcança; só aparece o modelo do .env até as tags responderem).'
                    : null,
            ];
        }

        foreach (['groq', 'anthropic', 'openai'] as $pid) {
            $configured = match ($pid) {
                'groq' => filled(config('services.groq.api_key')),
                'anthropic' => filled(config('services.anthropic.api_key')),
                'openai' => filled(config('services.openai.api_key')),
                default => false,
            };

            if (! $configured) {
                continue;
            }

            /** @var list<array<string, mixed>> $rows */
            $rows = config('ai_chat.providers.'.$pid, []);

            $models = [];
            $seen = [];

            foreach ($rows as $row) {
                $id = (string) $row['id'];
                $seen[$id] = true;
                $models[] = [
                    'id' => $id,
                    'label' => (string) ($row['label'] ?? $id),
                    'vision' => (bool) ($row['vision'] ?? false),
                    'audio_in_chat' => (bool) ($row['audio_in_chat'] ?? false),
                    'transcription' => (bool) ($row['transcription'] ?? false),
                    'image_generation' => (bool) ($row['image_generation'] ?? false),
                    'source' => 'catalog',
                ];
            }

            $mergedIds = $this->mergedModelIds($pid);
            $extra = [];
            foreach ($mergedIds as $mid) {
                if (isset($seen[$mid])) {
                    continue;
                }
                $cap = match ($pid) {
                    'groq' => $this->inferGroqRemoteCapabilities($mid),
                    'openai' => $this->inferOpenAiRemoteCapabilities($mid),
                    'anthropic' => $this->inferAnthropicRemoteCapabilities($mid),
                    default => ['vision' => false, 'audio_in_chat' => false, 'transcription' => false, 'image_generation' => false],
                };
                $extra[] = [
                    'id' => $mid,
                    'label' => $mid.' (API)',
                    'vision' => $cap['vision'],
                    'audio_in_chat' => $cap['audio_in_chat'],
                    'transcription' => $cap['transcription'],
                    'image_generation' => $cap['image_generation'],
                    'source' => 'remote',
                ];
            }

            usort($extra, static fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']));

            $providers[] = [
                'id' => $pid,
                'label' => match ($pid) {
                    'groq' => 'Groq',
                    'anthropic' => 'Anthropic',
                    'openai' => 'OpenAI',
                    default => $pid,
                },
                'models' => [...$models, ...$extra],
            ];
        }

        $transcription = [
            'openai' => filled(config('services.openai.api_key')),
            'groq' => filled(config('services.groq.api_key')),
        ];

        $imageGeneration = [
            'openai' => filled(config('services.openai.api_key')),
        ];

        return [
            'providers' => $providers,
            'transcription' => $transcription,
            'image_generation' => $imageGeneration,
            'defaults' => [
                'openai_transcription_model' => (string) config('ai_chat.transcription.openai_model'),
                'groq_transcription_model' => (string) config('ai_chat.transcription.groq_model'),
                'openai_image_model' => (string) config('ai_chat.image_generation.openai_model'),
                'openai_image_size' => (string) config('ai_chat.image_generation.size'),
            ],
            'meta' => [
                'fetch_remote_models' => (bool) config('ai_chat.fetch_remote_models', true),
                'ollama_enabled' => $this->ollama->enabled(),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function fetchGroqModelIdsFromApi(): array
    {
        $key = config('services.groq.api_key');
        if (! filled($key)) {
            return [];
        }

        $base = rtrim((string) config('services.groq.url'), '/');
        $url = $base.'/models';
        $timeout = (float) config('ai_chat.remote_models_timeout_seconds', 15);

        try {
            $response = Http::timeout($timeout)
                ->withToken((string) $key)
                ->acceptJson()
                ->get($url);

            if (! $response->successful()) {
                Log::warning('ai_chat.groq_models_http', ['status' => $response->status(), 'url' => $url]);

                return [];
            }

            return $this->parseOpenAiStyleModelsResponse($response->json());
        } catch (\Throwable $e) {
            Log::warning('ai_chat.groq_models_failed', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function fetchOpenAiModelIdsFromApi(): array
    {
        $key = config('services.openai.api_key');
        if (! filled($key)) {
            return [];
        }

        $timeout = (float) config('ai_chat.remote_models_timeout_seconds', 15);

        try {
            $response = Http::timeout($timeout)
                ->withToken((string) $key)
                ->acceptJson()
                ->get(self::OPENAI_MODELS_URL);

            if (! $response->successful()) {
                Log::warning('ai_chat.openai_models_http', ['status' => $response->status()]);

                return [];
            }

            return $this->parseOpenAiStyleModelsResponse($response->json());
        } catch (\Throwable $e) {
            Log::warning('ai_chat.openai_models_failed', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function fetchAnthropicModelIdsFromApi(): array
    {
        $key = config('services.anthropic.api_key');
        if (! filled($key)) {
            return [];
        }

        $timeout = (float) config('ai_chat.remote_models_timeout_seconds', 15);
        $ids = [];

        try {
            $afterId = null;

            for ($page = 0; $page < 20; $page++) {
                $query = ['limit' => 100];
                if ($afterId !== null) {
                    $query['after_id'] = $afterId;
                }

                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'x-api-key' => (string) $key,
                        'anthropic-version' => '2023-06-01',
                    ])
                    ->acceptJson()
                    ->get(self::ANTHROPIC_MODELS_URL, $query);

                if (! $response->successful()) {
                    Log::warning('ai_chat.anthropic_models_http', ['status' => $response->status()]);

                    break;
                }

                /** @var array<string, mixed> $json */
                $json = $response->json();
                $data = $json['data'] ?? [];
                if (! is_array($data) || $data === []) {
                    break;
                }

                foreach ($data as $item) {
                    if (is_array($item) && isset($item['id']) && is_string($item['id'])) {
                        $ids[] = $item['id'];
                    }
                }

                $hasMore = (bool) ($json['has_more'] ?? false);
                $last = $data[array_key_last($data)];
                $afterId = ($hasMore && is_array($last) && isset($last['id']) && is_string($last['id']))
                    ? $last['id']
                    : null;

                if ($afterId === null || ! $hasMore) {
                    break;
                }
            }

            return array_values(array_unique($ids));
        } catch (\Throwable $e) {
            Log::warning('ai_chat.anthropic_models_failed', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>|null  $json
     * @return list<string>
     */
    private function parseOpenAiStyleModelsResponse(?array $json): array
    {
        if ($json === null) {
            return [];
        }

        $data = $json['data'] ?? [];
        if (! is_array($data)) {
            return [];
        }

        $ids = [];
        foreach ($data as $item) {
            if (is_array($item) && isset($item['id']) && is_string($item['id'])) {
                $ids[] = $item['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return array{vision: bool, audio_in_chat: bool, transcription: bool, image_generation: bool}
     */
    private function inferGroqRemoteCapabilities(string $modelId): array
    {
        $l = strtolower($modelId);
        $vision = str_contains($l, 'vision')
            || str_contains($l, 'scout')
            || str_contains($l, 'maverick')
            || str_contains($l, 'llama-4')
            || str_contains($l, 'multimodal');

        return [
            'vision' => $vision,
            'audio_in_chat' => false,
            'transcription' => false,
            'image_generation' => false,
        ];
    }

    /**
     * @return array{vision: bool, audio_in_chat: bool, transcription: bool, image_generation: bool}
     */
    private function inferOpenAiRemoteCapabilities(string $modelId): array
    {
        $l = strtolower($modelId);

        $vision = str_contains($l, 'gpt-4o')
            || str_contains($l, 'gpt-5')
            || str_contains($l, 'chatgpt-4o')
            || str_contains($l, 'gpt-4-turbo')
            || str_contains($l, 'o1')
            || str_contains($l, 'o3')
            || str_contains($l, 'gpt-realtime')
            || str_contains($l, 'audio'); // alguns modelos multimodais

        return [
            'vision' => $vision,
            'audio_in_chat' => false,
            'transcription' => false,
            'image_generation' => false,
        ];
    }

    /**
     * @return array{vision: bool, audio_in_chat: bool, transcription: bool, image_generation: bool}
     */
    private function inferAnthropicRemoteCapabilities(string $modelId): array
    {
        $l = strtolower($modelId);
        // Claude Messages API: visão amplamente disponível nas séries recentes (heurística por nome).
        $vision = str_contains($l, 'claude');

        return [
            'vision' => $vision,
            'audio_in_chat' => false,
            'transcription' => false,
            'image_generation' => false,
        ];
    }
}
