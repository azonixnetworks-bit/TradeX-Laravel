@extends('admin.layouts.app')

@section('title', 'Add Product')
@section('page-title', 'Add Product')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COMMERCIAL CATALOG</div>
        <h1>Add <span>New Product</span></h1>
        <p>
            Create a future TradeX product and control whether customers can purchase it immediately.
            Product code, billing type and currency become immutable after creation.
        </p>
    </div>
</div>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    New products are stored in the same TradeX product catalog used by the payment system.
    Keep the product INACTIVE until its customer entitlement or delivery flow is ready.
</section>

<section class="panel" style="padding:22px;">
    <form
        method="POST"
        action="{{ route('admin.products.store') }}"
    >
        @csrf

        <div style="display:grid;gap:16px;max-width:760px;">
            <label>
                <span>Product Name</span>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    maxlength="120"
                    required
                    placeholder="Example: TradeX Pro Signals"
                    style="width:100%;"
                >
            </label>

            <label>
                <span>Product Code</span>
                <input
                    type="text"
                    name="code"
                    value="{{ old('code') }}"
                    maxlength="50"
                    required
                    placeholder="TRADEX_PRO_SIGNALS"
                    autocomplete="off"
                    style="width:100%;text-transform:uppercase;"
                >
                <small style="display:block;opacity:.7;margin-top:6px;">
                    Uppercase letters, numbers and underscores only. This cannot be changed later.
                </small>
            </label>

            <label>
                <span>Description</span>
                <textarea
                    name="description"
                    rows="4"
                    maxlength="2000"
                    placeholder="What the customer receives after purchase"
                    style="width:100%;"
                >{{ old('description') }}</textarea>
            </label>

            <label>
                <span>Billing Type</span>
                <select name="billing_type" required style="width:100%;">
                    @foreach($billingTypes as $billingType)
                        <option
                            value="{{ $billingType }}"
                            @selected(old('billing_type', \App\Models\Product::BILLING_ONE_TIME_LIFETIME) === $billingType)
                        >
                            {{ $billingType }}
                        </option>
                    @endforeach
                </select>
                <small style="display:block;opacity:.7;margin-top:6px;">
                    Only the billing mode currently supported by this catalog is enabled.
                </small>
            </label>

            <label>
                <span>Currency</span>
                <select name="currency" required style="width:100%;">
                    @foreach($currencies as $currency)
                        <option
                            value="{{ $currency }}"
                            @selected(old('currency', 'USD') === $currency)
                        >
                            {{ $currency }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Price in USD minor units (7900 = USD 79.00)</span>
                <input
                    type="number"
                    name="amount_minor"
                    min="1"
                    max="999999999"
                    step="1"
                    value="{{ old('amount_minor') }}"
                    required
                    placeholder="7900"
                    style="width:100%;"
                >
            </label>

            <label>
                <span>New Purchase Status</span>
                <select name="is_active" required style="width:100%;">
                    <option
                        value="0"
                        @selected((string) old('is_active', '0') === '0')
                    >
                        INACTIVE — configure first, do not sell yet
                    </option>
                    <option
                        value="1"
                        @selected((string) old('is_active', '0') === '1')
                    >
                        ACTIVE — allow purchase flow to use this product
                    </option>
                </select>
            </label>

            @if($errors->any())
                <div class="panel" style="padding:14px;">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <button class="primary-btn" type="submit">
                    Add Product
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