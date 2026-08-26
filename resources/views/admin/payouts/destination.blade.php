@extends('admin.layouts.app')

@section('title', 'Sensitive Payout Destination')
@section('page-title', 'Payout Destination')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">SENSITIVE PAYOUT DATA</div>
        <h1>Payout <span>Destination</span></h1>
        <p>{{ $payout->uuid }} — {{ $payout->method }}</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.payouts.show', $payout) }}">Back to Review</a>
</div>

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <div class="panel-eyebrow">SECURITY NOTICE</div>
    <p>This page contains the immutable destination captured when the affiliate submitted the payout. Access is audited and this response is marked no-store.</p>
</section>

<section class="panel" style="padding:22px;">
    @if(in_array($payout->method, [\App\Models\PayoutRequest::METHOD_PAYPAL, \App\Models\PayoutRequest::METHOD_BINANCE], true))
        <p><strong>Email:</strong> {{ $destination['email'] ?? '—' }}</p>
    @elseif($payout->method === \App\Models\PayoutRequest::METHOD_USDT_TRC20)
        <p><strong>Network:</strong> {{ $destination['network'] ?? 'TRC20' }}</p>
        <p><strong>Address:</strong> {{ $destination['address'] ?? '—' }}</p>
    @elseif($payout->method === \App\Models\PayoutRequest::METHOD_LOCAL_BANK)
        <p><strong>Bank:</strong> {{ $destination['bank_name'] ?? '—' }}</p>
        <p><strong>Branch:</strong> {{ $destination['branch'] ?? '—' }}</p>
        <p><strong>Account Holder:</strong> {{ $destination['account_holder'] ?? '—' }}</p>
        <p><strong>Account Number:</strong> {{ $destination['account_number'] ?? '—' }}</p>
    @else
        <p>Configured destination.</p>
    @endif
</section>
@endsection
