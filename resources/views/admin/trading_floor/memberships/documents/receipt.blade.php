@extends('admin.layouts.app')

@section('title', $membership->membership_number.' Payment Receipt | TradeX Administration')
@section('page-title', 'Printable Membership Receipt')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/trading-floor-print.css') }}">
@endpush

@section('content')
<div class="page-header print-hide">
    <div><div class="page-eyebrow">{{ $membership->membership_number }} · PAYMENT RECEIPT</div><h1>Membership <span>Receipt</span></h1><p>{{ $membership->client?->client_code }} · {{ $membership->client?->full_name }}</p></div>
    <div><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.show', $membership) }}">Back to Membership</a><button class="btn btn-primary" type="button" onclick="window.print()">Print / Save PDF</button></div>
</div>

<section class="tf-print-sheet">
    <div class="tf-print-brand">
        <div><div class="tf-print-kicker">TRADEX · TRADING FLOOR</div><h1>Membership Payment Receipt</h1></div>
        <div class="tf-print-meta"><strong>{{ $membership->membership_number }}</strong><span>Payment {{ $payment->received_at?->format('Y-m-d H:i') }}</span><span>Status: {{ $payment->status }}</span></div>
    </div>

    <div class="tf-print-grid tf-print-grid-2">
        <div class="tf-print-box"><span>Received From</span><strong>{{ $membership->client?->full_name }}</strong><div>{{ $membership->client?->client_code }}</div><div>{{ $membership->package?->name }}</div></div>
        <div class="tf-print-box"><span>Payment</span><strong>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</strong><div>{{ str_replace('_', ' ', $payment->payment_method) }}</div><div>Reference: {{ $payment->transaction_reference ?: '—' }}</div></div>
    </div>

    <div class="tf-print-section"><h2>Membership Snapshot</h2><table class="tf-print-table"><tbody>
        <tr><th>Period</th><td>{{ $membership->starts_at?->format('Y-m-d') }} → {{ $membership->expires_at?->format('Y-m-d') }}</td></tr>
        <tr><th>Floor / Desk</th><td>{{ $membership->floor?->code }} / {{ $membership->desk?->code ?: '—' }}</td></tr>
        <tr><th>Membership Price</th><td>{{ $membership->currency }} {{ number_format((float) $membership->price_amount, 2) }}</td></tr>
        <tr><th>Current Paid</th><td>{{ $membership->currency }} {{ number_format((float) $membership->paid_amount, 2) }}</td></tr>
        <tr><th>Current Balance</th><td>{{ $membership->currency }} {{ number_format((float) $membership->balance_amount, 2) }}</td></tr>
        <tr><th>Collected By</th><td>{{ $payment->receivedByAdmin?->name ?: 'System / Unknown' }}</td></tr>
        <tr><th>Notes</th><td>{{ $payment->notes ?: '—' }}</td></tr>
    </tbody></table></div>

    @if($payment->status === 'REVERSED')
    <div class="tf-print-section"><h2>Reversal</h2><table class="tf-print-table"><tbody><tr><th>Reversed At</th><td>{{ $payment->reversed_at?->format('Y-m-d H:i') ?: '—' }}</td></tr><tr><th>Reason</th><td>{{ $payment->reversal_reason ?: '—' }}</td></tr></tbody></table></div>
    @endif

    <div class="tf-print-footer"><span>TradeX Administration · Trading Floor Membership Receipt</span><span>System-generated document</span></div>
</section>
@endsection
