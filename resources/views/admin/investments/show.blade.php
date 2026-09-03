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

@if($errors->any())
    <section class="panel" style="padding:18px;margin-bottom:18px;">
        <strong>Action could not be completed.</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Investment Amount</span>
        <strong>{{ $investment->deposit_currency }} {{ number_format((float) $investment->deposit_amount, 2) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Period</span>
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
        <span>Status</span>
        <strong>{{ $investment->status }}</strong>
    </div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">ACTIVATION GATES</div>
            <h2>Lifecycle Readiness</h2>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;padding:18px;">
        <div class="user-stat-card"><span>Deposit</span><strong>{{ $readiness['deposit_verified'] ? 'VERIFIED' : 'REQUIRED' }}</strong></div>
        <div class="user-stat-card"><span>Risk Disclosure</span><strong>{{ $readiness['risk_accepted'] ? 'ACCEPTED' : 'REQUIRED' }}</strong></div>
        <div class="user-stat-card"><span>Agreement</span><strong>{{ $readiness['agreement_verified'] ? 'VERIFIED' : 'REQUIRED' }}</strong></div>
        <div class="user-stat-card"><span>Payout Wallet</span><strong>{{ $readiness['wallet_verified'] ? 'VERIFIED' : 'REQUIRED' }}</strong></div>
        <div class="user-stat-card"><span>Activation</span><strong>{{ $readiness['ready'] ? 'READY' : 'BLOCKED' }}</strong></div>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">CUSTOMER</div><h2>Investor Details</h2></div>
    </div>
    <div style="padding:20px;">
        <p><strong>Name:</strong> {{ $investment->user?->name ?? '—' }}</p>

        @if($permissions['view_customer'])
            <p><strong>Email:</strong> {{ $investment->user?->email ?? '—' }}</p>
            <p><strong>WhatsApp:</strong> {{ $investment->user?->whatsapp_number ?? '—' }}</p>
            <p><strong>Country:</strong> {{ $investment->user?->country_code ?? '—' }}</p>
            <p><strong>KYC:</strong> {{ $investment->user?->latestKycSubmission?->status ?? 'NOT SUBMITTED' }}</p>
            <p><strong>USDT TRC20 Payout Address:</strong> {{ $investment->payout_wallet_address ?: 'NOT CONFIGURED' }}</p>
            <p><strong>Wallet Verified:</strong> {{ $investment->payout_wallet_verified_at ? 'YES — '.$investment->payout_wallet_verified_at->format('Y-m-d H:i') : 'NO' }}</p>

            @if($permissions['wallet_verify'] && !$investment->payout_wallet_verified_at && $investment->payout_wallet_address)
                <form method="POST" action="{{ route('admin.investments.wallet.verify', $investment) }}">
                    @csrf
                    <button class="primary-btn" type="submit">Verify Customer TRC20 Wallet</button>
                </form>
            @endif
        @else
            <p>Customer financial/contact details require <code>investments.customers.view</code>.</p>
        @endif
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">STEP 1</div><h2>USDT TRC20 Deposit</h2></div>
    </div>
    <div style="padding:20px;">
        @if($verifiedDeposit)
            <p><strong>Status:</strong> VERIFIED</p>
            <p><strong>Amount:</strong> {{ $verifiedDeposit->asset_symbol }} {{ $verifiedDeposit->amount }}</p>
            <p><strong>TxID:</strong> <code>{{ $verifiedDeposit->txid }}</code></p>
            <p><strong>Verified:</strong> {{ $verifiedDeposit->verified_at?->format('Y-m-d H:i') }}</p>
        @elseif($pendingDeposit)
            <p><strong>Status:</strong> PENDING VERIFICATION</p>
            <p><strong>Amount:</strong> {{ $pendingDeposit->asset_symbol }} {{ $pendingDeposit->amount }}</p>
            <p><strong>TxID:</strong> <code>{{ $pendingDeposit->txid }}</code></p>

            @if($permissions['deposit_verify'])
                <form method="POST" action="{{ route('admin.investments.deposits.verify', [$investment, $pendingDeposit]) }}" style="margin-bottom:12px;">
                    @csrf
                    <button class="primary-btn" type="submit">Verify Deposit</button>
                </form>

                <form method="POST" action="{{ route('admin.investments.deposits.reject', [$investment, $pendingDeposit]) }}">
                    @csrf
                    <div class="filter-item">
                        <label>Rejection Reason</label>
                        <input type="text" name="reason" required minlength="5" maxlength="1000">
                    </div>
                    <button class="secondary-btn" type="submit">Reject Deposit</button>
                </form>
            @endif
        @elseif($permissions['deposit_record'] && $investment->status === \App\Models\Investment::STATUS_AWAITING_DEPOSIT)
            <p>Expected deposit: <strong>USDT {{ $investment->deposit_amount }}</strong> on <strong>TRC20</strong>.</p>

            <form method="POST" action="{{ route('admin.investments.deposits.store', $investment) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
                    <div class="filter-item">
                        <label>Amount — USDT</label>
                        <input type="text" name="amount" value="{{ old('amount', $investment->deposit_amount) }}" required>
                    </div>
                    <div class="filter-item">
                        <label>TRON TxID — 64 hex chars</label>
                        <input type="text" name="txid" value="{{ old('txid') }}" required maxlength="64">
                    </div>
                    <div class="filter-item">
                        <label>Sender Wallet — optional</label>
                        <input type="text" name="sender_address" value="{{ old('sender_address') }}">
                    </div>
                    <div class="filter-item">
                        <label>TradeX Receiving Wallet</label>
                        <input type="text" name="receiving_address" value="{{ old('receiving_address') }}" required>
                    </div>
                    <div class="filter-item">
                        <label>Received Date / Time</label>
                        <input type="datetime-local" name="occurred_at" value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}" required>
                    </div>
                </div>
                <button class="primary-btn" type="submit">Record Deposit for Verification</button>
            </form>
        @else
            <p>No deposit action is available in the current status.</p>
        @endif
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">STEP 2</div><h2>Crypto Risk Disclosure — {{ $riskVersion }}</h2></div>
    </div>
    <div style="padding:20px;">
        @if($currentRiskAcceptance)
            <p><strong>Status:</strong> ACCEPTED</p>
            <p><strong>Accepted At:</strong> {{ $currentRiskAcceptance->accepted_at?->format('Y-m-d H:i') }}</p>
            <p><strong>Evidence Source:</strong> {{ $currentRiskAcceptance->evidence_source }}</p>
            <p><strong>Reference:</strong> {{ $currentRiskAcceptance->acceptance_reference }}</p>
        @elseif($verifiedDeposit && $permissions['risk_record'])
            <ol>
                @foreach($riskStatements as $statement)
                    <li>{{ $statement }}</li>
                @endforeach
            </ol>

            <form method="POST" action="{{ route('admin.investments.risk.store', $investment) }}">
                @csrf
                <div class="filter-item">
                    <label>Customer Acceptance Evidence Reference</label>
                    <input type="text" name="acceptance_reference" value="{{ old('acceptance_reference') }}" placeholder="Signed form / recording / ticket reference" required>
                </div>
                <div class="filter-item">
                    <label>Customer Accepted At</label>
                    <input type="datetime-local" name="accepted_at" value="{{ old('accepted_at', now()->format('Y-m-d\TH:i')) }}" required>
                </div>
                <div class="filter-item">
                    <label>Customer IP — optional if independently captured</label>
                    <input type="text" name="accepted_ip" value="{{ old('accepted_ip') }}">
                </div>
                <div class="filter-item">
                    <label>Customer User Agent — optional if independently captured</label>
                    <input type="text" name="accepted_user_agent" value="{{ old('accepted_user_agent') }}">
                </div>

                <p><strong>This admin action records evidence already obtained from the customer. It must not be used to impersonate customer acceptance.</strong></p>

                @foreach([
                    'ack_read_disclosure' => 'Customer read the crypto risk disclosure.',
                    'ack_capital_loss' => 'Customer understands capital can decrease or be fully lost.',
                    'ack_crypto_volatility' => 'Customer understands crypto volatility and asset failure/delisting risk.',
                    'ack_stablecoin_risk' => 'Customer understands USDT/stablecoin issuer, de-peg and restriction risks.',
                    'ack_network_cyber_risk' => 'Customer understands TRON/TRC20, wallet, custody and cyber risks.',
                    'ack_regulatory_risk' => 'Customer understands regulatory/legal rules can change.',
                    'ack_variable_returns' => 'Customer understands returns are variable and not guaranteed.',
                    'ack_early_exit_rule' => 'Customer understands early termination does not guarantee original USDT and the agreed in-kind underlying-unit rule applies.',
                ] as $name => $label)
                    <label style="display:block;margin:10px 0;">
                        <input type="checkbox" name="{{ $name }}" value="1" required>
                        {{ $label }}
                    </label>
                @endforeach

                <button class="primary-btn" type="submit">Record Customer Risk Acceptance Evidence</button>
            </form>
        @else
            <p>Verified deposit is required before risk acceptance can be recorded.</p>
        @endif
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">STEP 3</div><h2>Agreement Management</h2></div>
    </div>
    <div style="padding:20px;">
        @if($investment->agreements->isEmpty())
            <p>No agreement version has been generated.</p>
        @else
            <div class="users-table-wrap">
                <table class="users-table">
                    <thead>
                    <tr>
                        <th>Version</th><th>Status</th><th>Template</th><th>Risk</th><th>Generated</th><th>Signed</th><th>Documents</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($investment->agreements as $agreement)
                        <tr>
                            <td>v{{ $agreement->version }}</td>
                            <td>{{ $agreement->status }}</td>
                            <td>{{ $agreement->template_version ?? '—' }}<br><small>{{ $agreement->template_status ?? '—' }}</small></td>
                            <td>{{ $agreement->risk_disclosure_version ?? '—' }}</td>
                            <td>{{ $agreement->generated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>{{ $agreement->signed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>
                                @if($permissions['agreements_view'] && $agreement->document_path)
                                    <a class="secondary-btn" href="{{ route('admin.investments.agreements.generated.download', [$investment, $agreement]) }}">Generated</a>
                                @endif
                                @if($permissions['agreements_view'] && $agreement->signed_document_path)
                                    <a class="secondary-btn" href="{{ route('admin.investments.agreements.signed.download', [$investment, $agreement]) }}">Signed PDF</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if($currentRiskAcceptance && $permissions['agreements_manage'] && !$investment->agreements->contains(fn($agreement) => in_array($agreement->status, [\App\Models\InvestmentAgreement::STATUS_SIGNED, \App\Models\InvestmentAgreement::STATUS_VERIFIED], true)))
            <form method="POST" action="{{ route('admin.investments.agreements.generate', $investment) }}" style="margin-top:16px;">
                @csrf
                <button class="primary-btn" type="submit">Generate New Agreement Version</button>
            </form>
        @endif

        @php($latestAgreement = $investment->latestAgreement)

        @if($latestAgreement && in_array($latestAgreement->status, [\App\Models\InvestmentAgreement::STATUS_GENERATED, \App\Models\InvestmentAgreement::STATUS_SENT], true) && $permissions['agreements_manage'])
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.investments.agreements.signed.upload', [$investment, $latestAgreement]) }}" style="margin-top:20px;">
                @csrf
                <div class="filter-item">
                    <label>Signed Agreement PDF</label>
                    <input type="file" name="signed_document" accept="application/pdf,.pdf" required>
                </div>
                <div class="filter-item">
                    <label>Signed At</label>
                    <input type="datetime-local" name="signed_at" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                </div>
                <button class="primary-btn" type="submit">Upload Signed PDF</button>
            </form>
        @endif

        @if($latestAgreement && $latestAgreement->status === \App\Models\InvestmentAgreement::STATUS_SIGNED && $permissions['agreements_verify'])
            <form method="POST" action="{{ route('admin.investments.agreements.verify', [$investment, $latestAgreement]) }}" style="margin-top:16px;">
                @csrf
                <button class="primary-btn" type="submit">Verify Signed Agreement</button>
            </form>
        @endif
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">STEP 4</div><h2>Activation</h2></div>
    </div>
    <div style="padding:20px;">
        @if($investment->status === \App\Models\Investment::STATUS_ACTIVE)
            <p><strong>ACTIVE</strong></p>
            <p><strong>Start:</strong> {{ $investment->start_date?->toDateString() }}</p>
            <p><strong>Maturity:</strong> {{ $investment->maturity_date?->toDateString() }}</p>
            <p><strong>Principal Settlement:</strong> {{ $investment->principal_settlement_date?->toDateString() }}</p>
        @elseif($readiness['ready'] && $permissions['activate'])
            <p>All four activation gates are satisfied.</p>
            <form method="POST" action="{{ route('admin.investments.activate', $investment) }}">
                @csrf
                <button class="primary-btn" type="submit">Activate Investment</button>
            </form>
        @else
            <p>Activation remains blocked until every gate is satisfied.</p>
        @endif
    </div>
</section>

@if($investment->profitPeriods->isNotEmpty())
<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">AUTO-GENERATED SCHEDULE</div><h2>Profit Review Periods</h2></div>
    </div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr><th>#</th><th>Start</th><th>Review Date</th><th>Status</th><th>Eligible Net Profit</th><th>Customer Share</th><th>Company Share</th></tr>
            </thead>
            <tbody>
            @foreach($investment->profitPeriods as $period)
                <tr>
                    <td>{{ $period->sequence }}</td>
                    <td>{{ $period->period_start?->toDateString() }}</td>
                    <td>{{ $period->period_end?->toDateString() }}</td>
                    <td>{{ $period->status }}</td>
                    <td>USDT {{ $period->eligible_net_profit_usdt }}</td>
                    <td>USDT {{ $period->customer_profit_share_usdt }}</td>
                    <td>USDT {{ $period->company_profit_share_usdt }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

@if($permissions['transactions_view'])
<section class="panel users-panel">
    <div class="panel-header">
        <div><div class="panel-eyebrow">IMMUTABLE TRANSACTION LEDGER</div><h2>Latest Transactions</h2></div>
    </div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr><th>Type</th><th>Direction</th><th>Asset</th><th>Amount</th><th>Network</th><th>TxID</th><th>Status</th></tr>
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
