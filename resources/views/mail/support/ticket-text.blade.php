TradeX Support

Your support request has an update.
@isset($payload['reference'])Reference: {{ $payload['reference'] }}@endisset
@isset($payload['subject'])Subject: {{ $payload['subject'] }}@endisset
@isset($payload['body']){{ $payload['body'] }}@endisset
@isset($payload['active'])Daily summary {{ $payload['date'] ?? '' }}: {{ $payload['active'] }} active, {{ $payload['unassigned'] ?? 0 }} unassigned, {{ $payload['overdue'] ?? 0 }} overdue.@endisset

Open the authenticated TradeX app to view and respond. Never share passwords, OTPs, or broker credentials.
