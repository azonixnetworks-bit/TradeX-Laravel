@extends('admin.layouts.app')

@section('title', 'Settlement '.$investment->investment_code)
@section('page-title', 'Investment Settlement')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">TERMINAL SETTLEMENT</div>
        <h1>{{ $investment->investment_code }}</h1>
        <p>{{ $settlement->type }} · {{ $settlement->status }}</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="secondary-btn" href="{{ route('admin.investments.show', $investment) }}">Investment</a>
        <a class="secondary-btn" href="{{ route('admin.investments.settlements.index') }}">Settlements</a>
    </div>
</div>

@if($errors->any())
    <section class="panel" style="padding:18px;margin-bottom:18px;">
        <strong>Action could not be completed.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Type</span><strong>{{ $settlement->type }}</strong></div>
    <div class="user-stat-card"><span>Status</span><strong>{{ $settlement->status }}</strong></div>
    <div class="user-stat-card"><span>Cutoff</span><strong>{{ $settlement->cutoff_at?->format('Y-m-d H:i') }}</strong></div>
    <div class="user-stat-card"><span>Due</span><strong>{{ $settlement->due_at?->toDateString() }}</strong></div>
    <div class="user-stat-card"><span>Underlying Return</span><strong>{{ number_format($settlement->return_bps / 100, 2) }}%</strong></div>
    <div class="user-stat-card"><span>Principal State</span><strong>{{ $investment->principal_settlement_status }}</strong></div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">IMMUTABLE SNAPSHOT</div><h2>Terminal Calculation</h2></div></div>
    <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;">
        <div class="user-stat-card"><span>Reference Capital</span><strong>USDT {{ $settlement->reference_capital_usdt }}</strong></div>
        <div class="user-stat-card"><span>Eligible Terminal Profit</span><strong>USDT {{ $settlement->eligible_net_profit_usdt }}</strong></div>
        <div class="user-stat-card"><span>Customer Profit 80%</span><strong>USDT {{ $settlement->customer_profit_share_usdt }}</strong></div>
        <div class="user-stat-card"><span>TradeX Profit 20%</span><strong>USDT {{ $settlement->company_profit_share_usdt }}</strong></div>
        <div class="user-stat-card"><span>Loss Carry Forward</span><strong>USDT {{ $settlement->loss_carryforward_close_usdt }}</strong></div>
        <div class="user-stat-card"><span>Source SHA-256</span><strong style="font-size:12px;word-break:break-all;">{{ $settlement->calculation_source_sha256 ?? 'NOT PREPARED' }}</strong></div>
    </div>

    @if($settlement->reason)
        <div style="padding:0 20px 20px;"><strong>Reason:</strong> {{ $settlement->reason }}</div>
    @endif

    @if($permissions['prepare'] && in_array($settlement->status, [\App\Models\InvestmentSettlement::STATUS_DRAFT, \App\Models\InvestmentSettlement::STATUS_PREPARED], true))
        <div style="padding:0 20px 20px;">
            <form method="POST" action="{{ route('admin.investments.settlements.prepare', [$investment, $settlement]) }}">
                @csrf
                <button class="primary-btn" type="submit">{{ $settlement->status === \App\Models\InvestmentSettlement::STATUS_PREPARED ? 'Re-Prepare from Current Verified Ledger' : 'Prepare Settlement' }}</button>
            </form>
        </div>
    @endif
</section>

