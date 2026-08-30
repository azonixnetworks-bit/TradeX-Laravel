@extends('admin.layouts.app')

@section('title', 'TradeX Stripe Payments')
@section('page-title', 'Stripe Payments')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">PROVIDER / WEBHOOK VERIFIED</div>
        <h1>Stripe <span>Ledger</span></h1>
        <p>Read-only Stripe checkout and settlement visibility. Admins cannot manually approve or settle Stripe transactions.</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.payments.index') }}">All Payments</a>
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.payments.stripe.index') }}" class="user-filter-form">
        <div class="filter-search">
            <label>Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Order UUID, user name, email...">
        </div>
        <div class="filter-item">
            <label>Status</label>
            <select name="status">
                <option value="">All</option>
                @foreach(['CREATED','AWAITING_USER_ACTION','PROCESSING','CLEARED','FAILED','CANCELLED','PAYMENT_REVERSED'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-item">
            <label>Country</label>
            <input type="text" name="country" maxlength="2" value="{{ request('country') }}" placeholder="LK">
        </div>
        <div class="filter-item">
            <label>From</label>
            <input type="date" name="from" value="{{ request('from') }}">
        </div>
        <div class="filter-item">
            <label>To</label>
            <input type="date" name="to" value="{{ request('to') }}">
        </div>
        <div class="filter-item">
            <label>Per Page</label>
            <select name="per_page">
                @foreach([10,20,50,100] as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page',20) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button class="primary-btn" type="submit">Apply</button>
            <a class="secondary-btn" href="{{ route('admin.payments.stripe.index') }}">Reset</a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">STRIPE</div>
            <h2>Provider Records</h2>
        </div>
        <div class="panel-count">{{ number_format($orders->total()) }} records</div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Order Status</th>
                    <th>Payment</th>
                    <th>Affiliate</th>
                    <th>Cleared</th>
                    <th>Created</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($orders as $order)
                @php($amount = $money->present((int) $order->amount_minor, (string) $order->currency))
                <tr>
                    <td><strong>{{ $order->uuid }}</strong></td>
                    <td>{{ $order->user?->name ?? '—' }}<br><small>{{ $order->user?->email ?? '—' }}</small></td>
                    <td>{{ $amount['currency'] }} {{ $amount['amount'] }}</td>
                    <td><strong>{{ $order->status }}</strong></td>
                    <td>{{ $order->payment?->status ?? '—' }}</td>
                    <td>{{ $order->payment?->affiliate_processing_status ?? '—' }}</td>
                    <td>{{ $order->payment?->cleared_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $order->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td><a class="secondary-btn" href="{{ route('admin.payments.show', $order) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="9">No Stripe records match the current filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">
        @include('admin.components.pagination', ['paginator' => $orders])
    </div>
</section>
@endsection
