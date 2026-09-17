@extends('admin.layouts.app')

@section('title', 'Payment Receipt | TradeX Administration')
@section('page-title', 'Printable Payment Receipt')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/trading-floor-print.css') }}">
@endpush

@section('content')
<div class="page-header print-hide">
    <div><div class="page-eyebrow">{{ $invoice->invoice_number }} · PAYMENT RECEIPT</div><h1>Payment <span>Receipt</span></h1><p>{{ $invoice->client->client_code }} · {{ $invoice->client->full_name }}</p></div>
    <div><a class="btn btn-secondary" href="{{ route('admin.trading_floor.commissions.show', $invoice) }}">Back to Invoice</a><button class="btn btn-primary" type="button" onclick="window.print()">Print / Save PDF</button></div>
</div>

<section class="tf-print-sheet">
    <div class="tf-print-brand">
        <div><div class="tf-print-kicker">TRADEX · TRADING FLOOR</div><h1>Payment Receipt</h1></div>
        <div class="tf-print-meta"><strong>{{ $payment->uuid }}</strong><span>Invoice {{ $invoice->invoice_number }}</span><span>{{ $payment->received_at?->format('Y-m-d H:i') }}</span></div>
    </div>
    <div class="tf-print-grid tf-print-grid-2">
        <div class="tf-print-box"><span>Received From</span><strong>{{ $invoice->client->full_name }}</strong><div>{{ $invoice->client->client_code }}</div><div>{{ $invoice->client->mobile ?: ($invoice->client->email ?: 'No contact recorded') }}</div></div>
        <div class="tf-print-box"><span>Payment</span><strong>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</strong><div>{{ str_replace('_', ' ', $payment->payment_method) }}</div><div>Reference: {{ $payment->transaction_reference ?: '—' }}</div></div>
    </div>
    <div class="tf-print-grid tf-print-grid-3">
        <div class="tf-print-box"><span>Payment Status</span><strong>{{ $payment->status }}</strong></div>
        <div class="tf-print-box"><span>Collected By</span><strong>{{ $payment->receivedByAdmin?->name ?: 'TradeX Administration' }}</strong></div>
        <div class="tf-print-box"><span>Invoice Balance</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->balance_amount, 2) }}</strong></div>
    </div>
    <div class="tf-print-section"><h2>Receipt Details</h2><table class="tf-print-table"><tbody>
        <tr><th>Invoice</th><td>{{ $invoice->invoice_number }}</td></tr>
        <tr><th>Billing Period</th><td>{{ $invoice->period_start?->format('Y-m-d') }} → {{ $invoice->period_end?->format('Y-m-d') }}</td></tr>
        <tr><th>Received At</th><td>{{ $payment->received_at?->format('Y-m-d H:i:s') }}</td></tr>
        <tr><th>Notes</th><td>{{ $payment->notes ?: '—' }}</td></tr>
        @if($payment->status === 'REVERSED')<tr><th>Reversal</th><td>{{ $payment->reversal_reason ?: 'Reversed payment' }}{{ $payment->reversed_at ? ' · '.$payment->reversed_at->format('Y-m-d H:i') : '' }}</td></tr>@endif
    </tbody></table></div>
    <div class="tf-print-footer"><span>TradeX Administration · Trading Floor Payment Receipt</span><span>System-generated document</span></div>
</section>
@endsection
