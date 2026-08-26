@extends('admin.layouts.app')

@section('title', 'TradeX Finance')
@section('page-title', 'Finance')

@section('content')
@php
    $usdContribution = $money->present((int) $known_usd_contribution_cents, 'USD');
    $activePayouts = $money->present((int) $active_payout_reserved_cents, 'USD');
    $affiliate = $monthly['affiliate'];
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">FINANCE CONTROL CENTER</div>
        <h1>TradeX <span>Finance</span></h1>
        <p>Accounting visibility from immutable payment, affiliate and payout ledgers. No cross-currency FX assumptions.</p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        @if($permissions['reports'])
            <a class="secondary-btn" href="{{ route('admin.finance.daily') }}">Daily Sales</a>
            <a class="secondary-btn" href="{{ route('admin.finance.monthly') }}">Monthly Sales</a>
        @endif

        @if($permissions['reconciliation'])
            <a class="secondary-btn" href="{{ route('admin.finance.reconciliation') }}">Reconciliation</a>
        @endif
    </div>
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.finance.dashboard') }}" class="user-filter-form">
        <div class="filter-item">
            <label>Finance Month</label>
            <input type="month" name="month" value="{{ $month }}">
        </div>

        <div class="filter-actions">
            <button class="primary-btn" type="submit">Load Month</button>
            <a class="secondary-btn" href="{{ route('admin.finance.dashboard') }}">Current Month</a>
        </div>
    </form>
</section>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Month</span>
        <strong>{{ $month }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Known USD Contribution</span>
        <strong class="text-green">USD {{ $usdContribution['amount'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Pending Manual Reviews</span>
        <strong class="text-gold">{{ number_format($pending_manual_reviews) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Active Payout Reserved</span>
        <strong>USD {{ $activePayouts['amount'] }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Reconciliation Alerts</span>
        <strong>{{ number_format($reconciliation_issue_count) }}</strong>
    </div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">MONTHLY SALES</div>
            <h2>Sales by Currency</h2>
        </div>
        <div class="panel-count">{{ $timezone }}</div>
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
            @forelse($monthly['currencies'] as $bucket)
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
                    <td>{{ number_format($bucket['unknown_fee_payments_count']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No finance ledger activity for this month.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">PROVIDER MIX</div>
            <h2>Sales by Provider</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Provider</th>
                    <th>Currency</th>
                    <th>Payments</th>
                    <th>Gross</th>
                    <th>Known Fees</th>
                </tr>
            </thead>
            <tbody>
            @forelse($monthly['providers'] as $provider)
                @php
                    $gross = $money->present((int) $provider['gross_minor'], $provider['currency']);
                    $fees = $money->present((int) $provider['fees_minor'], $provider['currency']);
                @endphp
                <tr>
                    <td>{{ $provider['provider'] }}</td>
                    <td>{{ $provider['currency'] }}</td>
                    <td>{{ number_format($provider['payments_count']) }}</td>
                    <td>{{ $gross['currency'] }} {{ $gross['amount'] }}</td>
                    <td>{{ $fees['currency'] }} {{ $fees['amount'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No provider sales for this month.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="padding:22px;">
    <div class="panel-eyebrow">AFFILIATE ACCRUAL — USD</div>
    <h2>Commission, Gifts & Cash Settlement</h2>

    @php
        $commissionEarned = $money->present((int) $affiliate['commission_earned_cents'], 'USD');
        $commissionAdjustments = $money->present((int) $affiliate['commission_adjustments_cents'], 'USD');
        $netCommission = $money->present((int) $affiliate['net_commission_expense_cents'], 'USD');
        $gifts = $money->present((int) $affiliate['milestone_gifts_cents'], 'USD');
        $cashPayouts = $money->present((int) $affiliate['cash_payouts_paid_cents'], 'USD');
    @endphp

    <p><strong>Commission earned:</strong> USD {{ $commissionEarned['amount'] }}</p>
    <p><strong>Commission adjustments:</strong> USD {{ $commissionAdjustments['amount'] }}</p>
    <p><strong>Net commission expense:</strong> USD {{ $netCommission['amount'] }}</p>
    <p><strong>Milestone gifts accrued:</strong> USD {{ $gifts['amount'] }}</p>
    <p><strong>Payout cash settled:</strong> USD {{ $cashPayouts['amount'] }}</p>

    <p><small>Payout cash settlement is not subtracted again from contribution because commission/gift expense is recognized when earned.</small></p>
</section>
@endsection
