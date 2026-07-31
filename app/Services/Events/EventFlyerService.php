<?php

namespace App\Services\Events;

use App\Contracts\AiVisionServiceInterface;
use App\Enums\AiTask;
use App\Enums\Events\EventStatus;
use App\Jobs\Events\ProcessEventFlyerJob;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class EventFlyerService
{
    private const STORAGE_DISK = 's3';

    private const OG_WIDTH = 1200;

    private const OG_HEIGHT = 630;

    public function __construct(
        private readonly AiVisionServiceInterface $aiVision,
        private readonly EventManagementService $managementService,
    ) {}

    /**
     * Cria evento em rascunho a partir do flyer e enfileira o processamento IA.
     * A og:image é sempre gerada; a extração de dados/tema é best-effort.
     *
     * @throws ValidationException — limite diário de jobs IA por conta
     */
    public function createDraftFromFlyer(User $owner, UploadedFile $flyer): Event
    {
        $limit = (int) config('events.limits.max_flyer_jobs_per_day', 5);
        $usedToday = Event::query()
            ->where('owner_id', $owner->id)
            ->whereNotNull('flyer_path')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($usedToday >= $limit) {
            throw ValidationException::withMessages([
                'flyer' => ["Limite de {$limit} análises de flyer por dia atingido. Tente amanhã ou crie manualmente."],
            ]);
        }

        $event = Event::query()->create([
            'owner_id' => $owner->id,
            'slug' => 'evento-'.Str::lower(Str::random(8)),
            'status' => EventStatus::Draft,
            'title' => 'Novo evento',
            'timezone' => 'America/Sao_Paulo',
            'registration_open' => false,
            'ai_status' => 'pending',
        ]);

        $path = $flyer->store("events/flyers/{$event->id}", self::STORAGE_DISK);
        $event->forceFill(['flyer_path' => $path])->save();

        ProcessEventFlyerJob::dispatch($event->id);

        return $event->refresh();
    }

    /**
     * Processamento pesado (fila `ai`): og:image sempre; extração best-effort.
     * Nunca lança exceção — o estado final fica em ai_status (ready|failed).
     */
    public function process(Event $event): void
    {
        if ($event->flyer_path === null) {
            $event->forceFill(['ai_status' => 'failed'])->save();

            return;
        }

        $event->forceFill(['ai_status' => 'processing'])->save();

        try {
            $this->generateOgImage($event);
        } catch (Throwable $exception) {
            Log::warning('events.flyer_og_failed', [
                'event_id' => $event->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $proposal = $this->extractProposal($event);
            $this->applyProposal($event, $proposal);
            $event->forceFill(['ai_status' => 'ready'])->save();
        } catch (Throwable $exception) {
            // Fallback da spec §5: IA indisponível → usuário segue pelo fluxo manual
            // (og:image já gerada acima permanece).
            Log::warning('events.flyer_ai_failed', [
                'event_id' => $event->id,
                'error' => $exception->getMessage(),
            ]);
            $event->forceFill(['ai_status' => 'failed'])->save();
        }
    }

    // ---------------------------------------------------------------------------
    // Extração IA
    // ---------------------------------------------------------------------------

    /**
     * @return array<string, mixed> — proposta validada (dados + tema)
     */
    private function extractProposal(Event $event): array
    {
        $binary = Storage::disk(self::STORAGE_DISK)->get($event->flyer_path);
        if ($binary === null) {
            throw new \RuntimeException('Flyer não encontrado no storage.');
        }

        // Inferir mime type da extensão do arquivo (evita falha do Storage::mimeType no S3/MinIO)
        $ext = strtolower(pathinfo((string) $event->flyer_path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        $result = $this->aiVision->completeWithVision(
            $this->extractionPrompt(),
            AiTask::EventFlyerExtraction,
            [['data' => base64_encode($binary), 'mime' => $mime]],
            null,
            true,
        );

        if (! $result->success) {
            throw new \RuntimeException('IA indisponível: '.($result->errorDetail ?? $result->errorType ?? 'desconhecido'));
        }

        $decoded = json_decode($this->stripJsonFences($result->text), true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Resposta da IA não é um JSON válido.');
        }

        return $this->validateProposal($decoded);
    }

    private function extractionPrompt(): string
    {
        $fonts = implode('|', config('events.allowed_fonts', []));
        $icons = implode('|', config('events.allowed_rule_icons', []));

        return <<<PROMPT
        Analise este flyer de evento e extraia os dados em JSON com esta estrutura exata:
        {
          "title": string|null,          // nome do evento (curto, sem emojis)
          "starts_at": string|null,      // início em "YYYY-MM-DD HH:mm" (24h; se o flyer não tiver ano, use o próximo ano corrente)
          "date_label": string|null,     // data por extenso curta (ex: "15 de Agosto")
          "date_sub": string|null,       // complemento da data (ex: "Sábado")
          "time_label": string|null,     // horário (ex: "20h")
          "location_name": string|null,  // nome do local
          "location_sub": string|null,   // bairro/cidade/endereço curto
          "headline": string|null,       // chamada principal para o topo da página (curta, impactante)
          "subheadline": string|null,    // frase de apoio (até 140 caracteres)
          "badge": string|null,          // selo curto em caixa alta (ex: "★ VILLA 40 ★") ou null
          "rules": [                     // até 6 regras/avisos visíveis ou claramente implícitos no flyer
            {"icon": "{$icons}", "title": string, "text": string|null}
          ],
          "palette": {                   // cores dominantes do flyer, hex #RRGGBB
            "primary": string,           // cor de destaque principal
            "secondary": string,         // destaque secundário (harmoniza com primary)
            "background": string,        // fundo da página (derivado do flyer)
            "surface": string,           // cards (um pouco mais claro/escuro que background)
            "text": string               // cor de texto com contraste sobre background
          },
          "fonts": {
            "heading": "{$fonts}",       // escolha SOMENTE entre estas, combinando com o estilo do flyer
            "body": "{$fonts}"
          },
          "mode": "dark"|"light"         // conforme o fundo dominante do flyer
        }
        Responda APENAS o JSON, sem markdown e sem texto extra.
        PROMPT;
    }

    private function stripJsonFences(string $text): string
    {
        $text = trim($text);
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        }

        return trim($text);
    }

    /**
     * Valida e normaliza a proposta da IA (prompt injection no flyer é mitigado
     * pelo parsing estrito + allowlists + ranges).
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function validateProposal(array $raw): array
    {
        $defaultTheme = config('events.default_theme');
        $hex = fn (mixed $value): ?string => is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : null;
        $text = fn (mixed $value, int $max): ?string => is_string($value) && trim($value) !== ''
            ? mb_substr(trim($value), 0, $max)
            : null;

        $startsAt = null;
        if (is_string($raw['starts_at'] ?? null) && trim((string) $raw['starts_at']) !== '') {
            try {
                $startsAt = Carbon::parse((string) $raw['starts_at'], 'America/Sao_Paulo');
            } catch (Throwable) {
                $startsAt = null;
            }
        }

        // Flyers sem ano costumam ser do próximo ano — sobe até ficar no futuro.
        $yearAttempts = 0;
        while ($startsAt !== null && $startsAt->isPast() && $yearAttempts < 3) {
            $startsAt = $startsAt->addYear();
            $yearAttempts++;
        }

        $allowedFonts = config('events.allowed_fonts', []);
        $font = fn (mixed $value, string $fallback): string => is_string($value) && in_array($value, $allowedFonts, true)
            ? $value
            : $fallback;

        $allowedIcons = config('events.allowed_rule_icons', []);
        $rules = [];
        foreach ((array) ($raw['rules'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = $text($item['title'] ?? null, 80);
            if ($title === null) {
                continue;
            }
            $rules[] = [
                'icon' => in_array($item['icon'] ?? '', $allowedIcons, true) ? $item['icon'] : 'star',
                'title' => $title,
                'text' => $text($item['text'] ?? null, 200),
            ];
            if (count($rules) >= 6) {
                break;
            }
        }

        $palette = (array) ($raw['palette'] ?? []);

        return [
            'title' => $text($raw['title'] ?? null, 120),
            'starts_at' => $startsAt,
            'date_label' => $text($raw['date_label'] ?? null, 60),
            'date_sub' => $text($raw['date_sub'] ?? null, 60),
            'time_label' => $text($raw['time_label'] ?? null, 60),
            'location_name' => $text($raw['location_name'] ?? null, 120),
            'location_sub' => $text($raw['location_sub'] ?? null, 160),
            'headline' => $text($raw['headline'] ?? null, 120),
            'subheadline' => $text($raw['subheadline'] ?? null, 300),
            'badge' => $text($raw['badge'] ?? null, 60),
            'rules' => $rules,
            'palette' => [
                'primary' => $hex($palette['primary'] ?? null) ?? $defaultTheme['colors']['primary'],
                'secondary' => $hex($palette['secondary'] ?? null) ?? $defaultTheme['colors']['secondary'],
                'background' => $hex($palette['background'] ?? null) ?? $defaultTheme['colors']['background'],
                'surface' => $hex($palette['surface'] ?? null) ?? $defaultTheme['colors']['surface'],
                'text' => $hex($palette['text'] ?? null) ?? $defaultTheme['colors']['text'],
            ],
            'fonts' => [
                'heading' => $font($raw['fonts']['heading'] ?? null, $defaultTheme['fonts']['heading']),
                'body' => $font($raw['fonts']['body'] ?? null, $defaultTheme['fonts']['body']),
            ],
            'mode' => in_array($raw['mode'] ?? '', ['dark', 'light'], true) ? $raw['mode'] : 'dark',
        ];
    }

    /**
     * Aplica a proposta validada ao evento (tema + conteúdo + dados nativos + slug).
     *
     * @param  array<string, mixed>  $proposal
     */
    private function applyProposal(Event $event, array $proposal): void
    {
        $defaultTheme = config('events.default_theme');

        $theme = [
            'colors' => array_merge($proposal['palette'], [
                'textMuted' => $defaultTheme['colors']['textMuted'],
            ]),
            'fonts' => $proposal['fonts'],
            'mode' => $proposal['mode'],
            'borderRadius' => $defaultTheme['borderRadius'],
            'customCss' => null,
        ];

        $content = [
            'hero' => [
                'enabled' => true,
                'headline' => $proposal['headline'] ?? $proposal['title'] ?? $event->title,
                'subheadline' => $proposal['subheadline'],
                'badge' => $proposal['badge'],
            ],
            'info' => [
                'enabled' => true,
                'dateLabel' => $proposal['date_label'],
                'dateSub' => $proposal['date_sub'],
                'timeLabel' => $proposal['time_label'],
                'timeSub' => null,
                'locationName' => $proposal['location_name'],
                'locationSub' => $proposal['location_sub'],
                'mapUrl' => null,
            ],
            'countdown' => [
                'enabled' => $proposal['starts_at'] !== null,
                'title' => null,
            ],
            'rules' => [
                'enabled' => $proposal['rules'] !== [],
                'title' => null,
                'items' => $proposal['rules'],
            ],
            'form' => ['enabled' => true, 'title' => null, 'submitLabel' => null],
        ];

        $attributes = [
            'title' => $proposal['title'] ?? $event->title,
            'starts_at' => $proposal['starts_at'],
            'theme_json' => $theme,
            'content_json' => $content,
        ];

        // Slug temporário (evento-xxxxxxxx) é substituído pelo slug do título extraído.
        if (str_starts_with($event->slug, 'evento-')) {
            $attributes['slug'] = $this->managementService->availableSlug($attributes['title'], $event);
        }

        $event->forceFill($attributes)->save();
    }

    // ---------------------------------------------------------------------------
    // OG image (derivado do flyer, crop central 1200×630 WebP)
    // ---------------------------------------------------------------------------

    private function generateOgImage(Event $event): void
    {
        $binary = Storage::disk(self::STORAGE_DISK)->get($event->flyer_path);
        if ($binary === null) {
            throw new \RuntimeException('Flyer não encontrado no storage.');
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new \RuntimeException('gd_read_failed');
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $targetRatio = self::OG_WIDTH / self::OG_HEIGHT;
        $srcRatio = $srcW / $srcH;

        if ($srcRatio > $targetRatio) {
            $cropW = (int) ($srcH * $targetRatio);
            $cropH = $srcH;
            $srcX = (int) (($srcW - $cropW) / 2);
            $srcY = 0;
        } else {
            $cropW = $srcW;
            $cropH = (int) ($srcW / $targetRatio);
            $srcX = 0;
            $srcY = (int) (($srcH - $cropH) / 2);
        }

        $target = imagecreatetruecolor(self::OG_WIDTH, self::OG_HEIGHT);
        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, self::OG_WIDTH, self::OG_HEIGHT, $cropW, $cropH);

        ob_start();
        imagewebp($target, null, 82);
        $webp = (string) ob_get_clean();

        $path = "events/flyers/{$event->id}/og.webp";
        Storage::disk(self::STORAGE_DISK)->put($path, $webp, 'public');

        $event->forceFill(['og_image_path' => $path])->save();
    }
}
