@extends('admin.layouts.app')

@section('title', 'Add Investment')
@section('page-title', 'Add Investment')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">NEW INVESTMENT</div>
        <h1>Add <span>Investment</span></h1>
        <p>Creates an investment record only. Activation remains blocked until deposit verification, crypto risk acceptance and signed agreement are completed.</p>
    </div>
</div>

@if($errors->any())
    <section class="panel" style="padding:18px;margin-bottom:18px;">
        <strong>Investment could not be created.</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </section>
@endif

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <div class="panel-eyebrow">PRODUCT AVAILABILITY</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-top:16px;">
        @foreach($products as $product)
            <div class="user-stat-card">
                <span>{{ $product->name }}</span>
                @if($product->status === \App\Models\InvestmentProduct::STATUS_ACTIVE)
                    <strong class="text-green">AVAILABLE</strong>
                    <small>{{ $product->deposit_currency }} / {{ $product->deposit_network }}</small>
                @else
                    <strong class="text-gold">COMING SOON</strong>
                @endif
            </div>
        @endforeach
    </div>
</section>

<section class="panel" style="padding:22px;">
    <form method="POST" action="{{ route('admin.investments.store') }}">
        @csrf

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;">
            <div class="filter-item">
                <label>Customer Email</label>
                <input type="email" name="customer_email" value="{{ old('customer_email') }}" required>
            </div>

            <div class="filter-item">
                <label>Investment Product</label>
                <select name="product_code" required>
                    @foreach($products as $product)
                        <option
                            value="{{ $product->code }}"
                            @disabled($product->status !== \App\Models\InvestmentProduct::STATUS_ACTIVE)
                            @selected(old('product_code', 'CRYPTO') === $product->code)
                        >
                            {{ $product->name }}
                            {{ $product->status === \App\Models\InvestmentProduct::STATUS_COMING_SOON ? ' — COMING SOON' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label>Investment Amount — USDT</label>
                <input type="number" step="0.00000001" min="1" name="deposit_amount" value="{{ old('deposit_amount') }}" required>
            </div>

            <div class="filter-item">
                <label>Investment Period</label>
                <select name="investment_period_years" required>
                    @foreach([1, 2, 5] as $years)
                        <option value="{{ $years }}" @selected((int) old('investment_period_years', 1) === $years)>
                            {{ $years }} Year{{ $years > 1 ? 's' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label>Profit Review / Distribution</label>
                <select name="profit_review_interval_months" required>
                    @foreach([2, 4, 6] as $months)
                        <option value="{{ $months }}" @selected((int) old('profit_review_interval_months', 2) === $months)>
                            Every {{ $months }} Months
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-item">
                <label>Principal Settlement</label>
                <select name="settlement_mode" id="settlementMode" required>
                    <option value="ON_MATURITY" @selected(old('settlement_mode', 'ON_MATURITY') === 'ON_MATURITY')>
                        On Maturity
                    </option>
                    <option value="CUSTOM_DATE" @selected(old('settlement_mode') === 'CUSTOM_DATE')>
                        Custom Settlement Date
                    </option>
                </select>
            </div>

            <div class="filter-item" id="customSettlementWrap">
                <label>Custom Settlement Date</label>
                <input type="date" name="custom_settlement_date" value="{{ old('custom_settlement_date') }}">
            </div>

            <div class="filter-item">
                <label>Customer USDT TRC20 Payout Address</label>
                <input
                    type="text"
                    name="payout_wallet_address"
                    value="{{ old('payout_wallet_address') }}"
                    placeholder="Optional — existing customer profile address is used when available"
                >
            </div>
        </div>

        <section class="panel" style="padding:18px;margin:22px 0;background:rgba(255,255,255,.025);">
            <div class="panel-eyebrow">FROZEN COMMERCIAL MODEL</div>
            <p><strong>Performance based:</strong> 80% of eligible net realized profit to customer / 20% to company.</p>
            <p><strong>No fixed annual return. No guaranteed profit.</strong> Prior losses are carried forward and a high-water-mark model is reserved in the ledger.</p>
            <p><strong>Early customer termination policy snapshot:</strong> 80% of attributable underlying spot-crypto units, subject to the signed agreement and applicable law. Original USDT value is not guaranteed.</p>
        </section>

        <button class="primary-btn" type="submit">Create Investment Record</button>
    </form>
</section>

<script>
    const settlementMode = document.getElementById('settlementMode');
    const customWrap = document.getElementById('customSettlementWrap');

    function syncSettlementDate() {
        customWrap.style.display =
            settlementMode.value === 'CUSTOM_DATE'
                ? 'block'
                : 'none';
    }

    settlementMode.addEventListener('change', syncSettlementDate);
    syncSettlementDate();
</script>
@endsection
