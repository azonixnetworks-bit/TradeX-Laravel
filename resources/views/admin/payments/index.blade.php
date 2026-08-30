@extends('admin.layouts.app')

@section('title', 'TradeX Payments')
@section('page-title', 'Payments')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">PAYMENT OPERATIONS</div>
        <h1>TradeX <span>Payments</span></h1>
        <p>Provider-verified PayPal, Stripe and NOWPayments records plus controlled Binance / USDT TRC20 and local-bank manual-payment reconciliation.</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        @if(auth('admin')->user()->hasPermission('products.view'))
            <a class="secondary-btn" href="{{ route('admin.products.index') }}">Products</a>
        @endif
        <a class="secondary-btn" href="{{ route('admin.payments.paypal.index') }}">PayPal</a>
        <a class="secondary-btn" href="{{ route('admin.payments.stripe.index') }}">Stripe</a>
        <a class="secondary-btn" href="{{ route('admin.payments.nowpayments.index') }}">NOWPayments</a>
        <a class="secondary-btn" href="{{ route('admin.payments.bank.index') }}">Bank Review</a>
        <a class="secondary-btn" href="{{ route('admin.payments.usdt.index') }}">Binance / USDT Review</a>
        @if($permissions['accounts'])
            <a class="secondary-btn" href="{{ route('admin.payments.accounts.index') }}">Payment Accounts</a>
        @endif
    </div>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px 18px;margin-bottom:18px;">{{ session('success') }}</section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card"><span>All Orders</span><strong>{{ number_format($stats['orders']) }}</strong></div>
    <div class="user-stat-card"><span>Pending Manual</span><strong class="text-gold">{{ number_format($stats['pending_manual']) }}</strong></div>
    <div class="user-stat-card"><span>Cleared</span><strong class="text-green">{{ number_format($stats['cleared']) }}</strong></div>
    <div class="user-stat-card"><span>Failed</span><strong>{{ number_format($stats['failed']) }}</strong></div>
    <div class="user-stat-card"><span>Reversed</span><strong>{{ number_format($stats['reversed']) }}</strong></div>
    <div class="user-stat-card"><span>PayPal Orders</span><strong>{{ number_format($stats['paypal']) }}</strong></div>
    <div class="user-stat-card"><span>Stripe Orders</span><strong>{{ number_format($stats['stripe']) }}</strong></div>
    <div class="user-stat-card"><span>NOWPayments Orders</span><strong>{{ number_format($stats['nowpayments']) }}</strong></div>
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.payments.index') }}" class="user-filter-form">
        <div class="filter-search"><label>Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Order UUID, user name, email..."></div>
        <div class="filter-item"><label>Provider</label><select name="provider"><option value="">All</option><option value="PAYPAL" @selected(request('provider') === 'PAYPAL')>PayPal</option><option value="LOCAL_BANK" @selected(request('provider') === 'LOCAL_BANK')>Local Bank</option><option value="USDT_TRC20" @selected(request('provider') === 'USDT_TRC20')>Binance / USDT TRC20</option></select></div>
        <div class="filter-item"><label>Status</label><select name="status"><option value="">All</option>@foreach(['CREATED','AWAITING_USER_ACTION','PENDING_REVIEW','PROCESSING','CLEARED','FAILED','CANCELLED','PAYMENT_REVERSED'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div class="filter-item"><label>Country</label><input type="text" name="country" maxlength="2" value="{{ request('country') }}" placeholder="LK"></div>
        <div class="filter-item"><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
        <div class="filter-item"><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
        <div class="filter-item"><label>Per Page</label><select name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int) request('per_page',20) === $size)>{{ $size }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="primary-btn" type="submit">Apply</button><a class="secondary-btn" href="{{ route('admin.payments.index') }}">Reset</a></div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header"><div><div class="panel-eyebrow">PAYMENT ORDER LEDGER</div><h2>Orders</h2></div><div class="panel-count">{{ number_format($orders->total()) }} records</div></div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>Order</th><th>User</th><th>Provider</th><th>Amount</th><th>Country</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                @php($amount = $money->present((int) $order->amount_minor, (string) $order->currency))
                <tr>
                    <td><strong>{{ $order->uuid }}</strong></td>
                    <td>{{ $order->user?->name ?? '—' }}<br><small>{{ $order->user?->email ?? '—' }}</small></td>
                    <td>{{ $order->provider === 'USDT_TRC20' ? 'BINANCE / USDT TRC20' : $order->provider }}</td>
                    <td>{{ $amount['currency'] }} {{ $amount['amount'] }}</td>
                    <td>{{ $order->country_code }}</td>
                    <td><strong>{{ $order->status }}</strong></td>
                    <td>{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                    <td><a class="secondary-btn" href="{{ route('admin.payments.show', $order) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="8">No payment orders match the current filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding-top:18px;">
        @include('admin.components.pagination', ['paginator' => $orders])
    </div>
</section>
@endsection
