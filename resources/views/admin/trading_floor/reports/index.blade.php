@extends('admin.layouts.app')

@section('title', 'Trading Floor Reports | TradeX Administration')
@section('page-title', 'Trading Floor Reports')

@section('content')
@php
    $currencyText = static fn (array $values): string => collect($values)
        ->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))
        ->implode(' · ');
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-30 · OPERATIONS & REPORTING</div>
        <h1>Trading Floor <span>Reports</span></h1>
        <p>Attendance, live presence, commission collection, outstanding balances and overdue customers in one audit-ready view.</p>
    </div>
    <div>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.index') }}">Floor Dashboard</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.reminders.index') }}">Reminder Queue</a>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <form method="GET" action="{{ route('admin.trading_floor.reports.index') }}" style="display:grid;grid-template-columns:200px 200px auto auto auto;gap:10px;align-items:end;">
            <div class="form-group" style="margin:0;">
                <label for="from">From</label>
                <input id="from" name="from" type="date" value="{{ $from->format('Y-m-d') }}">
            </div>
            <div class="form-group" style="margin:0;">
                <label for="to">To</label>
                <input id="to" name="to" type="date" value="{{ $to->format('Y-m-d') }}">
            </div>
            <button class="btn btn-primary" type="submit">Apply Range</button>
            <a class="btn btn-secondary" href="{{ route('admin.trading_floor.reports.attendance_csv', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">Attendance CSV</a>
            <a class="btn btn-secondary" href="{{ route('admin.trading_floor.reports.finance_csv', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">Finance CSV</a>
        </form>
    </div>
</div>

<div class="dashboard-grid" style="margin-top:18px;">
    <div class="stat-card"><div class="stat-card__label">Active Clients</div><div class="stat-card__value">{{ number_format($stats['active_clients']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Present Now</div><div class="stat-card__value">{{ number_format($stats['present_now']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Entries</div><div class="stat-card__value">{{ number_format($stats['entries']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Exits</div><div class="stat-card__value">{{ number_format($stats['exits']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Warnings</div><div class="stat-card__value">{{ number_format($stats['warnings']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Denied</div><div class="stat-card__value">{{ number_format($stats['denied']) }}</div></div>
</div>

<div class="dashboard-grid" style="margin-top:18px;">
    <div class="stat-card"><div class="stat-card__label">Invoices Issued</div><div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $currencyText($stats['issued_by_currency']) ?: '0.00' }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Received</div><div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $currencyText($stats['received_by_currency']) ?: '0.00' }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Outstanding</div><div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $currencyText($stats['outstanding_by_currency']) ?: '0.00' }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Overdue</div><div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $currencyText($stats['overdue_by_currency']) ?: '0.00' }}</div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:18px;">
    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">LIVE FLOOR</div><h2>Currently Inside</h2></div><span>{{ number_format($presentNow->count()) }} PRESENT</span></div>
        <div class="panel-body" style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead><tr><th>Customer</th><th>Floor / Desk</th><th>Last Entry</th></tr></thead>
                <tbody>
                @forelse($presentNow as $log)
                    <tr>
                        <td><a href="{{ route('admin.trading_floor.clients.show', $log->client) }}">{{ $log->client?->client_code }}</a><div style="opacity:.65;font-size:12px;">{{ $log->client?->full_name }}</div></td>
                        <td>{{ $log->client?->floor_code ?: '—' }} / {{ $log->client?->desk_code ?: '—' }}</td>
                        <td>{{ $log->accessed_at?->format('Y-m-d H:i:s') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;padding:24px;">No customers are currently marked inside.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">COLLECTION</div><h2>Recent Payments</h2></div></div>
        <div class="panel-body" style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead><tr><th>Time</th><th>Customer</th><th>Invoice</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($recentPayments as $payment)
                    <tr>
                        <td>{{ $payment->received_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $payment->invoice?->client?->client_code }}<div style="opacity:.65;font-size:12px;">{{ $payment->invoice?->client?->full_name }}</div></td>
                        <td><a href="{{ $payment->invoice ? route('admin.trading_floor.commissions.show', $payment->invoice) : '#' }}">{{ $payment->invoice?->invoice_number }}</a></td>
                        <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                        <td><span class="badge">{{ $payment->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;padding:24px;">No payments in this date range.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">OVERDUE CONTROL</div><h2>Customers Requiring Attention</h2></div><span>{{ number_format($overdueInvoices->count()) }} SHOWN</span></div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead><tr><th>Customer</th><th>Invoice</th><th>Due Date</th><th>Balance</th><th>Contact</th><th style="text-align:right;">Action</th></tr></thead>
            <tbody>
            @forelse($overdueInvoices as $invoice)
                <tr>
                    <td><a href="{{ route('admin.trading_floor.clients.show', $invoice->client) }}">{{ $invoice->client?->client_code }}</a><div style="opacity:.65;font-size:12px;">{{ $invoice->client?->full_name }}</div></td>
                    <td><a href="{{ route('admin.trading_floor.commissions.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                    <td>{{ $invoice->due_date?->format('Y-m-d') }}</td>
                    <td><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->balance_amount, 2) }}</strong></td>
                    <td>{{ $invoice->client?->whatsapp_number ?: ($invoice->client?->mobile ?: ($invoice->client?->email ?: '—')) }}</td>
                    <td style="text-align:right;"><a class="btn btn-secondary" href="{{ route('admin.trading_floor.reports.client_statement', ['client' => $invoice->client, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">Statement</a></td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:24px;">No overdue invoices.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">ACCESS AUDIT</div><h2>Recent Access Activity</h2></div></div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead><tr><th>Time</th><th>Customer</th><th>Device</th><th>Direction</th><th>Decision</th><th>Reason</th></tr></thead>
            <tbody>
            @forelse($recentAccess as $attempt)
                <tr>
                    <td>{{ $attempt->occurred_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $attempt->client?->client_code ?: 'Unknown' }}<div style="opacity:.65;font-size:12px;">{{ $attempt->client?->full_name }}</div></td>
                    <td>{{ $attempt->device_code }}</td>
                    <td>{{ $attempt->direction }}</td>
                    <td><strong>{{ $attempt->decision }}</strong></td>
                    <td>{{ $attempt->reason_code }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:24px;">No access activity in this date range.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
