<?php

namespace App\Enums;

enum AiTask: string
{
    case Classification = 'classification';
    case ThreadsOpportunityClassification = 'threads_opportunity_classification';
    case Sentiment = 'sentiment';
    case SummaryShort = 'summary_short';
    case ChatDefault = 'chat';
    case ChatLong = 'chat_long';

    /**
     * @throws \InvalidArgumentException
     */
    public static function tryFromHttp(?string $mode): self
    {
        if ($mode === null || $mode === '') {
            return self::ChatDefault;
        }

        /** @phpstan-ignore-next-line */
        return match ($mode) {
            'classification', 'classify' => self::Classification,
            'sentiment' => self::Sentiment,
            'summary_short' => self::SummaryShort,
            'chat', 'chat_default', 'default' => self::ChatDefault,
            'chat_long', 'long' => self::ChatLong,
            default => throw new \InvalidArgumentException('mode inválido.'),
        };
    }

    /**
     * Whether this task prefers Ollama first (cheap local work).
     */
    public function prefersLocalFirst(): bool
    {
        return match ($this) {
            self::Classification,
            self::ThreadsOpportunityClassification,
            self::Sentiment,
            self::SummaryShort,
            self::ChatDefault => true,
            self::ChatLong => false,
        };
    }

    /**
     * Default system hint (Portuguese prompts for WhatsApp hub).
     */
    public function systemPrompt(): ?string
    {
        return match ($this) {
            self::Classification => 'Respondas em pt-BR. Para classificar, devolva só um único label curto sem explicações, salvo pedido contrário pelo usuário.',
            self::ThreadsOpportunityClassification => 'Responda em JSON válido para classificar oportunidades no Threads. Campos obrigatórios: category_slug (emprego-fixo|temporario|freela|renda-extra|outros), summary (string curta em pt-BR), relevance_score (número de 0 a 1). Sem markdown.',
            self::Sentiment => 'Respondas em pt-BR. Avalie apenas o sentimento principal (positivo, neutro ou negativo) numa única linha.',
            self::SummaryShort => 'Respondas em pt-BR. Resumo curto em até três frases.',
            self::ChatDefault => null,
            self::ChatLong => null,
        };
    }
}
