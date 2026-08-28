@extends('admin.layouts.app')

@section('title', 'Copy Trading Broker Account Verification')
@section('page-title', 'Broker Account Verification')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COPY TRADING · PARTNER ACCOUNT REVIEW</div>
        <h1>Broker Account <span>Verification</span></h1>
        <p>
            Verify that submitted LIVE broker accounts are attributed to an
            approved TradeX partner relationship before Copy Trade eligibility
            can continue.
        </p>
    </div>
</div>

<div class="admin-form-callout">
    <div class="admin-form-callout-icon" aria-hidden="true">◇</div>
    <div>
        <strong>Verification is not activation</strong>
        VERIFIED confirms partner attribution, LIVE account mode and eligible
        currency only. Copy Trade execution remains blocked until all separate
        activation, balance, billing, terms and risk controls are satisfied.
    </div>
</div>

<form
    class="panel admin-form-panel"
    method="GET"
    action="{{ route('admin.copy_trading.verifications.index') }}"
    style="margin-bottom:18px;"
>
    <div class="form-grid">
        <div class="form-group">
            <label for="search">Customer / Account Last 4</label>
            <input
                id="search"
                type="text"
                name="search"
                maxlength="191"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Name, email or last 4 digits"
            >
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All Review States</option>
                @foreach($statuses as $status)
                    <option
                        value="{{ $status }}"
                        @selected(($filters['status'] ?? '') === $status)
                    >
                        {{ str_replace('_', ' ', $status) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="market">Market</label>
            <select id="market" name="market">
                <option value="">All Markets</option>
                @foreach($marketCategories as $market)
                    <option
                        value="{{ $market }}"
                        @selected(($filters['market'] ?? '') === $market)
                    >
                        {{ $market }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label for="per_page">Rows</label>
            <select id="per_page" name="per_page">
                @foreach([20, 50, 100] as $size)
                    <option
                        value="{{ $size }}"
                        @selected((int) ($filters['per_page'] ?? 20) === $size)
                    >
                        {{ $size }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="admin-form-actions">
        <button class="primary-btn" type="submit">Filter Queue</button>
        <a
            class="secondary-btn"
            href="{{ route('admin.copy_trading.verifications.index') }}"
        >
            Reset
        </a>
    </div>
</form>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">VERIFICATION QUEUE</div>
            <h2>Submitted Broker Accounts</h2>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Broker</th>
                    <th>Market</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($accounts as $account)
                @php
                    $statusClass = match($account->status) {
                        'PENDING' => 'status-pending',
                        'NEEDS_RESUBMISSION' => 'status-blocked',
                        'ESCALATED' => 'status-blocked',
                        default => 'status-pending',
                    };
                @endphp
                <tr>
                    <td>
                        <strong>{{ $account->user?->name ?? 'Unknown customer' }}</strong><br>
                        <small>{{ $account->user?->email ?? '—' }}</small>
                    </td>
                    <td>{{ $account->broker?->name ?? '—' }}</td>
                    <td>
                        {{ $account->brokerMarket?->market_category ?? '—' }}<br>
                        <small>
                            Eligible currency:
                            {{ $account->brokerMarket?->required_currency ?? '—' }}
                        </small>
                    </td>
                    <td>{{ $account->maskedAccountNumber() }}</td>
                    <td>
                        <span class="user-status {{ $statusClass }}">
                            <span class="user-status-dot"></span>
                            {{ str_replace('_', ' ', $account->status) }}
                        </span>
                    </td>
                    <td>{{ $account->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        <a
                            class="secondary-btn"
                            href="{{ route('admin.copy_trading.verifications.show', ['copyTradeBrokerAccount' => $account]) }}"
                        >
                            Review
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:30px;">
                        No broker accounts currently require verification review.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:16px 20px;">
        {{ $accounts->links() }}
    </div>
</section>
@endsection
