<!doctype html><html><body style="background:#050505;color:#f5f5f5;font-family:Arial,sans-serif;padding:24px">
<div style="max-width:620px;margin:auto;background:#101010;border:1px solid #d6ab3a;border-radius:14px;padding:28px">
<h1 style="color:#f4c94d">TradeX Support</h1>
<p>Your support request has an update.</p>
@if(isset($payload['reference']))<p><strong>Reference:</strong> {{ $payload['reference'] }}</p>@endif
@if(isset($payload['subject']))<p><strong>Subject:</strong> {{ $payload['subject'] }}</p>@endif
@if(isset($payload['body']))<p>{{ $payload['body'] }}</p>@endif
@if(isset($payload['status']))<p><strong>Status:</strong> {{ str_replace('_', ' ', $payload['status']) }}</p>@endif
@if(isset($payload['active']))<p><strong>Daily summary {{ $payload['date'] ?? '' }}:</strong> {{ $payload['active'] }} active, {{ $payload['unassigned'] ?? 0 }} unassigned, {{ $payload['overdue'] ?? 0 }} overdue.</p>@endif
<p>Open the authenticated TradeX app to view and respond. We will never request passwords, OTPs, or broker credentials in support messages.</p>
</div></body></html>
