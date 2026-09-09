<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background:#0b1220;color:#e5e7eb;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0b1220;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#111827;border:1px solid #25324a;border-radius:14px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 30px 18px;">
                        <div style="font-size:12px;letter-spacing:1.5px;color:#94a3b8;">TRADEX ADMINISTRATION</div>
                        <h1 style="margin:10px 0 0;font-size:25px;color:#f8fafc;">{{ $subjectLine }}</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 30px 28px;line-height:1.65;color:#cbd5e1;">
                        <p>Hello {{ $adminName }},</p>

                        @if($eventType === 'ADMIN_INVITATION')
                            <p>Your TradeX administrator account has been created.</p>
                            @if(!empty($context['roles']))
                                <p><strong>Assigned roles:</strong> {{ implode(', ', $context['roles']) }}</p>
                            @endif
                            <p>Your password is never included in email. Use the admin login page with the credentials provided through the approved secure channel.</p>
                        @elseif($eventType === 'ROLES_UPDATED')
                            <p>Your TradeX administrator role assignments were updated.</p>
                            @if(!empty($context['roles']))
                                <p><strong>Current roles:</strong> {{ implode(', ', $context['roles']) }}</p>
                            @endif
                        @elseif($eventType === 'ACCOUNT_DISABLED')
                            <p>Your TradeX administrator account has been disabled. New admin logins are blocked.</p>
                        @elseif($eventType === 'ACCOUNT_ENABLED')
                            <p>Your TradeX administrator account has been enabled.</p>
                        @elseif($eventType === 'ACCOUNT_DELETED')
                            <p>Your TradeX administrator account has been removed from active administration access.</p>
                        @elseif($eventType === 'PROFILE_UPDATED')
                            <p>Your TradeX administrator profile details were updated.</p>
                        @else
                            <p>A security-related change was made to your TradeX administrator account.</p>
                        @endif

                        @if(!empty($context['reason']))
                            <p><strong>Reason:</strong> {{ $context['reason'] }}</p>
                        @endif

                        @if($eventType !== 'ACCOUNT_DELETED' && $eventType !== 'ACCOUNT_DISABLED')
                            <p style="margin:26px 0;">
                                <a href="{{ $loginUrl }}" style="display:inline-block;padding:12px 18px;border-radius:8px;background:#f59e0b;color:#111827;text-decoration:none;font-weight:700;">Open TradeX Admin</a>
                            </p>
                        @endif

                        <p style="font-size:13px;color:#94a3b8;">If you did not expect this change, contact the TradeX owner/security administrator immediately.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
