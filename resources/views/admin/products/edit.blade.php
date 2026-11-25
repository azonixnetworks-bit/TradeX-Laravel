@extends('admin.layouts.app')

@section('title', 'Edit Product')
@section('page-title', 'Edit Product')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COMMERCIAL CATALOG</div>
        <h1>Edit <span>{{ $product->name }}</span></h1>
        <p>
            Changes affect future purchase attempts only.
            Existing PaymentOrder amount snapshots are not rewritten.
        </p>
    </div>
</div>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    PayPal / Stripe / NOWPayments use the USD price. Local-bank checkout uses
    the LKR price. Affiliate discount applies only when a valid affiliate code
    is supplied during the Premium Course purchase.
</section>

<section class="panel" style="padding:22px;">
    <div class="user-stat-grid" style="margin-bottom:20px;">
        <div class="user-stat-card">
            <span>Product Code</span>
            <strong>{{ $product->code }}</strong>
        </div>
        <div class="user-stat-card">
            <span>Billing Type</span>
            <strong>{{ $product->billing_type }}</strong>
        </div>
        <div class="user-stat-card">
            <span>Currency</span>
            <strong>{{ $product->currency }}</strong>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('admin.products.update', $product) }}"
    >
        @csrf
        @method('PATCH')

        <div style="display:grid;gap:16px;max-width:760px;">
            <label>
                <span>Name</span>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name', $product->name) }}"
                    maxlength="120"
                    required
                    style="width:100%;"
                >
            </label>

            <label>
                <span>Description</span>
                <textarea
                    name="description"
                    rows="4"
                    maxlength="2000"
                    style="width:100%;"
                >{{ old('description', $product->description) }}</textarea>
            </label>

            @if($product->isEducationPremium())
                <label>
                    <span>Regular Course Price (USD)</span>
                    <input
                        id="regular-price-usd"
                        type="number"
                        name="regular_price_usd"
                        min="0.01"
                        max="9999999.99"
                        step="0.01"
                        value="{{ old('regular_price_usd', number_format($product->amount_minor / 100, 2, '.', '')) }}"
                        required
                        style="width:100%;"
                    >
                </label>

                <label>
                    <span>Regular Course Price (LKR)</span>
                    <input
                        id="regular-price-lkr"
                        type="number"
                        name="regular_price_lkr"
                        min="0.01"
                        max="9999999999.99"
                        step="0.01"
                        value="{{ old('regular_price_lkr', number_format(($product->lkr_amount_minor ?? 5000000) / 100, 2, '.', '')) }}"
                        required
                        style="width:100%;"
                    >
                </label>

                <label>
                    <span>Valid Affiliate Code Discount (%)</span>
                    <input
                        id="affiliate-discount-percent"
                        type="number"
                        name="affiliate_discount_percent"
                        min="0"
                        max="100"
                        step="1"
                        value="{{ old('affiliate_discount_percent', $product->affiliate_discount_percent ?? 15) }}"
                        required
                        style="width:100%;"
                    >
                </label>

                <section class="panel" style="padding:18px;">
                    <strong>Customer Pricing Preview</strong>
                    <div style="display:grid;gap:8px;margin-top:12px;">
                        <div>Regular course price: <strong id="preview-regular-usd"></strong></div>
                        <div>LKR price: <strong id="preview-regular-lkr"></strong></div>
                        <div>Valid affiliate code discount: <strong id="preview-discount"></strong></div>
                        <div>Discounted USD price: <strong id="preview-discounted-usd"></strong></div>
                        <div>Discounted LKR price: <strong id="preview-discounted-lkr"></strong></div>
                    </div>
                </section>
            @else
                <label>
                    <span>Price in {{ $product->currency }} minor units</span>
                    <input
                        type="number"
                        name="amount_minor"
                        min="1"
                        max="999999999"
                        step="1"
                        value="{{ old('amount_minor', $product->amount_minor) }}"
                        required
                        style="width:100%;"
                    >
                </label>
            @endif

            <label>
                <span>New Purchase Status</span>
                <select name="is_active" required style="width:100%;">
                    <option
                        value="1"
                        @selected((string) old('is_active', $product->is_active ? '1' : '0') === '1')
                    >
                        ACTIVE — allow new purchases
                    </option>
                    <option
                        value="0"
                        @selected((string) old('is_active', $product->is_active ? '1' : '0') === '0')
                    >
                        INACTIVE — block new purchases
                    </option>
                </select>
            </label>

            @if($errors->any())
                <div>
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div style="display:flex;gap:12px;">
                <button class="primary-btn" type="submit">
                    Save Product
                </button>

                <a
                    class="secondary-btn"
                    href="{{ route('admin.products.index') }}"
                >
                    Cancel
                </a>
            </div>
        </div>
    </form>
</section>
@endsection

@push('scripts')
@if($product->isEducationPremium())
<script>
(() => {
    const usd = document.getElementById('regular-price-usd');
    const lkr = document.getElementById('regular-price-lkr');
    const discount = document.getElementById('affiliate-discount-percent');
    const money = (value, currency) => `${currency === 'USD' ? '$' : 'Rs. '}${new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number.isFinite(value) ? value : 0)}`;

    const render = () => {
        const usdValue = Number.parseFloat(usd.value) || 0;
        const lkrValue = Number.parseFloat(lkr.value) || 0;
        const discountValue = Math.min(100, Math.max(0, Number.parseInt(discount.value, 10) || 0));
        const multiplier = (100 - discountValue) / 100;

        document.getElementById('preview-regular-usd').textContent = money(usdValue, 'USD');
        document.getElementById('preview-regular-lkr').textContent = money(lkrValue, 'LKR');
        document.getElementById('preview-discount').textContent = `${discountValue}%`;
        document.getElementById('preview-discounted-usd').textContent = money(usdValue * multiplier, 'USD');
        document.getElementById('preview-discounted-lkr').textContent = money(lkrValue * multiplier, 'LKR');
    };

    [usd, lkr, discount].forEach((input) => input.addEventListener('input', render));
    render();
})();
</script>
@endif
@endpush
