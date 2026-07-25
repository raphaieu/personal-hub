<?php

namespace App\Services\Events;

use Illuminate\Validation\ValidationException;

final class ThemeService
{
    /**
     * Sanitiza o CSS customizado do organizador (escape hatch avançado).
     * Rejeita padrões que permitiriam defacement fora da landing, XSS ou
     * exfiltração de dados via url() externa.
     *
     * @throws ValidationException
     */
    public function sanitizeCustomCss(?string $css): ?string
    {
        if ($css === null || trim($css) === '') {
            return null;
        }

        $css = trim($css);

        if (str_contains($css, '<') || str_contains($css, '>')) {
            throw ValidationException::withMessages([
                'theme.customCss' => ['O CSS não pode conter "<" ou ">".'],
            ]);
        }

        $blockedPatterns = [
            '@import', 'javascript:', 'expression(', 'behavior:', '-moz-binding',
            'position:fixed', 'position: fixed', 'position:sticky', 'position: sticky',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (stripos($css, $pattern) !== false) {
                throw ValidationException::withMessages([
                    'theme.customCss' => ['O CSS contém um padrão não permitido: '.$pattern.'.'],
                ]);
            }
        }

        // Seletores globais vazariam para fora do escopo da landing.
        if (preg_match('/(^|[},])\s*(html|body|\*)\s*[{,]/i', $css) === 1) {
            throw ValidationException::withMessages([
                'theme.customCss' => ['Seletores globais (html, body, *) não são permitidos.'],
            ]);
        }

        // url() externa só é permitida para o Google Fonts; demais são neutralizadas.
        $sanitized = preg_replace_callback(
            '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i',
            fn (array $matches): string => str_starts_with($matches[1], 'https://fonts.googleapis.com')
                ? $matches[0]
                : 'url(about:blank)',
            $css,
        );

        return mb_substr((string) $sanitized, 0, 5120);
    }
}
