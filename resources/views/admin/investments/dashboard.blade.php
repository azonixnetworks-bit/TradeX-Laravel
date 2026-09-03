@extends('admin.layouts.app')

@section('title', 'TradeX Investments')
@section('page-title', 'Investments')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">INVESTMENT CONTROL CENTER</div>
        <h1>TradeX <span>Investments</span></h1>
        <p>Performance-based investment administration. Crypto is active; Indian Stocks and US Stocks are staged as coming soon.</p>
    </div>

    @if($canCreate)
        <a class="primary-btn" href="{{ route('admin.investments.create') }}">Add Investment</a>
    @endif
</div>

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Total Investments</span>
        <strong>{{ number_format($stats['total_investments']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Active Investments</span>
        <strong class="text-green">{{ number_format($stats['active_investments']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Total Investors</span>
        <strong>{{ number_format($stats['total_investors']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Active Capital</span>
        <strong>USDT {{ number_format((float) $stats['active_capital_usdt'], 2) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Awaiting Deposit</span>
        <strong class="text-gold">{{ number_format($stats['awaiting_deposit']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Unsigned Agreements</span>
        <strong>{{ number_format($stats['unsigned_agreements']) }}</strong>
    </div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">INVESTMENT PRODUCTS</div>
            <h2>Product Availability</h2>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;padding:18px;">
        @foreach($products as $product)
            <div class="user-stat-card" style="min-height:150px;">
                <span>{{ $product->code }}</span>
                <strong>{{ $product->name }}</strong>

                @if($product->status === \App\Models\InvestmentProduct::STATUS_ACTIVE)
                    <div class="text-green" style="margin-top:10px;font-weight:800;">AVAILABLE</div>
                    <small>Deposit: {{ $product->deposit_currency }} / {{ $product->deposit_network }}</small>
                    <small>Profit share: {{ $product->customer_profit_share_bps / 100 }}% customer / {{ $product->company_profit_share_bps / 100 }}% company</small>
                @else
                    <div class="text-gold" style="margin-top:10px;font-weight:800;">COMING SOON</div>
                @endif
            </div>
        @endforeach
    </div>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">RECENT INVESTMENTS</div>
            <h2>Latest Records</h2>
        </div>
        <a class="secondary-btn" href="{{ route('admin.investments.index') }}">View All</a>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
            <tr>
                <th>Investment</th>
                <th>Customer</th>
                <th>Product</th>
                <th>Amount</th>
                <th>Period</th>
                <th>Profit Review</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse($recentInvestments as $investment)
                <tr>
                    <td>
                        <a href="{{ route('admin.investments.show', $investment) }}">
                            <strong>{{ $investment->investment_code }}</strong>
                        </a>
                    </td>
                    <td>{{ $investment->user?->name ?? '—' }}</td>
                    <td>{{ $investment->product?->name ?? '—' }}</td>
                    <td>USDT {{ number_format((float) $investment->deposit_amount, 2) }}</td>
                    <td>{{ $investment->investment_period_years }} year(s)</td>
                    <td>Every {{ $investment->profit_review_interval_months }} months</td>
                    <td>{{ $investment->status }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">No investment records yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
