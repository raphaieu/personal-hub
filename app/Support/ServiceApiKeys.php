<?php

namespace App\Support;

/**
 * Resolve chaves de APIs de terceiros com fallback para getenv().
 *
 * Útil em produção quando `config:cache` foi gerado antes das chaves ou quando
 * segredos vêm só como env do container (Docker/K8s), sem reexecutar o cache de config.
 */
final class ServiceApiKeys
{
    private const GROQ_ENV = 'GROQ_API_KEY';

    private const OPENAI_ENV = 'OPENAI_API_KEY';

    private const ANTHROPIC_ENV = 'ANTHROPIC_API_KEY';

    public static function groq(): ?string
    {
        return self::resolve('services.groq.api_key', self::GROQ_ENV);
    }

    public static function openAi(): ?string
    {
        return self::resolve('services.openai.api_key', self::OPENAI_ENV);
    }

    public static function anthropic(): ?string
    {
        return self::resolve('services.anthropic.api_key', self::ANTHROPIC_ENV);
    }

    private static function resolve(string $configKey, string $envName): ?string
    {
        $fromConfig = config($configKey);
        if (filled($fromConfig)) {
            return trim((string) $fromConfig);
        }

        $raw = $_ENV[$envName] ?? getenv($envName);
        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        return null;
    }
}
