<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#080b0a;font-family:Arial,Helvetica,sans-serif;color:#edf2ef;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#080b0a;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#101512;border:1px solid #80651c;border-radius:18px;overflow:hidden;">
                    <tr>
                        <td align="center" style="padding:30px 32px 20px;background:linear-gradient(135deg,#151a16 0%,#080b0a 100%);border-bottom:1px solid #5e4c19;">
                            <img src="{{ $logoUrl }}" alt="TradeX OS" width="138" style="display:block;width:138px;max-width:100%;height:auto;border:0;">
                            <p style="margin:18px 0 0;color:#d7b33d;font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;">Discipline creates freedom</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 32px 12px;">
                            <p style="margin:0 0 10px;color:#d7b33d;font-size:12px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;">Security verification</p>
                            <h1 style="margin:0;color:#ffffff;font-size:25px;line-height:1.25;font-weight:800;">{{ $title }}</h1>
                            <p style="margin:16px 0 0;color:#bdc8c1;font-size:15px;line-height:1.6;">{{ $intro }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#090d0b;border:1px solid #b89427;border-radius:12px;">
                                <tr>
                                    <td align="center" style="padding:12px 16px 5px;color:#aebbb3;font-size:11px;font-weight:700;letter-spacing:1.1px;text-transform:uppercase;">Your verification code</td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:7px 16px 18px;color:#ffd65a;font-size:28px;line-height:1.25;font-weight:800;letter-spacing:3px;">{{ $code }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 32px 28px;">
                            <p style="margin:0;color:#bdc8c1;font-size:13px;line-height:1.6;">This code expires in 10 minutes. Do not share it with anyone. TradeX will never ask you for this code.</p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 24px;background:#090d0b;border-top:1px solid #29322c;color:#87948b;font-size:11px;line-height:1.6;">
                            <strong style="color:#d7b33d;">Billionaire Trading Institute</strong><br>
                            Registration: {{ $registrationNumber }} &nbsp;|&nbsp; Contact: {{ $contactPhone }}<br>
                            © {{ now()->year }} TradeX OS. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
