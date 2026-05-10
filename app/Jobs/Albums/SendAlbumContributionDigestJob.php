<?php

namespace App\Jobs\Albums;

use App\Mail\AlbumContributionDigestMail;
use App\Models\Album;
use App\Services\Albums\AlbumContributionDigestService;
use App\Services\EvolutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SendAlbumContributionDigestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $albumId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(
        AlbumContributionDigestService $digestService,
        EvolutionService $evolution,
    ): void {
        $schedKey = 'albums:contrib:digest:sched:'.$this->albumId;
        $lines = $digestService->pullBuffer($this->albumId);
        if ($lines === []) {
            Cache::forget($schedKey);

            return;
        }

        try {
            $album = Album::query()->find($this->albumId);
            if ($album === null) {
                Log::warning('albums.contribution.digest_album_missing', ['album_id' => $this->albumId]);

                return;
            }

            $to = config('services.albums.contribution_notify_email');
            if (! is_string($to) || $to === '') {
                $to = config('mail.from.address');
            }

            if (is_string($to) && $to !== '') {
                try {
                    Mail::to($to)->send(new AlbumContributionDigestMail($album, $lines));
                } catch (Throwable $e) {
                    Log::error('albums.contribution.digest_mail_failed', [
                        'album_id' => $this->albumId,
                        'message' => $e->getMessage(),
                    ]);
                }
            } else {
                Log::warning('albums.contribution.digest_no_admin_email', ['album_id' => $this->albumId]);
            }

            $jid = config('services.albums.contributions_whatsapp_jid');
            if (! is_string($jid) || $jid === '') {
                $jid = config('services.whatsapp.utilities_home_group_jid');
            }

            if (is_string($jid) && $jid !== '' && $evolution->isConfigured()) {
                try {
                    $evolution->sendText($jid, $this->formatWhatsappSummary($album, $lines));
                } catch (Throwable $e) {
                    Log::warning('albums.contribution.digest_whatsapp_failed', [
                        'album_id' => $this->albumId,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            Cache::forget($schedKey);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function formatWhatsappSummary(Album $album, array $lines): string
    {
        $n = count($lines);
        $title = $album->title;
        $header = sprintf("Contribuições no álbum «%s»\n%d novo(s) arquivo(s) na última janela:\n", $title, $n);
        $body = '';
        foreach ($lines as $line) {
            $email = is_string($line['contributor_email'] ?? null) ? $line['contributor_email'] : '?';
            $name = is_string($line['filename'] ?? null) ? $line['filename'] : '?';
            $type = is_string($line['type'] ?? null) ? $line['type'] : '?';
            $body .= sprintf("- %s (%s) — %s\n", $name, $type, $email);
        }

        return $header.$body;
    }
}
