<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ingresso — {{ $guest->event->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 24px; }
        .box { border: 2px solid #4f46e5; border-radius: 8px; padding: 16px; max-width: 420px; margin: 0 auto; text-align: center; }
        h1 { font-size: 18px; margin: 0 0 8px; color: #312e81; }
        .muted { color: #6b7280; font-size: 10px; margin-top: 12px; }
        img.qr { width: 180px; height: 180px; display: block; margin: 12px auto; }
        .row { margin: 8px 0; text-align: left; }
        .label { font-size: 9px; text-transform: uppercase; color: #6b7280; }
    </style>
</head>
<body>
    <div class="box">
        <h1>{{ $guest->event->title }}</h1>
        <div class="row">
            <span class="label">Convidado</span><br>
            <strong>{{ $guest->name }}</strong>
        </div>
        @if($guest->event->starts_at)
            <div class="row">
                <span class="label">Quando</span><br>
                {{ $guest->event->starts_at->timezone($guest->event->timezone ?? 'America/Sao_Paulo')->locale('pt_BR')->isoFormat('LLLL') }}
            </div>
        @endif
        <img class="qr" src="{{ $qrDataUri }}" alt="QR Code">
        <p class="muted">Apresente este QR na portaria. URL de check-in embutida.</p>
    </div>
</body>
</html>
