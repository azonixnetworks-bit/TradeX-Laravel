@extends('admin.layouts.app')

@section('title', 'Trading Floor Commission Billing | TradeX Administration')
@section('page-title', 'Trading Floor Commission Billing')

@section('content')
@php
    $admin = auth('admin')->user();
    $canManage = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.commissions.manage');
    $outstandingText = collect($stats['outstanding_by_currency'])
        ->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))
        ->implode(' · ');
    $overdueText = collect($stats['overdue_by_currency'])
        ->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))
        ->implode(' · ');
    $receivedText = collect($stats['received_this_month_by_currency'])
        ->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))
        ->implode(' · ');
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-30 · COMMISSION BILLING</div>
        <h1>Commission <span>Ledger</span></h1>
        <p>Invoice, collection, outstanding balance and overdue monitoring for Trading Floor customers.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.index') }}">Floor Dashboard</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.index') }}">Customers</a>
    </div>
</div>

<div class="dashboard-grid" style="margin-top:22px;">
    <div class="stat-card"><div class="stat-card__label">Outstanding</div><div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $outstandingText !== '' ? $outstandingText : '0.00' }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Overdue</div><div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $overdueText !== '' ? $overdueText : '0.00' }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Received This Month</div><div class="stat-card__value" style="font-size:18px;line-height:1.35;">{{ $receivedText !== '' ? $receivedText : '0.00' }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Open Invoices</div><div class="stat-card__value">{{ number_format($stats['open_invoices']) }}</div></div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">COLLECTION CONTROL</div><h2>Commission Invoices</h2></div>
        <span>{{ $canManage ? 'MANAGE READY' : 'READ VIEW' }}</span>
    </div>
    <div class="panel-body">
        <form method="GET" action="{{ route('admin.trading_floor.commissions.index') }}" style="display:grid;grid-template-columns:minmax(220px,1fr) 220px auto auto;gap:10px;align-items:end;margin-bottom:18px;">
            <div class="form-group" style="margin:0;">
                <label for="search">Search</label>
                <input id="search" name="search" value="{{ $search }}" placeholder="Invoice, customer code or name">
            </div>
            <div class="form-group" style="margin:0;">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Statuses</option>
                    @foreach(['PENDING','PARTIALLY_PAID','PAID','OVERDUE','WAIVED','DISPUTED'] as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ str_replace('_', ' ', $option) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-secondary" href="{{ route('admin.trading_floor.commissions.index') }}">Reset</a>
        </form>

        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead>
                    <tr><th>Invoice</th><th>Customer</th><th>Period</th><th>Due</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th style="text-align:right;">Action</th></tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        <tr>
                            <td><strong>{{ $invoice->invoice_number }}</strong></td>
                            <td>
                                <a href="{{ route('admin.trading_floor.clients.show', $invoice->client) }}">{{ $invoice->client->client_code }}</a>
                                <div style="opacity:.66;font-size:12px;">{{ $invoice->client->full_name }}</div>
                            </td>
                            <td>{{ $invoice->period_start?->format('Y-m-d') }} → {{ $invoice->period_end?->format('Y-m-d') }}</td>
                            <td>{{ $invoice->due_date?->format('Y-m-d') }}</td>
                            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->commission_amount, 2) }}</td>
                            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->paid_amount, 2) }}</td>
                            <td><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->balance_amount, 2) }}</strong></td>
                            <td><span class="badge">{{ str_replace('_', ' ', $invoice->status) }}</span></td>
                            <td style="text-align:right;"><a class="btn btn-secondary" href="{{ route('admin.trading_floor.commissions.show', $invoice) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="padding:28px;text-align:center;opacity:.7;">No commission invoices found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px;">{{ $invoices->links() }}</div>
    </div>
</div>
@endsection
