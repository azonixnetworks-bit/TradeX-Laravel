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

    <div>
        <a
            class="secondary-btn"
            href="{{ route('admin.products.index') }}"
        >
            Back to Catalog
        </a>
    </div>
</div>

<div class="admin-form-callout gold">
    <div class="admin-form-callout-icon" aria-hidden="true">✦</div>
    <div>
        <strong>Catalog safety</strong>
        New products are stored in the same TradeX commercial catalog used by the payment system.
        Keep a product INACTIVE until its customer entitlement or delivery flow is ready.
    </div>
</div>

<section class="panel admin-form-panel">
    <div class="admin-form-heading">
        <div class="page-eyebrow">PRODUCT CONFIGURATION</div>
        <h2>Commercial product details</h2>
        <p>
            Configure the customer-facing identity, checkout price and purchase availability.
            Immutable fields are clearly marked so the catalog stays safe after launch.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('admin.products.store') }}"
    >
        @csrf

        <div class="form-grid">
            <div class="form-group">
                <label for="product-name">Product Name</label>
                <input
                    id="product-name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    maxlength="120"
                    required
                    placeholder="Example: TradeX Pro Signals"
                    autocomplete="off"
                >
                <small class="form-help">
                    Customer-facing product name used across the catalog and checkout.
                </small>
            </div>

            <div class="form-group">
                <label for="product-code">Product Code</label>
                <input
                    id="product-code"
                    type="text"
                    name="code"
                    value="{{ old('code') }}"
                    maxlength="50"
                    required
                    placeholder="TRADEX_PRO_SIGNALS"
                    autocomplete="off"
                    style="text-transform:uppercase;"
                >
                <small class="form-help">
                    Uppercase letters, numbers and underscores only. This cannot be changed later.
                </small>
            </div>

            <div class="form-group full-width">
                <label for="product-description">Description</label>
                <textarea
                    id="product-description"
                    name="description"
                    rows="4"
                    maxlength="2000"
                    placeholder="Describe what the customer receives after purchase"
                >{{ old('description') }}</textarea>
                <small class="form-help">
                    Keep this concise and suitable for future checkout or customer-facing screens.
                </small>
            </div>

            <div class="form-group">
                <label for="product-billing-type">Billing Type</label>
                <select
                    id="product-billing-type"
                    name="billing_type"
                    required
                >
                    @foreach($billingTypes as $billingType)
                        <option
                            value="{{ $billingType }}"
                            @selected(old('billing_type', \App\Models\Product::BILLING_ONE_TIME_LIFETIME) === $billingType)
                        >
                            {{ $billingType }}
                        </option>
                    @endforeach
                </select>
                <small class="form-help">
                    Only the billing mode currently supported by this catalog is enabled. Immutable after creation.
                </small>
            </div>

            <div class="form-group">
                <label for="product-currency">Currency</label>
                <select
                    id="product-currency"
                    name="currency"
                    required
                >
                    @foreach($currencies as $currency)
                        <option
                            value="{{ $currency }}"
                            @selected(old('currency', 'USD') === $currency)
                        >
                            {{ $currency }}
                        </option>
                    @endforeach
                </select>
                <small class="form-help">
                    Checkout currency for this catalog product. Immutable after creation.
                </small>
            </div>

            <div class="form-group">
                <label for="product-price">Price in USD minor units</label>
                <input
                    id="product-price"
                    type="number"
                    name="amount_minor"
                    min="1"
                    max="999999999"
                    step="1"
                    value="{{ old('amount_minor') }}"
                    required
                    placeholder="7900"
                    inputmode="numeric"
                >
                <small class="form-help">
                    Example: 7900 = USD 79.00. Payment orders preserve the price snapshot used at checkout.
                </small>
            </div>

            <div class="form-group">
                <label for="product-status">New Purchase Status</label>
                <select
                    id="product-status"
                    name="is_active"
                    required
                >
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
                <small class="form-help">
                    INACTIVE is the safe default for newly introduced products.
                </small>
            </div>
        </div>

        @if($errors->any())
            <div class="admin-form-callout" style="margin-top:20px;margin-bottom:0;">
                <div class="admin-form-callout-icon" aria-hidden="true">!</div>
                <div>
                    <strong>Please review the product details</strong>
                    @foreach($errors->all() as $error)
                        <div class="form-error">{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="admin-form-note">
            <span aria-hidden="true">◆</span>
            <span>
                <strong>Immutable catalog fields:</strong>
                product code, billing type and currency cannot be changed after creation.
            </span>
        </div>

        <div class="admin-form-actions">
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
    </form>
</section>
@endsection
