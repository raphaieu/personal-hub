<?php

namespace App\Services;

use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\Providers\Ollama\Ollama;

final class OllamaService
{
    public function enabled(): bool
    {
        return (bool) config('services.ollama.enabled');
    }

    /**
     * Base URL ending with `/api` for NeuronAI Ollama provider.
     */
    public function apiBaseUrl(): string
    {
        $base = rtrim((string) config('services.ollama.base_url'), '/');

        return str_ends_with($base, '/api') ? $base : $base.'/api';
    }

    /**
     * @param  array<string, mixed>  $extraParameters
     */
    public function makeProvider(float $timeout, array $extraParameters = []): Ollama
    {
        $think = (bool) config('services.ollama.think');
        $parameters = array_merge(
            $think ? ['think' => true] : ['think' => false],
            $extraParameters,
        );

        $http = new GuzzleHttpClient([], $timeout);

        return new Ollama(
            url: $this->apiBaseUrl(),
            model: (string) config('services.ollama.model'),
            parameters: $parameters,
            httpClient: $http,
        );
    }

    public function defaultTimeoutSeconds(): int
    {
        return (int) config('services.ollama.timeout', 20);
    }
}
