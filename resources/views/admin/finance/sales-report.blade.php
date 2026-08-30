@extends('admin.layouts.app')

@section('title', 'Sales Reports')
@section('page-title', 'Sales Reports')

@section('content')
@php
    $today = \Carbon\CarbonImmutable::now($timezone)->toDateString();
    $yesterday = \Carbon\CarbonImmutable::now($timezone)->subDay()->toDateString();
    $last7 = \Carbon\CarbonImmutable::now($timezone)->subDays(6)->toDateString();
    $last30 = \Carbon\CarbonImmutable::now($timezone)->subDays(29)->toDateString();
    $baseFilters = array_filter([
        'provider' => $provider,
        'currency' => $currency,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">CUSTOM FINANCE REPORT</div>
        <h1>Sales <span>Reports</span></h1>
        <p>{{ $range['from'] }} → {{ $range['to'] }} · {{ $range['days'] }} day(s) · {{ $timezone }}</p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="secondary-btn" href="{{ route('admin.finance.dashboard') }}">Finance Dashboard</a>
        <a class="secondary-btn" href="{{ route('admin.finance.daily') }}">Daily Sales</a>
        <a class="secondary-btn" href="{{ route('admin.finance.monthly') }}">Monthly Sales</a>
        <a
            class="primary-btn"
            href="{{ route('admin.finance.sales-report.export', array_filter([
                'from' => $range['from'],
                'to' => $range['to'],
                'provider' => $provider,
                'currency' => $currency,
            ], fn ($value) => $value !== null && $value !== '')) }}"
        >Download CSV</a>
    </div>
</div>

