@extends('admin.layouts.app')

@section('title', 'Investment '.$investment->investment_code)
@section('page-title', 'Investment Details')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">INVESTMENT RECORD</div>
        <h1>{{ $investment->investment_code }}</h1>
        <p>{{ $investment->product?->name }} • {{ $investment->status }}</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.investments.index') }}">Back to Investments</a>
</div>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Investment Amount</span>
        <strong>{{ $investment->deposit_currency }} {{ number_format((float) $investment->deposit_amount, 2) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Investment Period</span>
        <strong>{{ $investment->investment_period_years }} Year(s)</strong>
    </div>
    <div class="user-stat-card">
        <span>Profit Review</span>
        <strong>Every {{ $investment->profit_review_interval_months }} Months</strong>
    </div>
    <div class="user-stat-card">
        <span>Profit Sharing</span>
        <strong>{{ $investment->customer_profit_share_bps / 100 }}% / {{ $investment->company_profit_share_bps / 100 }}%</strong>
    </div>
    <div class="user-stat-card">
        <span>Fixed Return</span>
        <strong>{{ $investment->fixed_return_guaranteed ? 'YES' : 'NO' }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Principal Settlement</span>
        <strong>{{ $investment->principal_settlement_status }}</strong>
    </div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">CUSTOMER</div>
            <h2>Investor Details</h2>
        </div>
    </div>
    <div style="padding:20px;">
        <p><strong>Name:</strong> {{ $investment->user?->name ?? '—' }}</p>

        @if($canViewCustomer)
            <p><strong>Email:</strong> {{ $investment->user?->email ?? '—' }}</p>
            <p><strong>WhatsApp:</strong> {{ $investment->user?->whatsapp_number ?? '—' }}</p>
            <p><strong>Country:</strong> {{ $investment->user?->country_code ?? '—' }}</p>
            <p><strong>KYC:</strong> {{ $investment->user?->latestKycSubmission?->status ?? 'NOT SUBMITTED' }}</p>
            <p>
                <strong>USDT TRC20 Payout Address:</strong>
                {{ $investment->payout_wallet_address ?: 'NOT CONFIGURED' }}
            </p>
            <p><strong>Wallet Verified:</strong> {{ $investment->payout_wallet_verified_at ? 'YES' : 'NO' }}</p>
        @else
            <p>Customer financial/contact details require <code>investments.customers.view</code>.</p>
        @endif
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">TERMS & SETTLEMENT</div>
            <h2>Contract Snapshot</h2>
        </div>
    </div>
    <div style="padding:20px;">
        <p><strong>Profit Model:</strong> PERFORMANCE BASED — NET REALIZED PROFIT</p>
        <p><strong>Customer Share:</strong> {{ $investment->customer_profit_share_bps / 100 }}%</p>
        <p><strong>Company Share:</strong> {{ $investment->company_profit_share_bps / 100 }}%</p>
        <p><strong>Loss Carry Forward:</strong> USDT {{ number_format((float) $investment->loss_carryforward_usdt, 2) }}</p>
        <p><strong>High-Water Mark:</strong> USDT {{ number_format((float) $investment->high_water_mark_usdt, 2) }}</p>
        <p><strong>Settlement Mode:</strong> {{ $investment->settlement_mode }}</p>
        <p><strong>Start Date:</strong> {{ $investment->start_date?->format('Y-m-d') ?? 'NOT ACTIVE' }}</p>
        <p><strong>Maturity Date:</strong> {{ $investment->maturity_date?->format('Y-m-d') ?? 'NOT CALCULATED' }}</p>
        <p><strong>Principal Settlement Date:</strong> {{ $investment->principal_settlement_date?->format('Y-m-d') ?? $investment->custom_settlement_date?->format('Y-m-d') ?? 'ON MATURITY' }}</p>
        <p><strong>Early Exit In-Kind Return Snapshot:</strong> {{ $investment->early_exit_return_bps / 100 }}% of attributable underlying spot-crypto units.</p>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">AGREEMENT & RISK</div>
            <h2>Compliance Gates</h2>
        </div>
    </div>
    <div style="padding:20px;">
        <p><strong>Latest Agreement:</strong> {{ $investment->latestAgreement?->status ?? 'NOT CREATED' }}</p>
        <p><strong>Agreement Version:</strong> {{ $investment->latestAgreement?->version ?? '—' }}</p>
        <p><strong>Risk Acceptances:</strong> {{ $investment->riskAcceptances()->count() }}</p>
        <p><small>Activation workflow is intentionally reserved for the next ADMIN-24 phase. Deposit verification + risk acceptance + signed agreement must all pass before ACTIVE.</small></p>
    </div>
</section>

@if($canViewTransactions)
<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">TRANSACTION LEDGER</div>
            <h2>Latest Transactions</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr>
                <th>Type</th>
                <th>Direction</th>
                <th>Asset</th>
                <th>Amount</th>
                <th>Network</th>
                <th>TxID</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse($investment->transactions as $transaction)
                <tr>
                    <td>{{ $transaction->type }}</td>
                    <td>{{ $transaction->direction }}</td>
                    <td>{{ $transaction->asset_symbol }}</td>
                    <td>{{ $transaction->amount }}</td>
                    <td>{{ $transaction->network ?? '—' }}</td>
                    <td>{{ $transaction->txid ?? '—' }}</td>
                    <td>{{ $transaction->status }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No investment transactions recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endif
@endsection
