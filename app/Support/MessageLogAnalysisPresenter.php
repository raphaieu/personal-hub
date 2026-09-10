<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MessageLog;

final class MessageLogAnalysisPresenter
{
    private const OFFER_FIELDS = [
        'title',
        'segment',
        'store',
        'price',
        'original_price',
        'currency',
        'coupon',
        'conditions',
        'validity_text',
    ];

    /**
     * @return array<string, mixed>
     */
    public function present(MessageLog $message): array
    {
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $analysis = is_array($metadata['analysis'] ?? null) ? $metadata['analysis'] : [];
        $raw = is_array($analysis['raw_normalized'] ?? null) ? $analysis['raw_normalized'] : [];
        $provider = is_array($analysis['provider_meta'] ?? null) ? $analysis['provider_meta'] : [];

        $offersPresent = array_key_exists('offers', $raw);
        $offersRaw = $offersPresent ? $raw['offers'] : null;
        $offersValid = is_array($offersRaw)
            && array_is_list($offersRaw)
            && count(array_filter($offersRaw, 'is_array')) === count($offersRaw);

        $structuredFields = [];
        foreach ($raw as $key => $value) {
            if (! is_string($key) || $key === 'offers') {
                continue;
            }

            $structuredFields[] = [
                'name' => $key,
                'value' => $this->displayValue($value),
                'multiline' => is_array($value) || is_object($value),
            ];
        }

        return [
            'analysis' => $analysis,
            'analysis_json' => $this->prettyJson($analysis),
            'raw_json' => $this->prettyJson($raw),
            'structured_fields' => $structuredFields,
            'profile_slug' => $this->firstString([
                $analysis['profile_slug'] ?? null,
                $provider['profile_slug'] ?? null,
            ]),
            'profile_id' => array_key_exists('profile_id', $analysis)
                ? $this->displayValue($analysis['profile_id'])
                : $this->displayOptional($provider, 'profile_id'),
            'analysis_status' => $this->stringOrNull($analysis['status'] ?? null),
            'summary' => $this->firstString([
                $analysis['summary'] ?? null,
                $raw['summary'] ?? null,
            ]),
            'category' => $this->firstString([
                $message->category,
                $raw['category_slug'] ?? null,
                $raw['category'] ?? null,
            ]),
            'normalized_score' => is_numeric($analysis['relevance_score'] ?? null)
                ? $analysis['relevance_score']
                : null,
            'raw_score' => array_key_exists('relevance_score', $raw)
                ? $this->displayValue($raw['relevance_score'])
                : null,
            'confidence' => $message->confidence !== null
                ? (string) $message->confidence
                : (array_key_exists('confidence', $raw) ? $this->displayValue($raw['confidence']) : null),
            'labels' => $this->labels($analysis['labels'] ?? $raw['labels'] ?? null),
            'provider' => $this->displayOptional($provider, 'provider'),
            'model' => $this->displayOptional($provider, 'model'),
            'latency_ms' => $this->displayOptional($provider, 'latency_ms'),
            'fallback_present' => array_key_exists('fallback_used', $provider),
            'fallback_value' => array_key_exists('fallback_used', $provider)
                ? match ($provider['fallback_used']) {
                    true => 'Sim',
                    false => 'Não',
                    default => $this->displayValue($provider['fallback_used']),
                }
                : null,
            'offers_state' => match (true) {
                ! $offersPresent => 'absent',
                ! $offersValid => 'invalid',
                $offersRaw === [] => 'empty',
                default => 'valid',
            },
            'offers' => $offersValid ? $this->offers($offersRaw) : [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return list<array{fields: array<string, array{present: bool, value: string, multiline: bool}>, urls: list<array{value: string, safe_url: ?string}>}>
     */
    private function offers(array $offers): array
    {
        $presented = [];

        foreach ($offers as $offer) {
            $fields = [];
            $currency = $this->scalarString($offer['currency'] ?? null);

            foreach (self::OFFER_FIELDS as $field) {
                $present = array_key_exists($field, $offer);
                $value = $present ? $offer[$field] : null;
                $fields[$field] = [
                    'present' => $present,
                    'value' => in_array($field, ['price', 'original_price'], true)
                        ? $this->moneyValue($value, $currency, $present)
                        : ($present ? $this->displayValue($value) : 'Ausente'),
                    'multiline' => is_array($value) || is_object($value),
                ];
            }

            $urlsValue = $offer['urls'] ?? null;
            $urls = is_array($urlsValue) && array_is_list($urlsValue) ? $urlsValue : [$urlsValue];
            $presentedUrls = [];
            foreach ($urls as $url) {
                if ($url === null && ! array_key_exists('urls', $offer)) {
                    continue;
                }

                $display = $this->displayValue($url);
                $presentedUrls[] = [
                    'value' => $display,
                    'safe_url' => is_string($url) ? $this->safeUrl($url) : null,
                ];
            }

            $presented[] = ['fields' => $fields, 'urls' => $presentedUrls];
        }

        return $presented;
    }

    private function moneyValue(mixed $value, ?string $currency, bool $present): string
    {
        if (! $present) {
            return 'Ausente';
        }

        if ($value === null) {
            return 'null';
        }

        if (! is_int($value) && ! is_float($value) && ! (is_string($value) && is_numeric($value))) {
            return $this->displayValue($value);
        }

        $prefix = match (strtoupper((string) $currency)) {
            'BRL' => 'R$ ',
            'USD' => 'US$ ',
            'EUR' => '€ ',
            default => $currency !== null && $currency !== '' ? $currency.' ' : '',
        };

        return $prefix.number_format((float) $value, 2, ',', '.');
    }

    private function safeUrl(string $value): ?string
    {
        $url = trim($value);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    /**
     * @return list<string>
     */
    private function labels(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        $labels = [];
        foreach ($value as $label) {
            if (is_string($label) && trim($label) !== '') {
                $labels[] = trim($label);
            }
        }

        return $labels;
    }

    /**
     * @param  list<mixed>  $values
     */
    private function firstString(array $values): ?string
    {
        foreach ($values as $value) {
            $string = $this->stringOrNull($value);
            if ($string !== null) {
                return $string;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function scalarString(mixed $value): ?string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function displayOptional(array $values, string $key): ?string
    {
        return array_key_exists($key, $values) ? $this->displayValue($values[$key]) : null;
    }

    private function displayValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            is_string($value), is_int($value), is_float($value) => (string) $value,
            default => $this->prettyJson($value),
        };
    }

    private function prettyJson(mixed $value): string
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '{}';
    }
}
