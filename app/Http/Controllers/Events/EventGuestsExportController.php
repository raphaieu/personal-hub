<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Guest;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EventGuestsExportController extends Controller
{
    public function __invoke(Event $event): StreamedResponse
    {
        abort_if($event->owner_id !== auth()->id(), 403);

        $timezone = $event->timezone ?? config('app.timezone');
        $filename = Str::slug($event->title).'-convidados.csv';

        return response()->streamDownload(function () use ($event, $timezone): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                '#',
                'ID',
                'Evento',
                'Nome',
                'E-mail',
                'Telefone',
                'Situação',
                'Data de cadastro',
                'Ano de nascimento',
                'Foto',
                'Dados personalizados',
                'Lista/referral',
                'Token referral',
                'Aceite dos termos',
                'Convite enviado em',
                'E-mail confirmado em',
                'Check-in em',
                'Token retorno pagamento',
                'Pagamento expira em',
                'Pagamento status',
                'Pagamento ID',
                'Preference ID',
                'Valor pagamento',
                'Moeda',
                'Pago em',
                'Criado em',
                'Atualizado em',
            ], ';', '"', '');

            Guest::query()
                ->where('event_id', $event->id)
                ->with(['payment', 'referralLink'])
                ->orderBy('created_at')
                ->orderBy('id')
                ->each(function (Guest $guest, int $index) use ($handle, $event, $timezone): void {
                    fputcsv($handle, [
                        $index + 1,
                        $guest->id,
                        $event->title,
                        $guest->name,
                        $guest->email,
                        $guest->phone,
                        $guest->status->value,
                        $this->formatDate($guest->created_at, $timezone),
                        $guest->birth_year,
                        $guest->photo_path,
                        $this->formatJson($guest->custom_data),
                        $guest->referralLink?->name,
                        $guest->referralLink?->token,
                        $this->formatDate($guest->consent_terms_at, $timezone),
                        $this->formatDate($guest->invite_sent_at, $timezone),
                        $this->formatDate($guest->email_confirmed_at, $timezone),
                        $this->formatDate($guest->checked_in_at, $timezone),
                        $guest->payment_return_token,
                        $this->formatDate($guest->payment_expires_at, $timezone),
                        $guest->payment?->status?->value,
                        $guest->payment?->payment_id,
                        $guest->payment?->preference_id,
                        $guest->payment?->amount_cents !== null
                            ? number_format($guest->payment->amount_cents / 100, 2, ',', '.')
                            : null,
                        $guest->payment?->currency,
                        $this->formatDate($guest->payment?->paid_at, $timezone),
                        $this->formatDate($guest->created_at, $timezone),
                        $this->formatDate($guest->updated_at, $timezone),
                    ], ';', '"', '');
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formatDate(?CarbonInterface $value, string $timezone): string
    {
        return $value?->timezone($timezone)->format('d/m/Y H:i:s') ?? '';
    }

    /**
     * @param  mixed  $value
     */
    private function formatJson($value): string
    {
        if ($value === null || $value === []) {
            return '';
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
