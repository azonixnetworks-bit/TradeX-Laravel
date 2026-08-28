@extends('admin.layouts.app')

@section('title', 'Copy Trade Accounts')
@section('page-title', 'Copy Trade Accounts')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COPY TRADING · ACCOUNT REGISTER</div>
        <h1>Copy Trade <span>Accounts</span></h1>
        <p>
            Read the full lifecycle of customer broker accounts across pending,
            verified, active, suspended, disconnected and exception states.
        </p>
    </div>
</div>

<div class="admin-form-callout">
    <div class="admin-form-callout-icon" aria-hidden="true">◇</div>
    <div>
        <strong>State boundary</strong>
        Broker verification does not automatically grant Copy Trade execution.
        ACTIVE is reserved for the dedicated activation layer after all frozen
        eligibility and commercial gates are satisfied.
    </div>
</div>

<form
    class="panel admin-form-panel"
    method="GET"
    action="{{ route('admin.copy_trading.accounts.index') }}"
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
                <option value="">All Statuses</option>
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
        <button class="primary-btn" type="submit">Filter Accounts</button>
        <a
            class="secondary-btn"
            href="{{ route('admin.copy_trading.accounts.index') }}"
        >
            Reset
        </a>
    </div>
</form>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">BROKER ACCOUNT REGISTER</div>
            <h2>All Copy Trade Accounts</h2>
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
                    <th>Verified</th>
                    <th>Activated</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($accounts as $account)
                @php
                    $statusClass = match($account->status) {
                        'ACTIVE', 'VERIFIED' => 'status-active',
                        'PENDING', 'NEEDS_RESUBMISSION' => 'status-pending',
                        default => 'status-blocked',
                    };
                @endphp
                <tr>
                    <td>
                        <strong>{{ $account->user?->name ?? 'Unknown customer' }}</strong><br>
                        <small>{{ $account->user?->email ?? '—' }}</small>
                    </td>
                    <td>{{ $account->broker?->name ?? '—' }}</td>
                    <td>{{ $account->brokerMarket?->market_category ?? '—' }}</td>
                    <td>{{ $account->maskedAccountNumber() }}</td>
                    <td>
                        <span class="user-status {{ $statusClass }}">
                            <span class="user-status-dot"></span>
                            {{ str_replace('_', ' ', $account->status) }}
                        </span>
                    </td>
                    <td>{{ $account->verified_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $account->activated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        <a
                            class="secondary-btn"
                            href="{{ route('admin.copy_trading.accounts.show', ['copyTradeBrokerAccount' => $account]) }}"
                        >
                            Inspect
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;padding:30px;">
                        No Copy Trade broker accounts found.
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