@if($settlement->assets->isNotEmpty())
<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">PER-ASSET IN-KIND LEDGER</div><h2>Attributable Assets</h2></div></div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>Asset</th><th>Identity</th><th>Gross Units</th><th>Customer Units</th><th>Exit Adjustment</th><th>Wallet</th><th>Transfer</th></tr></thead>
            <tbody>
            @foreach($settlement->assets as $asset)
                @php($assetTx = $settlement->transactions->firstWhere('investment_settlement_asset_id', $asset->id))
                <tr>
                    <td>
                        <strong>{{ $asset->asset_symbol }}</strong><br>
                        <small>{{ $asset->asset_network ?? 'NETWORK N/A' }}</small>
                    </td>
                    <td style="max-width:240px;word-break:break-all;">
                        {{ $asset->contract_address ?? 'Native / no contract' }}
                    </td>
                    <td>{{ $asset->gross_attributable_units }}</td>
                    <td>{{ $asset->customer_return_units }}</td>
                    <td>{{ $asset->early_exit_adjustment_units }}</td>
                    <td style="min-width:280px;">
                        <div><strong>{{ $asset->wallet_status }}</strong></div>
                        @if($asset->destination_wallet_sha256)
                            <small>Wallet SHA: {{ substr($asset->destination_wallet_sha256, 0, 14) }}…</small>
                        @endif

                        @if($permissions['wallet_record'] && $settlement->status === \App\Models\InvestmentSettlement::STATUS_PREPARED && $asset->wallet_status !== \App\Models\InvestmentSettlementAsset::WALLET_VERIFIED)
                            <form method="POST" action="{{ route('admin.investments.settlements.wallet.record', [$investment, $settlement, $asset]) }}" style="margin-top:10px;">
                                @csrf
                                <div class="filter-item"><label>Payout Network</label><input type="text" name="payout_network" value="{{ old('payout_network', $asset->asset_network) }}" required></div>
                                <div class="filter-item"><label>Customer Destination Wallet</label><input type="text" name="destination_wallet" required></div>
                                <div class="filter-item"><label>Wallet Evidence / Reference</label><input type="text" name="wallet_reference" required></div>
                                <button class="secondary-btn" type="submit">Record Wallet</button>
                            </form>
                        @endif

                        @if($permissions['wallet_verify'] && $settlement->status === \App\Models\InvestmentSettlement::STATUS_PREPARED && $asset->wallet_status === \App\Models\InvestmentSettlementAsset::WALLET_PENDING)
                            <form method="POST" action="{{ route('admin.investments.settlements.wallet.verify', [$investment, $settlement, $asset]) }}" style="margin-top:10px;">
                                @csrf
                                <button class="primary-btn" type="submit">Finance Verify Wallet</button>
                            </form>
                        @endif
                    </td>
                    <td style="min-width:300px;">
                        <div><strong>{{ $asset->status }}</strong></div>
                        @if($assetTx)
                            <div>TxID: <code>{{ $assetTx->txid }}</code></div>
                            <div>Net: {{ $assetTx->amount }} {{ $assetTx->asset_symbol }}</div>
                            <div>Fee: {{ $asset->network_fee_units }} {{ $asset->network_fee_asset_symbol ?? $asset->asset_symbol }}</div>
                            <div>Tx Status: {{ $assetTx->status }}</div>
                        @endif

                        @if($permissions['payment_record'] && in_array($settlement->status, [\App\Models\InvestmentSettlement::STATUS_APPROVED, \App\Models\InvestmentSettlement::STATUS_IN_PROGRESS], true) && $asset->status === \App\Models\InvestmentSettlementAsset::STATUS_READY)
                            <form method="POST" action="{{ route('admin.investments.settlements.asset_payout.record', [$investment, $settlement, $asset]) }}" style="margin-top:10px;">
                                @csrf
                                <div class="filter-item"><label>Actual Network Fee Asset</label><input type="text" name="network_fee_asset_symbol" value="{{ $asset->asset_symbol }}"></div>
                                <div class="filter-item"><label>Actual Network Fee Units</label><input type="text" name="network_fee_units" value="0" required></div>
                                <label style="display:block;margin:8px 0;"><input type="checkbox" name="deduct_fee_from_customer" value="1"> Deduct this fee from customer quantity (same asset only)</label>
                                <div class="filter-item"><label>Transfer TxID</label><input type="text" name="txid" required></div>
                                <div class="filter-item"><label>Company From Address — optional</label><input type="text" name="from_address"></div>
                                <input type="hidden" name="occurred_at" value="{{ now()->toDateTimeString() }}">
                                <button class="primary-btn" type="submit">Record Asset Transfer</button>
                            </form>
                        @endif

                        @if($permissions['payment_verify'] && $assetTx && $assetTx->status === \App\Models\InvestmentTransaction::STATUS_PENDING)
                            <form method="POST" action="{{ route('admin.investments.settlements.asset_payout.verify', [$investment, $settlement, $asset, $assetTx]) }}" style="margin-top:10px;">
                                @csrf
                                <button class="primary-btn" type="submit">Finance Verify TxID</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

