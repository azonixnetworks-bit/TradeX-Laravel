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
    PayPal / Stripe / NOWPayments USD checkout uses this catalog price.
    Local-bank and USDT tender amounts remain separately configured because ADMIN-25 does not invent FX conversion.
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

            <label>
                <span>
                    Price in {{ $product->currency }} minor units
                    (7900 = USD 79.00)
                </span>
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
