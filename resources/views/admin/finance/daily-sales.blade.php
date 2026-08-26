@extends('admin.layouts.app')

@section('title', 'Daily Sales')
@section('page-title', 'Daily Sales')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">DAILY FINANCE REPORT</div>
        <h1>Daily <span>Sales</span></h1>
        <p>{{ $date }} — {{ $timezone }}</p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="secondary-btn" href="{{ route('admin.finance.dashboard') }}">Finance Dashboard</a>
        <a class="secondary-btn" href="{{ route('admin.finance.daily.export', request()->query()) }}">Export CSV</a>
    </div>
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.finance.daily') }}" class="user-filter-form">
        <div class="filter-item">
            <label>Date</label>
            <input type="date" name="date" value="{{ $date }}">
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

        <div class="filter-item">
            <label>Per Page</label>
            <select name="per_page">
                @foreach([10,20,50,100] as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page',20) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button class="primary-btn" type="submit">Apply</button>
            <a class="secondary-btn" href="{{ route('admin.finance.daily') }}">Today</a>
        </div>
    </form>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">DAILY SUMMARY</div>
            <h2>Currency Totals</h2>
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
                </tr>
            @empty
                <tr><td colspan="6">No ledger activity for this date/filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">CLEARED PAYMENT LEDGER</div>
            <h2>Payments</h2>
        </div>
        <div class="panel-count">{{ number_format($payments->total()) }} records</div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Payment</th>
                    <th>User</th>
                    <th>Provider</th>
                    <th>Gross</th>
                    <th>Known Fee</th>
                    <th>Status</th>
                    <th>Cleared</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payments as $payment)
                @php
                    $gross = $money->present((int) $payment->gross_amount_minor, $payment->currency);
                    $fee = $payment->processor_fee_minor === null
                        ? null
                        : $money->present((int) $payment->processor_fee_minor, $payment->currency);
                @endphp
                <tr>
                    <td>{{ $payment->uuid }}</td>
                    <td>
                        {{ $payment->user?->name ?? '—' }}<br>
                        <small>{{ $payment->user?->email ?? '—' }}</small>
                    </td>
                    <td>{{ $payment->provider }}</td>
                    <td>{{ $gross['currency'] }} {{ $gross['amount'] }}</td>
                    <td>{{ $fee ? $fee['currency'].' '.$fee['amount'] : 'Unknown / not recorded' }}</td>
                    <td>{{ $payment->status }}</td>
                    <td>{{ $payment->cleared_at?->timezone($timezone)->format('Y-m-d H:i:s') }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No cleared payments.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">{{ $payments->links() }}</div>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">SIGNED ADJUSTMENTS</div>
            <h2>Reversals / Adjustments</h2>
        </div>
        <div class="panel-count">{{ number_format($adjustments->total()) }} records</div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Adjustment</th>
                    <th>Payment</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Recorded</th>
                </tr>
            </thead>
            <tbody>
            @forelse($adjustments as $adjustment)
                @php($amount = $money->present((int) $adjustment->amount_minor, $adjustment->currency))
                <tr>
                    <td>{{ $adjustment->uuid }}</td>
                    <td>{{ $adjustment->payment?->uuid ?? '—' }}</td>
                    <td>{{ $adjustment->type }}</td>
                    <td>{{ $amount['currency'] }} {{ $amount['amount'] }}</td>
                    <td>{{ $adjustment->created_at?->timezone($timezone)->format('Y-m-d H:i:s') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No payment adjustments for this date/filter.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">{{ $adjustments->links() }}</div>
</section>
@endsection
