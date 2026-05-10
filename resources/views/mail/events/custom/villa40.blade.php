<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VILLA JR. — Convite</title>
</head>
<body style="margin:0;padding:0;background:#333333;font-family:'Segoe UI',Arial,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#333333;padding:20px 8px;">
    <tr>
        <td align="center">
            <table role="presentation" width="350" cellspacing="0" cellpadding="0" style="background:#002B5B;border:4px solid #C59D3F;border-radius:15px;overflow:hidden;max-width:100%;">
                <tr>
                    <td style="height:10px;background:repeating-linear-gradient(45deg,#C59D3F,#C59D3F 10px,transparent 10px,transparent 20px);opacity:0.35;"></td>
                </tr>
                <tr>
                    <td style="text-align:center;padding:20px;border-bottom:2px dashed #C59D3F;">
                        <h1 style="margin:0;font-size:28px;text-transform:uppercase;color:#E2C275;font-weight:bold;">VILLA JR.</h1>
                        <p style="margin:8px 0 0;font-weight:bold;letter-spacing:2px;color:#C59D3F;font-size:14px;">FAZ 40 ANOS</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px;text-align:center;color:#C59D3F;">
                        <p style="margin:0 0 16px;font-style:italic;font-size:13px;color:#F4F4F4;">&quot;Troco gente chata por uma roda de samba&quot;</p>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="width:50%;text-align:left;padding:12px 0;border-top:1px solid rgba(197,157,63,0.35);">
                                    <span style="display:block;font-size:10px;text-transform:uppercase;color:#F4F4F4;">Data</span>
                                    <span style="font-weight:bold;font-size:15px;color:#E2C275;">06 / Junho</span>
                                </td>
                                <td style="width:50%;text-align:right;padding:12px 0;border-top:1px solid rgba(197,157,63,0.35);">
                                    <span style="display:block;font-size:10px;text-transform:uppercase;color:#F4F4F4;">Hora</span>
                                    <span style="font-weight:bold;font-size:15px;color:#E2C275;">13:00h</span>
                                </td>
                            </tr>
                        </table>
                        <div style="margin:16px 0;padding-top:12px;border-top:1px solid rgba(197,157,63,0.35);">
                            <span style="display:block;font-size:10px;text-transform:uppercase;color:#F4F4F4;">Local</span>
                            <span style="font-weight:bold;font-size:15px;color:#E2C275;">Kasa Azul, Pedra do Sal</span>
                        </div>
                        <div style="background:#F4F4F4;padding:12px;margin:16px auto;width:150px;border-radius:10px;text-align:center;">
                            <img src="{{ $qrDataUri }}" width="150" height="150" alt="QR Code check-in" style="display:block;margin:0 auto;">
                        </div>
                        <div style="margin-top:8px;">
                            <span style="display:block;font-size:10px;text-transform:uppercase;color:#F4F4F4;">Convidado</span>
                            <span style="font-weight:bold;font-size:16px;color:#F4F4F4;">{{ $guest->name }}</span>
                        </div>
                        <p style="margin:16px 0 0;font-size:10px;color:#E2C275;word-break:break-all;">{{ $checkInUrl }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="background:#C59D3F;color:#002B5B;padding:10px;text-align:center;font-size:11px;font-weight:bold;">
                        DRESS CODE: ALL BLUE | TRAGA SEU COPO TÉRMICO
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
