@extends('admin.layouts.app')

@section('title', 'Monthly Sales')
@section('page-title', 'Monthly Sales')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">MONTHLY FINANCE REPORT</div>
        <h1>Monthly <span>Sales</span></h1>
        <p>{{ $month }} — {{ $timezone }}</p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="secondary-btn" href="{{ route('admin.finance.dashboard') }}">Finance Dashboard</a>
        <a class="secondary-btn" href="{{ route('admin.finance.monthly.export', request()->query()) }}">Export CSV</a>
    </div>
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.finance.monthly') }}" class="user-filter-form">
        <div class="filter-item">
            <label>Month</label>
            <input type="month" name="month" value="{{ $month }}">
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
            <button class="primary-btn" type="submit">Apply</button>
            <a class="secondary-btn" href="{{ route('admin.finance.monthly') }}">Current Month</a>
        </div>
    </form>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">MONTH TOTALS</div>
            <h2>Currency Summary</h2>
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
                </tr>
            </thead>
            <tbody>
            @forelse($summary['currencies'] as $bucket)
                @php
                    $gross = $money->present((int) $bucket['gross_minor'], $bucket['currency']);
                    $fees = $money->present((int) $bucket['fees_minor'], $bucket['currency']);
                    $adjustments = $money->present((int) $bucket['adjustments_minor'], $bucket['currency']);
                    $knownNet = $money->present((int) $bucket['known_net_minor'], $bucket['currency']);
                @endphp
                <tr>
                    <td><strong>{{ $bucket['currency'] }}</strong></td>
                    <td>{{ number_format($bucket['payments_count']) }}</td>
                    <td>{{ $gross['currency'] }} {{ $gross['amount'] }}</td>
                    <td>{{ $fees['currency'] }} {{ $fees['amount'] }}</td>
                    <td>{{ $adjustments['currency'] }} {{ $adjustments['amount'] }}</td>
                    <td><strong>{{ $knownNet['currency'] }} {{ $knownNet['amount'] }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="6">No monthly ledger activity.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">LOCAL BUSINESS-DAY BREAKDOWN</div>
            <h2>Daily Totals</h2>
        </div>
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
                    $adjustments = $money->present((int) $row['adjustments_minor'], $row['currency']);
                    $knownNet = $money->present((int) $row['known_net_minor'], $row['currency']);
                @endphp
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['currency'] }}</td>
                    <td>{{ number_format($row['payments_count']) }}</td>
                    <td>{{ $gross['currency'] }} {{ $gross['amount'] }}</td>
                    <td>{{ $fees['currency'] }} {{ $fees['amount'] }}</td>
                    <td>{{ $adjustments['currency'] }} {{ $adjustments['amount'] }}</td>
                    <td><strong>{{ $knownNet['currency'] }} {{ $knownNet['amount'] }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="7">No daily breakdown for this month/filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