<section class="panel user-filter-panel" style="margin-bottom:18px;">
    <form method="GET" action="{{ route('admin.finance.sales-report') }}" class="user-filter-form">
        <div class="filter-item">
            <label>From Date</label>
            <input type="date" name="from" value="{{ $range['from'] }}" required>
        </div>

        <div class="filter-item">
            <label>To Date</label>
            <input type="date" name="to" value="{{ $range['to'] }}" required>
        </div>

        <div class="filter-item">
            <label>Provider</label>
            <select name="provider">
                <option value="">All</option>
                @foreach(['PAYPAL','LOCAL_BANK','USDT_TRC20'] as $value)
                    <option value="{{ $value }}" @selected($provider === $value)>{{ $value }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Currency</label>
            <input type="text" name="currency" value="{{ $currency }}" maxlength="8" placeholder="USD">
        </div>

        <div class="filter-actions">
            <button class="primary-btn" type="submit">Generate Report</button>
            <a class="secondary-btn" href="{{ route('admin.finance.sales-report') }}">Reset</a>
        </div>
    </form>
</section>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <div class="panel-eyebrow">QUICK PERIODS</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
        <a class="secondary-btn" href="{{ route('admin.finance.sales-report', array_merge($baseFilters, ['from' => $today, 'to' => $today])) }}">Today</a>
        <a class="secondary-btn" href="{{ route('admin.finance.sales-report', array_merge($baseFilters, ['from' => $yesterday, 'to' => $yesterday])) }}">Yesterday</a>
        <a class="secondary-btn" href="{{ route('admin.finance.sales-report', array_merge($baseFilters, ['from' => $last7, 'to' => $today])) }}">Last 7 Days</a>
        <a class="secondary-btn" href="{{ route('admin.finance.sales-report', array_merge($baseFilters, ['from' => $last30, 'to' => $today])) }}">Last 30 Days</a>
    </div>
    <p style="margin-bottom:0;"><small>Maximum single export window: {{ config('tradex_reporting.max_range_days', 366) }} days.</small></p>
</section>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Report Period</span>
        <strong>{{ $range['days'] }} days</strong>
    </div>
    <div class="user-stat-card">
        <span>Payments</span>
        <strong>{{ number_format($summary['payments_count']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Adjustments</span>
        <strong>{{ number_format($summary['adjustments_count']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Provider</span>
        <strong>{{ $provider ?: 'ALL' }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Currency</span>
        <strong>{{ $currency ?: 'ALL' }}</strong>
    </div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">PERIOD SUMMARY</div>
            <h2>Sales by Currency</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th>Payments</th>
                    <th>Gross</th>
                    <th>Known Fees</th>
                    <th>Adjustments</th>
                    <th>Known Net</th>
                    <th>Unknown Fee Rows</th>
                </tr>
            </thead>
            <tbody>
            @forelse($summary['currencies'] as $bucket)
                @php
                    $gross = $money->present((int) $bucket['gross_minor'], $bucket['currency']);
                    $fees = $money->present((int) $bucket['fees_minor'], $bucket['currency']);
                    $adjustmentsAmount = $money->present((int) $bucket['adjustments_minor'], $bucket['currency']);
                    $knownNet = $money->present((int) $bucket['known_net_minor'], $bucket['currency']);
                @endphp
                <tr>
                    <td><strong>{{ $bucket['currency'] }}</strong></td>
                    <td>{{ number_format($bucket['payments_count']) }}</td>
                    <td>{{ $gross['currency'] }} {{ $gross['amount'] }}</td>
                    <td>{{ $fees['currency'] }} {{ $fees['amount'] }}</td>
                    <td>{{ $adjustmentsAmount['currency'] }} {{ $adjustmentsAmount['amount'] }}</td>
                    <td><strong>{{ $knownNet['currency'] }} {{ $knownNet['amount'] }}</strong></td>
                    <td>{{ number_format($bucket['unknown_fee_payments_count']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No sales ledger activity for this report period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">DAILY BREAKDOWN</div>
            <h2>Day-by-Day Sales</h2>
        </div>
        <div class="panel-count">{{ number_format(count($daily_breakdown)) }} rows</div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Currency</th>
                    <th>Payments</th>
                    <th>Gross</th>
                    <th>Known Fees</th>
                    <th>Adjustments</th>
                    <th>Known Net</th>
                </tr>
            </thead>
            <tbody>
            @forelse($daily_breakdown as $row)
                @php
                    $gross = $money->present((int) $row['gross_minor'], $row['currency']);
                    $fees = $money->present((int) $row['fees_minor'], $row['currency']);
                    $adjustmentsAmount = $money->present((int) $row['adjustments_minor'], $row['currency']);
                    $knownNet = $money->present((int) $row['known_net_minor'], $row['currency']);
                @endphp
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['currency'] }}</td>
                    <td>{{ number_format($row['payments_count']) }}</td>
                    <td>{{ $gross['currency'] }} {{ $gross['amount'] }}</td>
                    <td>{{ $fees['currency'] }} {{ $fees['amount'] }}</td>
                    <td>{{ $adjustmentsAmount['currency'] }} {{ $adjustmentsAmount['amount'] }}</td>
                    <td><strong>{{ $knownNet['currency'] }} {{ $knownNet['amount'] }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="7">No daily sales rows for this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="padding:22px;">
    <div class="panel-eyebrow">AFFILIATE EXPENSE — USD</div>
    <h2>Commission, Gifts & Paid Payouts</h2>

    @php
        $affiliate = $summary['affiliate'];
        $commission = $money->present((int) $affiliate['net_commission_expense_cents'], 'USD');
        $gifts = $money->present((int) $affiliate['milestone_gifts_cents'], 'USD');
        $totalAccrued = $money->present((int) $affiliate['total_accrued_expense_cents'], 'USD');
        $cashPaid = $money->present((int) $affiliate['cash_payouts_paid_cents'], 'USD');
    @endphp

    <p><strong>Net commission expense:</strong> USD {{ $commission['amount'] }}</p>
    <p><strong>Milestone gifts accrued:</strong> USD {{ $gifts['amount'] }}</p>
    <p><strong>Total affiliate expense accrued:</strong> USD {{ $totalAccrued['amount'] }}</p>
    <p><strong>Payout cash settled:</strong> USD {{ $cashPaid['amount'] }}</p>
</section>
@endsection
