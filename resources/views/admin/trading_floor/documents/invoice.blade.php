@extends('admin.layouts.app')

@section('title', $invoice->invoice_number.' Printable Invoice | TradeX Administration')
@section('page-title', 'Printable Commission Invoice')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/trading-floor-print.css') }}">
@endpush

@section('content')
<div class="page-header print-hide">
    <div><div class="page-eyebrow">{{ $invoice->invoice_number }} · PRINTABLE DOCUMENT</div><h1>Commission <span>Invoice</span></h1><p>{{ $invoice->client->client_code }} · {{ $invoice->client->full_name }}</p></div>
    <div><a class="btn btn-secondary" href="{{ route('admin.trading_floor.commissions.show', $invoice) }}">Back to Invoice</a><button class="btn btn-primary" type="button" onclick="window.print()">Print / Save PDF</button></div>
</div>

<section class="tf-print-sheet">
    <div class="tf-print-brand">
        <div><div class="tf-print-kicker">TRADEX · TRADING FLOOR</div><h1>Commission Invoice</h1></div>
        <div class="tf-print-meta"><strong>{{ $invoice->invoice_number }}</strong><span>Issued {{ $invoice->created_at?->format('Y-m-d H:i') }}</span><span>Status: {{ str_replace('_', ' ', $invoice->status) }}</span></div>
    </div>
    <div class="tf-print-grid tf-print-grid-2">
        <div class="tf-print-box"><span>Bill To</span><strong>{{ $invoice->client->full_name }}</strong><div>{{ $invoice->client->client_code }}</div><div>{{ $invoice->client->mobile ?: ($invoice->client->email ?: 'No contact recorded') }}</div></div>
        <div class="tf-print-box"><span>Billing Period</span><strong>{{ $invoice->period_start?->format('Y-m-d') }} → {{ $invoice->period_end?->format('Y-m-d') }}</strong><div>Due date: {{ $invoice->due_date?->format('Y-m-d') }}</div><div>{{ $invoice->commission_type }} commission</div></div>
    </div>
    <div class="tf-print-grid tf-print-grid-3">
        <div class="tf-print-box"><span>Commission Amount</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->commission_amount, 2) }}</strong></div>
        <div class="tf-print-box"><span>Paid</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->paid_amount, 2) }}</strong></div>
        <div class="tf-print-box"><span>Balance Due</span><strong>{{ $invoice->currency }} {{ number_format((float) $invoice->balance_amount, 2) }}</strong></div>
    </div>
    <div class="tf-print-section"><h2>Calculation</h2><table class="tf-print-table"><tbody>
        <tr><th>Trading Profit</th><td>{{ $invoice->trading_profit_amount !== null ? $invoice->currency.' '.number_format((float) $invoice->trading_profit_amount, 2) : '—' }}</td></tr>
        <tr><th>Commission Rate</th><td>{{ $invoice->commission_rate !== null ? number_format((float) $invoice->commission_rate, 2).'%' : '—' }}</td></tr>
        <tr><th>Commission Type</th><td>{{ $invoice->commission_type }}</td></tr>
        <tr><th>Notes</th><td>{{ $invoice->notes ?: '—' }}</td></tr>
    </tbody></table></div>
    @if($invoice->payments->count())
    <div class="tf-print-section"><h2>Payment History</h2><table class="tf-print-table"><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead><tbody>
        @foreach($invoice->payments as $payment)<tr><td>{{ $payment->received_at?->format('Y-m-d H:i') }}</td><td>{{ str_replace('_', ' ', $payment->payment_method) }}</td><td>{{ $payment->transaction_reference ?: '—' }}</td><td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td><td>{{ $payment->status }}</td></tr>@endforeach
    </tbody></table></div>
    @endif
    <div class="tf-print-footer"><span>TradeX Administration · Trading Floor Invoice</span><span>System-generated document</span></div>
</section>
@endsection
