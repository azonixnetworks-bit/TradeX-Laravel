@extends('admin.layouts.app')

@section('title', 'Products')
@section('page-title', 'Products')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COMMERCIAL CATALOG</div>
        <h1>TradeX <span>Products</span></h1>
        <p>
            Manage future purchase availability and price.
            Product code, billing type and currency are immutable.
        </p>
    </div>

    @if($canManage)
        <div style="display:flex;align-items:center;">
            <a
                class="primary-btn"
                href="{{ route('admin.products.create') }}"
            >
                + Add Product
            </a>
        </div>
    @endif
</div>

@if(session('success'))
    <section class="panel" style="padding:14px;margin-bottom:18px;">
        {{ session('success') }}
    </section>
@endif

<section class="panel" style="padding:22px;">
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;padding:10px;">Product</th>
                    <th style="text-align:left;padding:10px;">Code</th>
                    <th style="text-align:left;padding:10px;">Billing</th>
                    <th style="text-align:left;padding:10px;">Price</th>
                    <th style="text-align:left;padding:10px;">Status</th>
                    @if($canManage)
                        <th style="text-align:right;padding:10px;">Action</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    <tr>
                        <td style="padding:10px;">
                            <strong>{{ $product->name }}</strong>
                            @if($product->description)
                                <div style="opacity:.75;margin-top:4px;">
                                    {{ $product->description }}
                                </div>
                            @endif
                        </td>
                        <td style="padding:10px;">
                            <code>{{ $product->code }}</code>
                        </td>
                        <td style="padding:10px;">
                            {{ $product->billing_type }}
                        </td>
                        <td style="padding:10px;">
                            {{ $product->currency }}
                            {{ number_format($product->amount_minor / 100, 2) }}
                        </td>
                        <td style="padding:10px;">
                            {{ $product->is_active ? 'ACTIVE' : 'INACTIVE' }}
                        </td>
                        @if($canManage)
                            <td style="padding:10px;text-align:right;">
                                <a
                                    class="primary-btn"
                                    href="{{ route('admin.products.edit', $product) }}"
                                >
                                    Edit
                                </a>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding:18px;">
                            No products are configured.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel" style="padding:18px;margin-top:18px;">
    <strong>Lifetime entitlement rule:</strong>
    deactivating a product blocks new purchases only.
    Existing ACTIVE lifetime entitlements remain valid.
</section>
@endsection