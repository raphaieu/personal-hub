<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liz 1 Aninho — Convite</title>
</head>
<body style="margin:0;padding:0;background:#eaf3f5;font-family:Outfit,'Segoe UI',Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eaf3f5;padding:20px 8px;">
    <tr>
        <td align="center">
            <table role="presentation" width="350" cellspacing="0" cellpadding="0" style="background:#ffffff;border:2px dashed #e4759a;border-radius:24px;overflow:hidden;max-width:100%;box-shadow:0 10px 30px rgba(228,117,154,0.15);">
                {{-- Colorful floral top border --}}
                <tr>
                    <td style="height:12px;font-size:0;line-height:0;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="width:25%;height:12px;background:#e4759a;"></td>
                                <td style="width:25%;height:12px;background:#f8e178;"></td>
                                <td style="width:25%;height:12px;background:#9ccb9e;"></td>
                                <td style="width:25%;height:12px;background:#a58bd4;"></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                {{-- Header --}}
                <tr>
                    <td style="text-align:center;padding:35px 20px 15px;border-bottom:1px dashed rgba(228,117,154,0.3);">
                        <h1 style="margin:0;font-family:'Dancing Script',Georgia,serif;font-size:56px;color:#e4759a;line-height:0.8;text-shadow:1px 1px 0 #fff;font-weight:400;">Liz</h1>
                        <p style="margin:5px 0 0;font-family:'Playfair Display',Georgia,serif;font-size:18px;font-style:italic;color:#9c6c59;">faz 1 aninho</p>
                    </td>
                </tr>
                {{-- Content --}}
                <tr>
                    <td style="padding:20px;text-align:center;color:#9c6c59;">
                        <p style="margin:0 0 20px;font-family:'Dancing Script',Georgia,serif;font-size:28px;color:#a58bd4;">&quot;Vem brincar no meu jardim!&quot;</p>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="width:50%;text-align:left;padding:15px 0;border-top:1px solid rgba(156,108,89,0.1);">
                                    <span style="display:block;font-size:11px;text-transform:uppercase;color:#e4759a;letter-spacing:1px;font-weight:600;">Data</span>
                                    <span style="font-family:'Playfair Display',Georgia,serif;font-weight:bold;font-size:18px;color:#9c6c59;">14 de Junho</span>
                                </td>
                                <td style="width:50%;text-align:right;padding:15px 0;border-top:1px solid rgba(156,108,89,0.1);">
                                    <span style="display:block;font-size:11px;text-transform:uppercase;color:#e4759a;letter-spacing:1px;font-weight:600;">Hora</span>
                                    <span style="font-family:'Playfair Display',Georgia,serif;font-weight:bold;font-size:18px;color:#9c6c59;">17h</span>
                                </td>
                            </tr>
                        </table>
                        <div style="margin:16px 0;padding-top:12px;border-top:1px solid rgba(156,108,89,0.1);">
                            <span style="display:block;font-size:11px;text-transform:uppercase;color:#e4759a;letter-spacing:1px;font-weight:600;">Local</span>
                            <span style="font-family:'Playfair Display',Georgia,serif;font-weight:bold;font-size:18px;color:#9c6c59;">Castelo Encantado</span>
                            <span style="display:block;font-size:13px;margin-top:4px;opacity:0.8;color:#9c6c59;">Vilas do Atlântico</span>
                        </div>
                        <div style="background:#ffffff;padding:12px;margin:20px auto;width:150px;border-radius:12px;border:1px solid rgba(228,117,154,0.2);text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.05);">
                            <img src="{{ $qrUrl }}" width="140" height="140" alt="QR Code check-in" style="display:block;margin:0 auto;border-radius:8px;">
                        </div>
                        <div style="margin-top:12px;border-top:none;">
                            <span style="display:block;font-size:11px;text-transform:uppercase;color:#e4759a;letter-spacing:1px;font-weight:600;">Convidado Especial</span>
                            <span style="font-family:'Dancing Script',Georgia,serif;font-size:28px;color:#e4759a;margin-top:4px;display:block;">{{ $guest->name }}</span>
                        </div>
                    </td>
                </tr>
                {{-- Footer --}}
                <tr>
                    <td style="background:rgba(248,225,120,0.3);color:#9c6c59;padding:15px;text-align:center;font-family:'Playfair Display',Georgia,serif;font-size:14px;font-weight:600;border-top:2px dashed rgba(248,225,120,0.8);letter-spacing:0.5px;">
                        COM AMOR, FAMÍLIA DA LIZ ✨
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
