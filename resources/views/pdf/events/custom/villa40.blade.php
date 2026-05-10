<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>VILLA JR. — Ingresso</title>
    <style>
        body { margin: 0; padding: 16px; font-family: DejaVu Sans, sans-serif; background: #333; color: #C59D3F; }
        .ticket { width: 340px; margin: 0 auto; background: #002B5B; border: 4px solid #C59D3F; border-radius: 12px; overflow: hidden; }
        .header { text-align: center; padding: 16px; border-bottom: 2px dashed #C59D3F; }
        .header h1 { margin: 0; font-size: 22px; text-transform: uppercase; color: #E2C275; }
        .header p { margin: 6px 0 0; font-weight: bold; letter-spacing: 2px; font-size: 11px; }
        .content { padding: 14px; text-align: center; font-size: 10px; }
        .phrase { font-style: italic; color: #F4F4F4; margin-bottom: 12px; }
        table.meta { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 10px; }
        table.meta td { padding: 8px 4px; border-top: 1px solid rgba(197,157,63,0.35); vertical-align: top; }
        .label { font-size: 8px; text-transform: uppercase; color: #F4F4F4; display: block; }
        .qr-wrap { background: #F4F4F4; padding: 10px; margin: 12px auto; width: 160px; border-radius: 8px; text-align: center; }
        .qr-wrap img { width: 140px; height: 140px; }
        .guest-name { font-size: 13px; font-weight: bold; color: #F4F4F4; margin-top: 8px; }
        .footer { background: #C59D3F; color: #002B5B; padding: 8px; text-align: center; font-size: 9px; font-weight: bold; }
        .url { font-size: 7px; color: #E2C275; word-break: break-all; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="header">
            <h1>VILLA JR.</h1>
            <p>FAZ 40 ANOS</p>
        </div>
        <div class="content">
            <p class="phrase">&quot;Troco gente chata por uma roda de samba&quot;</p>
            <table class="meta">
                <tr>
                    <td style="text-align:left;width:50%;">
                        <span class="label">Data</span>
                        <strong style="color:#E2C275;">06 / Junho</strong>
                    </td>
                    <td style="text-align:right;width:50%;">
                        <span class="label">Hora</span>
                        <strong style="color:#E2C275;">13:00h</strong>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:center;">
                        <span class="label">Local</span>
                        <strong style="color:#E2C275;">Kasa Azul, Pedra do Sal</strong>
                    </td>
                </tr>
            </table>
            <div class="qr-wrap">
                <img src="{{ $qrDataUri }}" alt="QR">
            </div>
            <span class="label">Convidado</span>
            <div class="guest-name">{{ $guest->name }}</div>
            <p class="url">{{ $checkInUrl }}</p>
        </div>
        <div class="footer">
            DRESS CODE: ALL BLUE | TRAGA SEU COPO TÉRMICO
        </div>
    </div>
</body>
</html>
