@extends('admin.layouts.app')

@section('title', 'Profit Period '.$investment->investment_code.' #'.$period->sequence)
@section('page-title', 'Profit Period Review')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">{{ $investment->investment_code }} • PERIOD #{{ $period->sequence }}</div>
        <h1>{{ $period->period_start?->format('Y-m-d') }} → {{ $period->period_end?->format('Y-m-d') }}</h1>
        <p>Only verified closed/realized spot-trading evidence is used. Floating P&amp;L is excluded.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="secondary-btn" href="{{ route('admin.investments.show', $investment) }}">Investment</a>
        <a class="secondary-btn" href="{{ route('admin.investments.profit_periods.index') }}">Profit Queue</a>
    </div>
</div>

@if($errors->any())
    <section class="panel" style="padding:18px;margin-bottom:18px;"><strong>Action could not be completed.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>
@endif
@if(session('success'))
    <section class="panel" style="padding:18px;margin-bottom:18px;"><strong>{{ session('success') }}</strong></section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Status</span><strong>{{ $period->status }}</strong></div>
    <div class="user-stat-card"><span>Outcome</span><strong>{{ $period->distribution_outcome ?? 'NOT CALCULATED' }}</strong></div>
    <div class="user-stat-card"><span>Gross Realized Profit</span><strong>{{ $period->gross_realized_profit_usdt }} USDT</strong></div>
    <div class="user-stat-card"><span>Realized Losses</span><strong>{{ $period->realized_losses_usdt }} USDT</strong></div>
    <div class="user-stat-card"><span>Approved Costs</span><strong>{{ $period->approved_costs_usdt }} USDT</strong></div>
    <div class="user-stat-card"><span>Loss Carry Forward</span><strong>{{ $period->loss_carryforward_open_usdt }} → {{ $period->loss_carryforward_close_usdt }}</strong></div>
    <div class="user-stat-card"><span>Eligible Net Profit</span><strong>{{ $period->eligible_net_profit_usdt }} USDT</strong></div>
    <div class="user-stat-card"><span>Customer 80%</span><strong>{{ $period->customer_profit_share_usdt }} USDT</strong></div>
    <div class="user-stat-card"><span>Company 20%</span><strong>{{ $period->company_profit_share_usdt }} USDT</strong></div>
    <div class="user-stat-card"><span>Reference Capital / HWM</span><strong>{{ $period->high_water_mark_open_usdt }} → {{ $period->high_water_mark_close_usdt }}</strong></div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">SPOT ONLY</div><h2>Trading Ledger</h2></div></div>
    <div style="padding:18px;">
        @if($permissions['trade_record'] && $investment->status === \App\Models\Investment::STATUS_ACTIVE)
        <form method="POST" action="{{ route('admin.investments.trades.store', $investment) }}" style="margin-bottom:24px;">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="filter-item"><label>Action</label><select name="action" required><option>BUY</option><option>SELL</option></select></div>
                <div class="filter-item"><label>Coin / Token Name</label><input name="asset_name" required></div>
                <div class="filter-item"><label>Symbol</label><input name="asset_symbol" required placeholder="BTC"></div>
                <div class="filter-item"><label>Network</label><input name="network" placeholder="Bitcoin / Ethereum / Solana"></div>
                <div class="filter-item"><label>Contract Address — token only</label><input name="contract_address"></div>
                <div class="filter-item"><label>Exchange / Broker</label><input name="exchange_broker" required></div>
                <div class="filter-item"><label>Quantity</label><input name="quantity" required></div>
                <div class="filter-item"><label>Executed Price USDT</label><input name="executed_price_usdt" required></div>
                <div class="filter-item"><label>Gross Value USDT</label><input name="gross_value_usdt" required></div>
                <div class="filter-item"><label>Fee USDT</label><input name="fee_usdt" value="0"></div>
                <div class="filter-item"><label>Realized P&amp;L USDT — SELL only</label><input name="realized_pnl_usdt" value="0"></div>
                <div class="filter-item"><label>Order / Trade Ref — optional</label><input name="order_reference"></div>
                <div class="filter-item"><label>Executed At</label><input type="datetime-local" name="executed_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
            </div>
            <p><strong>No whitelist:</strong> Investment Manager may enter any spot crypto coin/token. Futures, margin, leverage, options and perpetuals are not supported.</p>
            <button class="primary-btn" type="submit">Record Spot Trade</button>
        </form>
        @endif

        <div class="users-table-wrap">
            <table class="users-table">
                <thead><tr><th>Trade</th><th>Asset</th><th>Qty</th><th>Price</th><th>Realized P&amp;L</th><th>Fee</th><th>Status</th><th>Evidence / Finance</th></tr></thead>
                <tbody>
                @forelse($trades as $trade)
                    <tr>
                        <td>{{ $trade->action }}<br><small>{{ $trade->executed_at?->format('Y-m-d H:i') }}</small></td>
                        <td>{{ $trade->asset_symbol }}<br><small>{{ $trade->network ?? '—' }}</small></td>
                        <td>{{ $trade->quantity }}</td>
                        <td>{{ $trade->executed_price_usdt }}</td>
                        <td>{{ $trade->realized_pnl_usdt }}</td>
                        <td>{{ $trade->fee_usdt }}</td>
                        <td>{{ $trade->status }}</td>
                        <td>
                            @foreach($trade->evidences as $ev)
                                @if($permissions['evidence_view'] || $permissions['evidence_verify'])
                                    <a class="secondary-btn" href="{{ route('admin.investments.evidence.download', [$investment, $ev]) }}">Evidence {{ $ev->status }}</a>
                                @endif
                                @if($permissions['evidence_verify'] && $ev->status === \App\Models\InvestmentEvidence::STATUS_PENDING)
                                    <form method="POST" action="{{ route('admin.investments.evidence.verify', [$investment, $ev]) }}" style="display:inline">@csrf<button class="primary-btn" type="submit">Verify Evidence</button></form>
                                @endif
                            @endforeach

                            @if($permissions['evidence_manage'] && $trade->status === \App\Models\InvestmentTrade::STATUS_PENDING)
                                <form method="POST" enctype="multipart/form-data" action="{{ route('admin.investments.evidence.trade.store', [$investment, $trade]) }}" style="margin-top:8px;">@csrf<input type="file" name="document" accept="image/*,.pdf,.csv,.txt" required><input name="source_reference" placeholder="Screenshot / statement ref"><button class="secondary-btn" type="submit">Upload Screenshot</button></form>
                            @endif

                            @if($permissions['trade_verify'] && $trade->status === \App\Models\InvestmentTrade::STATUS_PENDING)
                                <form method="POST" action="{{ route('admin.investments.trades.verify', [$investment, $trade]) }}" style="margin-top:8px;">@csrf<button class="primary-btn" type="submit">Finance Verify Trade</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">No trades recorded for this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">DIRECT TRADING COSTS ONLY</div><h2>Approved Costs</h2></div></div>
    <div style="padding:18px;">
        @if($permissions['cost_record'])
        <form method="POST" action="{{ route('admin.investments.costs.store', $investment) }}" style="margin-bottom:20px;">@csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <div class="filter-item"><label>Cost Type</label><select name="cost_type" required>@foreach($approvedCostTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
                <div class="filter-item"><label>Amount USDT</label><input name="amount_usdt" required></div>
                <div class="filter-item"><label>Date / Time</label><input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                <div class="filter-item"><label>Broker / Exchange Reference</label><input name="source_reference" required></div>
                <div class="filter-item"><label>Notes</label><input name="notes"></div>
            </div>
            <button class="primary-btn" type="submit">Record Cost</button>
        </form>
        @endif

        <div class="users-table-wrap"><table class="users-table"><thead><tr><th>Type</th><th>Amount</th><th>Reference</th><th>Status</th><th>Evidence / Verify</th></tr></thead><tbody>
        @forelse($costs as $cost)
            <tr><td>{{ $cost->cost_type }}</td><td>{{ $cost->amount_usdt }}</td><td>{{ $cost->source_reference }}</td><td>{{ $cost->status }}</td><td>
                @if($permissions['evidence_manage'])<form method="POST" enctype="multipart/form-data" action="{{ route('admin.investments.evidence.cost.store', [$investment, $cost]) }}">@csrf<input type="file" name="document" accept="image/*,.pdf,.csv,.txt"><button class="secondary-btn" type="submit">Optional Evidence</button></form>@endif
                @if($permissions['cost_verify'] && $cost->status === \App\Models\InvestmentApprovedCost::STATUS_PENDING)<form method="POST" action="{{ route('admin.investments.costs.verify', [$investment, $cost]) }}">@csrf<button class="primary-btn" type="submit">Finance Verify Cost</button></form>@endif
            </td></tr>
        @empty<tr><td colspan="5">No costs.</td></tr>@endforelse
        </tbody></table></div>
        <p><small>Salary, marketing, office expense, software subscriptions and general company overhead are not deductible from customer investment performance.</small></p>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">PERIOD EVIDENCE</div><h2>P&amp;L Statement / Screenshot</h2></div></div>
    <div style="padding:18px;">
        @if($permissions['evidence_manage'])
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.investments.evidence.period.store', [$investment, $period]) }}">@csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
                <div class="filter-item"><label>Image / PDF / CSV</label><input type="file" name="document" required></div>
                <div class="filter-item"><label>Account / Reference</label><input name="account_reference"></div>
                <div class="filter-item"><label>Source Reference</label><input name="source_reference"></div>
            </div><button class="secondary-btn" type="submit">Upload Period Evidence</button>
        </form>
        @endif
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
        @foreach($evidences->where('evidence_type', \App\Models\InvestmentEvidence::TYPE_PNL_STATEMENT) as $ev)
            <a class="secondary-btn" href="{{ route('admin.investments.evidence.download', [$investment, $ev]) }}">{{ $ev->status }} • {{ $ev->document_mime_type }}</a>
            @if($permissions['evidence_verify'] && $ev->status === \App\Models\InvestmentEvidence::STATUS_PENDING)<form method="POST" action="{{ route('admin.investments.evidence.verify', [$investment, $ev]) }}">@csrf<button class="primary-btn" type="submit">Finance Verify</button></form>@endif
        @endforeach
        </div>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">IMMUTABLE CORRECTIONS</div><h2>Reversal / Adjustment</h2></div></div>
    <div style="padding:18px;">
        @if($permissions['adjustment_record'])
        <form method="POST" action="{{ route('admin.investments.adjustments.store', $investment) }}">@csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div class="filter-item"><label>Target</label><select name="target_type"><option>TRADE</option><option>COST</option></select></div>
                <div class="filter-item"><label>Target ID</label><input name="target_id" required></div>
                <div class="filter-item"><label>Type</label><select name="adjustment_type"><option>REVERSAL</option><option>ADJUSTMENT</option></select></div>
                <div class="filter-item"><label>Realized P&amp;L Delta</label><input name="realized_pnl_delta_usdt" value="0"></div>
                <div class="filter-item"><label>Approved Cost Delta</label><input name="approved_cost_delta_usdt" value="0"></div>
                <div class="filter-item"><label>Reason</label><input name="reason" required minlength="10"></div>
            </div>
            <p><small>Verified originals are never edited/deleted. Wrong quantity should be corrected by reversing the dependent trade chain and entering a corrected trade.</small></p>
            <button class="secondary-btn" type="submit">Record Correction</button>
        </form>
        @endif
        <div class="users-table-wrap" style="margin-top:14px;"><table class="users-table"><thead><tr><th>ID</th><th>Target</th><th>Type</th><th>P&amp;L Δ</th><th>Cost Δ</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @forelse($adjustments as $adjustment)
            <tr><td>{{ $adjustment->id }}</td><td>{{ $adjustment->target_type }} #{{ $adjustment->target_id }}</td><td>{{ $adjustment->adjustment_type }}</td><td>{{ $adjustment->realized_pnl_delta_usdt }}</td><td>{{ $adjustment->approved_cost_delta_usdt }}</td><td>{{ $adjustment->status }}</td><td>@if($permissions['adjustment_verify'] && $adjustment->status === \App\Models\InvestmentFinancialAdjustment::STATUS_PENDING)<form method="POST" action="{{ route('admin.investments.adjustments.verify', [$investment, $adjustment]) }}">@csrf<button class="primary-btn" type="submit">Finance Verify</button></form>@endif</td></tr>
        @empty<tr><td colspan="7">No corrections.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">REFERENCE CAPITAL</div><h2>Verified USDT TRC20 Top-ups</h2></div></div>
    <div style="padding:18px;">
        <p>Current HWM / reference capital: <strong>{{ $investment->high_water_mark_usdt }} USDT</strong>. Verified top-ups increase HWM; profit distributions do not.</p>
        @if($permissions['topup_record'] && $investment->status === \App\Models\Investment::STATUS_ACTIVE)
        <form method="POST" action="{{ route('admin.investments.topups.store', $investment) }}">@csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                <div class="filter-item"><label>Amount USDT</label><input name="amount" required></div>
                <div class="filter-item"><label>TRC20 TxID</label><input name="txid" required maxlength="64"></div>
                <div class="filter-item"><label>Sender Address</label><input name="sender_address"></div>
                <div class="filter-item"><label>TradeX Receiving Address</label><input name="receiving_address" required></div>
                <div class="filter-item"><label>Received At</label><input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
            </div><button class="primary-btn" type="submit">Record Top-up</button>
        </form>
        @endif
        <div style="margin-top:12px;">@foreach($topUps as $topUp)<p>#{{ $topUp->id }} • {{ $topUp->amount }} USDT • {{ $topUp->status }} @if($permissions['topup_verify'] && $topUp->status === \App\Models\InvestmentTransaction::STATUS_PENDING)<form method="POST" action="{{ route('admin.investments.topups.verify', [$investment, $topUp]) }}" style="display:inline">@csrf<button class="primary-btn" type="submit">Finance Verify</button></form>@endif</p>@endforeach</div>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">MAKER → CHECKER</div><h2>Calculate & Approve</h2></div></div>
    <div style="padding:18px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        @if($permissions['prepare'] && $period->approved_at === null)
            <form method="POST" action="{{ route('admin.investments.profit_periods.prepare', [$investment, $period]) }}">@csrf<button class="primary-btn" type="submit">Investment Admin: Prepare Calculation</button></form>
        @endif
        @if($permissions['approve'] && $period->status === \App\Models\InvestmentProfitPeriod::STATUS_APPROVAL_PENDING)
            <form method="POST" action="{{ route('admin.investments.profit_periods.approve', [$investment, $period]) }}">@csrf<button class="primary-btn" type="submit">Finance Admin: Final Approve</button></form>
        @endif
        <span>Maker: {{ $period->prepared_by_admin_id ?? '—' }} • Checker: {{ $period->approved_by_admin_id ?? '—' }}</span>
    </div>
</section>

<section class="panel users-panel">
    <div class="panel-header"><div><div class="panel-eyebrow">USDT • TRC20 ONLY</div><h2>Customer Profit Payout</h2></div></div>
    <div style="padding:18px;">
        @if($period->distribution_outcome !== \App\Models\InvestmentProfitPeriod::OUTCOME_PAYABLE)
            <p>No customer distribution is due for this period.</p>
        @elseif(!$payout && $permissions['payout_record'] && $period->status === \App\Models\InvestmentProfitPeriod::STATUS_APPROVED)
            <p>Approved customer share: <strong>{{ $period->customer_profit_share_usdt }} USDT</strong> → verified TRC20 wallet.</p>
            <form method="POST" action="{{ route('admin.investments.profit_payouts.store', [$investment, $period]) }}">@csrf
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                    <div class="filter-item"><label>TRC20 TxID</label><input name="txid" required maxlength="64"></div>
                    <div class="filter-item"><label>TradeX From Address — optional</label><input name="from_address"></div>
                    <div class="filter-item"><label>Paid At</label><input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                </div><button class="primary-btn" type="submit">Record Payout Transaction</button>
            </form>
        @elseif($payout)
            <p>{{ $payout->amount }} {{ $payout->asset_symbol }} • {{ $payout->network }} • {{ $payout->status }}</p>
            <p><code>{{ $payout->txid }}</code></p>
            @if($permissions['payout_verify'] && $payout->status === \App\Models\InvestmentTransaction::STATUS_PENDING)
                <form method="POST" action="{{ route('admin.investments.profit_payouts.verify', [$investment, $period, $payout]) }}">@csrf<button class="primary-btn" type="submit">Verify TxID & Settle</button></form>
            @endif
        @endif
    </div>
</section>
@endsection
