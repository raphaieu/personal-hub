<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Liz 1 Aninho — Ingresso</title>
    <style>
        body { margin: 0; padding: 16px; font-family: DejaVu Sans, sans-serif; background: #eaf3f5; }
        .ticket { width: 340px; margin: 0 auto; background: #ffffff; border: 2px dashed #e4759a; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(228, 117, 154, 0.12); }
        .floral-bar { height: 12px; font-size: 0; }
        .floral-bar td { height: 12px; }
        .pink { background: #e4759a; }
        .yellow { background: #f8e178; }
        .green { background: #9ccb9e; }
        .purple { background: #a58bd4; }
        .header { text-align: center; padding: 30px 20px 12px; border-bottom: 1px dashed rgba(228, 117, 154, 0.3); }
        .header h1 { margin: 0; font-size: 42px; color: #e4759a; line-height: 0.8; font-family: DejaVu Serif, serif; }
        .header p { margin: 6px 0 0; font-family: DejaVu Serif, serif; font-size: 14px; font-style: italic; color: #9c6c59; }
        .content { padding: 16px; text-align: center; color: #9c6c59; }
        .phrase { font-family: DejaVu Serif, serif; font-size: 18px; font-style: italic; color: #a58bd4; margin-bottom: 14px; }
        table.meta { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 10px; }
        table.meta td { padding: 10px 4px; border-top: 1px solid rgba(156, 108, 89, 0.1); vertical-align: top; }
        .label { font-size: 8px; text-transform: uppercase; color: #e4759a; letter-spacing: 1px; font-weight: bold; display: block; }
        .value { font-family: DejaVu Serif, serif; font-weight: bold; font-size: 14px; color: #9c6c59; }
        .value-sm { font-size: 11px; font-weight: normal; }
        .qr-wrap { background: #ffffff; padding: 10px; margin: 14px auto; width: 160px; border-radius: 10px; border: 1px solid rgba(228, 117, 154, 0.2); text-align: center; }
        .qr-wrap img { width: 140px; height: 140px; }
        .guest-label { font-size: 8px; text-transform: uppercase; color: #e4759a; letter-spacing: 1px; font-weight: bold; }
        .guest-name { font-family: DejaVu Serif, serif; font-size: 20px; color: #e4759a; margin-top: 4px; }
        .footer { background: rgba(248, 225, 120, 0.4); color: #9c6c59; padding: 10px; text-align: center; font-family: DejaVu Serif, serif; font-size: 11px; font-weight: bold; border-top: 2px dashed rgba(248, 225, 120, 0.8); letter-spacing: 0.5px; }
    </style>
</head>
<body>
    <div class="ticket">
        <table class="floral-bar" width="100%">
            <tr>
                <td class="pink" width="25%"></td>
                <td class="yellow" width="25%"></td>
                <td class="green" width="25%"></td>
                <td class="purple" width="25%"></td>
            </tr>
        </table>
        <div class="header">
            <h1>Liz</h1>
            <p>faz 1 aninho</p>
        </div>
        <div class="content">
            <p class="phrase">&quot;Vem brincar no meu jardim!&quot;</p>
            <table class="meta">
                <tr>
                    <td style="text-align:left;width:50%;">
                        <span class="label">Data</span>
                        <span class="value">14 de Junho</span>
                    </td>
                    <td style="text-align:right;width:50%;">
                        <span class="label">Hora</span>
                        <span class="value">17h</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:center;">
                        <span class="label">Local</span>
                        <span class="value">Castelo Encantado</span>
                        <span class="value value-sm">Vilas do Atlântico</span>
                    </td>
                </tr>
            </table>
            <div class="qr-wrap">
                <img src="{{ $qrDataUri }}" alt="QR">
            </div>
            <span class="guest-label">Convidado Especial</span>
            <div class="guest-name">{{ $guest->name }}</div>
        </div>
        <div class="footer">
            COM AMOR, FAMÍLIA DA LIZ ✨
        </div>
    </div>
</body>
</html>
