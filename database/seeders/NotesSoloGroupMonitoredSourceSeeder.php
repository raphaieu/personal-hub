<?php

namespace Database\Seeders;

use App\Models\MonitoredSource;
use Illuminate\Database\Seeder;

/**
 * Grupo WhatsApp “só eu” para notas e mídia (workaround Evolution no 1:1).
 * WebhookRouterService despacha ProcessPersonalWhatsAppMessage para este JID (config WHATSAPP_NOTAS_GRUPO_JID).
 */
class NotesSoloGroupMonitoredSourceSeeder extends Seeder
{
    public function run(): void
    {
        $jid = trim((string) env('WHATSAPP_NOTAS_GRUPO_JID', '120363424213917118@g.us'));

        if ($jid === '') {
            $this->command?->warn('NotesSoloGroupMonitoredSourceSeeder: WHATSAPP_NOTAS_GRUPO_JID vazio — ignorado.');

            return;
        }

        MonitoredSource::query()->updateOrCreate(
            ['identifier' => $jid],
            [
                'kind' => 'group',
                'label' => env('WHATSAPP_NOTAS_GRUPO_LABEL', 'Raphael Martins'),
                'is_active' => true,
                'notes' => 'Grupo fixado só com você — notas e mídia (Evolution costuma não webhookar mídia no chat consigo 1:1).',
                'media_storage_prefix' => 'whatsapp/notas-solo',
            ],
        );

        $this->command?->info("NotesSoloGroupMonitoredSourceSeeder: fonte group {$jid} garantida.");
    }
}
