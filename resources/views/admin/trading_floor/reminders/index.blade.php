@extends('admin.layouts.app')

@section('title', 'Trading Floor Reminders | TradeX Administration')
@section('page-title', 'Trading Floor Reminders')

@section('content')
@php
    $admin = auth('admin')->user();
    $canGenerate = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.settings.manage');
    $maskRecipient = static function (?string $value): string {
        if (! filled($value)) return 'IN-APP ONLY';
        if (str_contains($value, '@')) {
            [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');
            return substr($local, 0, 2).'***@'.$domain;
        }
        $length = strlen($value);
        return $length <= 4 ? '****' : str_repeat('*', max(0, $length - 4)).substr($value, -4);
    };
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-30/31 · NOTIFICATIONS & REMINDERS</div>
        <h1>Reminder <span>Queue</span></h1>
        <p>Prepare commission due-date, overdue, payment-received and membership renewal reminders for WhatsApp, email and in-app delivery.</p>
    </div>
    <div>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.index') }}">Memberships</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.reports.index') }}">Reports</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.index') }}">Floor Dashboard</a>
        @if($canGenerate)
            <form method="POST" action="{{ route('admin.trading_floor.reminders.generate') }}" style="display:inline;">@csrf<button class="btn btn-primary" type="submit">Refresh Queue</button></form>
        @endif
    </div>
</div>

<div class="dashboard-grid" style="margin-top:18px;">
    <div class="stat-card"><div class="stat-card__label">Pending</div><div class="stat-card__value">{{ number_format($stats['pending']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Membership Renewals</div><div class="stat-card__value">{{ number_format($stats['membership_renewals']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">WhatsApp Ready</div><div class="stat-card__value">{{ number_format($stats['whatsapp']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Email Ready</div><div class="stat-card__value">{{ number_format($stats['email']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">In-App</div><div class="stat-card__value">{{ number_format($stats['in_app']) }}</div></div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-body">
        <form method="GET" action="{{ route('admin.trading_floor.reminders.index') }}" style="display:grid;grid-template-columns:220px 220px auto auto;gap:10px;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Status</label><select name="status"><option value="">All Statuses</option>@foreach(['PENDING','SENT','FAILED','SKIPPED'] as $option)<option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>@endforeach</select></div>
            <div class="form-group" style="margin:0;"><label>Channel</label><select name="channel"><option value="">All Channels</option>@foreach(['IN_APP','WHATSAPP','EMAIL'] as $option)<option value="{{ $option }}" @selected($channel === $option)>{{ str_replace('_', ' ', $option) }}</option>@endforeach</select></div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-secondary" href="{{ route('admin.trading_floor.reminders.index') }}">Reset</a>
        </form>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">DELIVERY FOUNDATION</div><h2>Prepared Reminders</h2></div><span>{{ number_format($reminders->total()) }} RECORDS</span></div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead><tr><th>Created</th><th>Customer</th><th>Type</th><th>Channel</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Error</th></tr></thead>
            <tbody>
            @forelse($reminders as $reminder)
                <tr>
                    <td>{{ $reminder->created_at?->format('Y-m-d H:i') }}</td>
                    <td>@if($reminder->client)<a href="{{ route('admin.trading_floor.clients.show', $reminder->client) }}">{{ $reminder->client->client_code }}</a><div style="opacity:.65;font-size:12px;">{{ $reminder->client->full_name }}</div>@else—@endif</td>
                    <td>{{ str_replace('_', ' ', $reminder->reminder_type) }}</td>
                    <td>{{ str_replace('_', ' ', $reminder->channel) }}</td>
                    <td>{{ $maskRecipient($reminder->recipient) }}</td>
                    <td>
                        @if($reminder->membership)
                            <a href="{{ route('admin.trading_floor.memberships.show', $reminder->membership) }}">{{ $reminder->membership->membership_number }}</a>
                            <div style="opacity:.65;font-size:12px;">{{ $reminder->membership->package?->code }} · expires {{ $reminder->membership->expires_at?->format('Y-m-d') }}</div>
                        @elseif($reminder->invoice)
                            <a href="{{ route('admin.trading_floor.commissions.show', $reminder->invoice) }}">{{ $reminder->invoice->invoice_number }}</a>
                        @elseif($reminder->payment)
                            {{ $reminder->payment->currency }} {{ number_format((float) $reminder->payment->amount, 2) }}
                        @else—@endif
                    </td>
                    <td><span class="badge">{{ $reminder->status }}</span></td>
                    <td>{{ $reminder->last_error ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="padding:28px;text-align:center;">No reminder records yet. Use Refresh Queue to prepare eligible reminders.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:18px;">{{ $reminders->links() }}</div>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-body"><strong>Delivery boundary</strong><p style="margin-bottom:0;">This queue prepares reminder intent only. WhatsApp and email provider delivery remains separated so provider failures, retries and delivery receipts stay auditable.</p></div>
</div>
@endsection
