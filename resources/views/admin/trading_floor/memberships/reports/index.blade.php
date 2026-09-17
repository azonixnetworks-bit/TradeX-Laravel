@extends('admin.layouts.app')

@section('title', 'Membership Revenue Reports | TradeX Administration')
@section('page-title', 'Membership Revenue Reports')

@section('content')
@php
    $revenueText = collect($stats['revenue_by_currency'])->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))->implode(' · ');
    $outstandingText = collect($stats['outstanding_by_currency'])->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))->implode(' · ');
@endphp

<div class="page-header">
    <div><div class="page-eyebrow">ADMIN-31 · PACKAGE REVENUE</div><h1>Membership <span>Reports</span></h1><p>Package sales, actual collections, outstanding membership balances and renewal workload without cross-currency aggregation.</p></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;"><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.seat_map') }}">Seat Availability Map</a><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.index') }}">Memberships</a><a class="btn btn-primary" href="{{ route('admin.trading_floor.memberships.reports.package_revenue_csv', ['from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}">Export CSV</a></div>
</div>

<div class="panel" style="margin-top:18px;"><div class="panel-body"><form method="GET" style="display:grid;grid-template-columns:180px 180px auto auto;gap:10px;align-items:end;"><div class="form-group" style="margin:0;"><label>From</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div><div class="form-group" style="margin:0;"><label>To</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.reports.index') }}">Reset</a></form></div></div>

<div class="dashboard-grid" style="margin-top:18px;">
    <div class="stat-card"><div class="stat-card__label">Active Memberships</div><div class="stat-card__value">{{ number_format($stats['active_memberships']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Expiring ≤ 7 Days</div><div class="stat-card__value">{{ number_format($stats['expiring_7_days']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Overdue Payments</div><div class="stat-card__value">{{ number_format($stats['overdue_payments']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Received in Range</div><div class="stat-card__value" style="font-size:17px;">{{ $revenueText !== '' ? $revenueText : '0.00' }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Outstanding</div><div class="stat-card__value" style="font-size:17px;">{{ $outstandingText !== '' ? $outstandingText : '0.00' }}</div></div>
</div>

<div class="panel" style="margin-top:18px;"><div class="panel-header"><div><div class="panel-eyebrow">PACKAGE PERFORMANCE</div><h2>Revenue by Package & Currency</h2></div></div><div class="panel-body" style="overflow-x:auto;"><table class="data-table"><thead><tr><th>Package</th><th>Currency</th><th>Memberships Sold</th><th>Sold Value</th><th>Actual Received</th></tr></thead><tbody>@forelse($packageRows as $row)<tr><td><strong>{{ $row['package_code'] }}</strong><div style="opacity:.65;font-size:12px;">{{ $row['package_name'] }}</div></td><td>{{ $row['currency'] }}</td><td>{{ number_format($row['memberships_sold']) }}</td><td>{{ $row['currency'] }} {{ number_format($row['sold_value'],2) }}</td><td><strong>{{ $row['currency'] }} {{ number_format($row['received'],2) }}</strong></td></tr>@empty<tr><td colspan="5" style="padding:28px;text-align:center;opacity:.7;">No package revenue activity in this range.</td></tr>@endforelse</tbody></table></div></div>

<div class="panel" style="margin-top:18px;"><div class="panel-header"><div><div class="panel-eyebrow">COLLECTION AUDIT</div><h2>Recent Membership Payments</h2></div></div><div class="panel-body" style="overflow-x:auto;"><table class="data-table"><thead><tr><th>Time</th><th>Membership</th><th>Customer</th><th>Package</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead><tbody>@forelse($recentPayments as $payment)<tr><td>{{ $payment->received_at?->format('Y-m-d H:i') }}</td><td>{{ $payment->membership?->membership_number }}</td><td>{{ $payment->membership?->client?->client_code }}<div style="opacity:.65;font-size:12px;">{{ $payment->membership?->client?->full_name }}</div></td><td>{{ $payment->membership?->package?->code }}</td><td>{{ str_replace('_',' ',$payment->payment_method) }}</td><td>{{ $payment->currency }} {{ number_format((float)$payment->amount,2) }}</td><td><span class="badge">{{ $payment->status }}</span></td></tr>@empty<tr><td colspan="7" style="padding:28px;text-align:center;opacity:.7;">No membership payments in this range.</td></tr>@endforelse</tbody></table></div></div>
@endsection
