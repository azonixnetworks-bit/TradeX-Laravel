@extends('admin.layouts.app')

@section('title', 'TradeX Payouts')
@section('page-title', 'Payouts')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">AFFILIATE PAYOUT OPERATIONS</div>
        <h1>TradeX <span>Payouts</span></h1>
        <p>Review user-submitted affiliate payouts without changing earned commission, requested amount, or destination snapshots.</p>
    </div>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px 18px;margin-bottom:18px;">{{ session('success') }}</section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card"><span>All Requests</span><strong>{{ number_format($stats['all']) }}</strong></div>
    <div class="user-stat-card"><span>Pending Review</span><strong class="text-gold">{{ number_format($stats['pending']) }}</strong></div>
    <div class="user-stat-card"><span>Approved</span><strong>{{ number_format($stats['approved']) }}</strong></div>
    <div class="user-stat-card"><span>Paid</span><strong class="text-green">{{ number_format($stats['paid']) }}</strong></div>
    <div class="user-stat-card"><span>Rejected</span><strong>{{ number_format($stats['rejected']) }}</strong></div>
    <div class="user-stat-card"><span>Pending Value</span><strong>USD {{ $money->present((int) $stats['pending_cents'])['amount'] }}</strong></div>
    <div class="user-stat-card"><span>Paid Value</span><strong>USD {{ $money->present((int) $stats['paid_cents'])['amount'] }}</strong></div>
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.payouts.index') }}" class="user-filter-form">
        <div class="filter-search">
            <label>Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Payout UUID, user, email, referral...">
        </div>

        <div class="filter-item">
            <label>Method</label>
            <select name="method">
                <option value="">All</option>
                @foreach(\App\Models\PayoutRequest::METHODS as $method)
                    <option value="{{ $method }}" @selected(request('method') === $method)>{{ $method }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                @foreach(\App\Models\PayoutRequest::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>From</label>
            <input type="date" name="from" value="{{ request('from') }}">
        </div>

        <div class="filter-item">
            <label>To</label>
            <input type="date" name="to" value="{{ request('to') }}">
        </div>

        <div class="filter-item">
            <label>Per Page</label>
            <select name="per_page">
                @foreach([10,20,50] as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page',20) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button class="primary-btn" type="submit">Apply</button>
            <a class="secondary-btn" href="{{ route('admin.payouts.index') }}">Reset</a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">PAYOUT REQUEST LEDGER</div>
            <h2>Requests</h2>
        </div>
        <div class="panel-count">{{ number_format($payouts->total()) }} records</div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Payout</th>
                    <th>Affiliate</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($payouts as $payout)
                @php($amount = $money->present((int) $payout->amount_cents, (string) $payout->currency))
                <tr>
                    <td><strong>{{ $payout->uuid }}</strong></td>
                    <td>
                        {{ $payout->user?->name ?? '—' }}<br>
                        <small>{{ $payout->user?->email ?? '—' }}</small>
                    </td>
                    <td>{{ $payout->method }}</td>
                    <td>{{ $amount['currency'] }} {{ $amount['amount'] }}</td>
                    <td><strong>{{ $payout->status }}</strong></td>
                    <td>{{ $payout->requested_at?->format('Y-m-d H:i') }}</td>
                    <td><a class="secondary-btn" href="{{ route('admin.payouts.show', $payout) }}">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="7">No payout requests match the current filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">{{ $payouts->links() }}</div>
</section>
@endsection