@if($settlement->status === \App\Models\InvestmentSettlement::STATUS_PREPARED && $permissions['approve'])
<section class="panel" style="padding:20px;margin-bottom:18px;">
    <h2>Finance Approval</h2>
    <p>Approval re-computes the immutable source fingerprint and requires every asset destination wallet to be Finance verified.</p>
    <form method="POST" action="{{ route('admin.investments.settlements.approve', [$investment, $settlement]) }}">
        @csrf
        <button class="primary-btn" type="submit">Approve Terminal Settlement</button>
    </form>
</section>
@endif

@if($settlement->type === \App\Models\InvestmentSettlement::TYPE_EARLY_TERMINATION && (float) $settlement->customer_profit_share_usdt > 0)
<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">ACCRUED REALIZED PROFIT</div><h2>Customer 80% Profit Payout</h2></div></div>
    <div style="padding:20px;">
        <p>Approved customer terminal profit: <strong>USDT {{ $settlement->customer_profit_share_usdt }}</strong> on the customer's verified TRC20 payout wallet.</p>
        @if($terminalProfitPayout)
            <p><strong>Status:</strong> {{ $terminalProfitPayout->status }} · <strong>TxID:</strong> <code>{{ $terminalProfitPayout->txid }}</code></p>
        @endif

        @if($permissions['payment_record'] && in_array($settlement->status, [\App\Models\InvestmentSettlement::STATUS_APPROVED, \App\Models\InvestmentSettlement::STATUS_IN_PROGRESS], true) && !$terminalProfitPayout)
            <form method="POST" action="{{ route('admin.investments.settlements.terminal_profit.record', [$investment, $settlement]) }}">
                @csrf
                <div class="filter-item"><label>USDT TRC20 TxID</label><input type="text" name="txid" required></div>
                <div class="filter-item"><label>Company From Address — optional</label><input type="text" name="from_address"></div>
                <input type="hidden" name="occurred_at" value="{{ now()->toDateTimeString() }}">
                <button class="primary-btn" type="submit">Record Terminal Profit Payout</button>
            </form>
        @endif

        @if($permissions['payment_verify'] && $terminalProfitPayout && $terminalProfitPayout->status === \App\Models\InvestmentTransaction::STATUS_PENDING)
            <form method="POST" action="{{ route('admin.investments.settlements.terminal_profit.verify', [$investment, $settlement, $terminalProfitPayout]) }}">
                @csrf
                <button class="primary-btn" type="submit">Finance Verify Profit TxID</button>
            </form>
        @endif
    </div>
</section>
@endif

@if($settlement->status === \App\Models\InvestmentSettlement::STATUS_SETTLED && $permissions['close'])
<section class="panel" style="padding:20px;">
    <h2>Final Closure</h2>
    <p>All required in-kind asset transfers{{ (float) $settlement->customer_profit_share_usdt > 0 ? ' and terminal customer profit payout' : '' }} are verified. Closing is explicit and audited.</p>
    <form method="POST" action="{{ route('admin.investments.settlements.close', [$investment, $settlement]) }}">
        @csrf
        <button class="primary-btn" type="submit">Close Investment</button>
    </form>
</section>
@endif
@endsection
