@extends('admin.layouts.app')

@section('title', $membership->membership_number.' Membership Invoice | TradeX Administration')
@section('page-title', 'Printable Membership Invoice')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/trading-floor-print.css') }}">
@endpush

@section('content')
<div class="page-header print-hide">
    <div><div class="page-eyebrow">{{ $membership->membership_number }} · PRINTABLE DOCUMENT</div><h1>Membership <span>Invoice</span></h1><p>{{ $membership->client?->client_code }} · {{ $membership->client?->full_name }}</p></div>
    <div><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.show', $membership) }}">Back to Membership</a><button class="btn btn-primary" type="button" onclick="window.print()">Print / Save PDF</button></div>
</div>

<section class="tf-print-sheet">
    <div class="tf-print-brand">
        <div><div class="tf-print-kicker">TRADEX · TRADING FLOOR</div><h1>Membership Invoice</h1></div>
        <div class="tf-print-meta"><strong>{{ $membership->membership_number }}</strong><span>Created {{ $membership->created_at?->format('Y-m-d H:i') }}</span><span>Status: {{ str_replace('_', ' ', $membership->status) }}</span></div>
    </div>

    <div class="tf-print-grid tf-print-grid-2">
        <div class="tf-print-box"><span>Member</span><strong>{{ $membership->client?->full_name }}</strong><div>{{ $membership->client?->client_code }}</div><div>{{ $membership->client?->mobile ?: ($membership->client?->email ?: 'No contact recorded') }}</div></div>
        <div class="tf-print-box"><span>Membership Period</span><strong>{{ $membership->starts_at?->format('Y-m-d') }} → {{ $membership->expires_at?->format('Y-m-d') }}</strong><div>Due date: {{ $membership->due_date?->format('Y-m-d') ?: '—' }}</div><div>{{ $membership->billing_cycle }} billing</div></div>
    </div>

    <div class="tf-print-grid tf-print-grid-3">
        <div class="tf-print-box"><span>Membership Price</span><strong>{{ $membership->currency }} {{ number_format((float) $membership->price_amount, 2) }}</strong></div>
        <div class="tf-print-box"><span>Paid</span><strong>{{ $membership->currency }} {{ number_format((float) $membership->paid_amount, 2) }}</strong></div>
        <div class="tf-print-box"><span>Balance Due</span><strong>{{ $membership->currency }} {{ number_format((float) $membership->balance_amount, 2) }}</strong></div>
    </div>

    <div class="tf-print-section"><h2>Allocation & Package</h2><table class="tf-print-table"><tbody>
        <tr><th>Package</th><td>{{ $membership->package?->code }} · {{ $membership->package?->name }}</td></tr>
        <tr><th>Floor</th><td>{{ $membership->floor?->code }} · {{ $membership->floor?->name }}</td></tr>
        <tr><th>Desk / Seat</th><td>{{ $membership->desk?->code ?: 'Not assigned' }}</td></tr>
        <tr><th>Payment Status</th><td>{{ str_replace('_', ' ', $membership->payment_status) }}</td></tr>
        <tr><th>Auto Renew</th><td>{{ $membership->auto_renew ? 'YES' : 'NO' }}</td></tr>
    </tbody></table></div>

    @if($membership->payments->count())
    <div class="tf-print-section"><h2>Payment History</h2><table class="tf-print-table"><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead><tbody>
        @foreach($membership->payments as $payment)<tr><td>{{ $payment->received_at?->format('Y-m-d H:i') }}</td><td>{{ str_replace('_', ' ', $payment->payment_method) }}</td><td>{{ $payment->transaction_reference ?: '—' }}</td><td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td><td>{{ $payment->status }}</td></tr>@endforeach
    </tbody></table></div>
    @endif

    <div class="tf-print-footer"><span>TradeX Administration · Trading Floor Membership</span><span>System-generated document</span></div>
</section>
@endsection
