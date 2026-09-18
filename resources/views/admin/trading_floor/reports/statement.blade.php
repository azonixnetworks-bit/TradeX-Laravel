@extends('admin.layouts.app')

@section('title', $client->client_code.' Statement | TradeX Administration')
@section('page-title', 'Trading Floor Customer Statement')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/trading-floor-print.css') }}">
@endpush

@section('content')
@php
    $currencyText = static fn (array $values): string => collect($values)
        ->map(fn ($amount, $currency) => $currency.' '.number_format((float) $amount, 2))
        ->implode(' · ');
@endphp

<div class="page-header print-hide">
    <div>
        <div class="page-eyebrow">{{ $client->client_code }} · CUSTOMER STATEMENT</div>
        <h1>{{ $client->full_name }} <span>Statement</span></h1>
        <p>{{ $from->format('Y-m-d') }} → {{ $to->format('Y-m-d') }}</p>
    </div>
    <div>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.show', $client) }}">Customer Profile</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.reports.index', ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]) }}">Reports</a>
        <button class="btn btn-primary" type="button" onclick="window.print()">Print / Save PDF</button>
    </div>
</div>

<section class="tf-print-sheet">
    <div class="tf-print-brand">
        <div>
            <div class="tf-print-kicker">TRADEX · TRADING FLOOR</div>
            <h1>Customer Statement</h1>
        </div>
        <div class="tf-print-meta">
            <strong>{{ $client->client_code }}</strong>
            <span>Generated {{ now()->format('Y-m-d H:i') }}</span>
        </div>
    </div>

    <div class="tf-print-grid tf-print-grid-2">
        <div class="tf-print-box">
            <span>Customer</span>
            <strong>{{ $client->full_name }}</strong>
            <div>{{ $client->mobile ?: ($client->email ?: 'No contact recorded') }}</div>
            <div>{{ $client->floor_code ?: 'No floor' }} / {{ $client->desk_code ?: 'No desk' }}</div>
        </div>
        <div class="tf-print-box">
            <span>Statement Period</span>
            <strong>{{ $from->format('Y-m-d') }} → {{ $to->format('Y-m-d') }}</strong>
            <div>{{ $client->commission_type }} · {{ $client->billing_cycle }}</div>
            <div>Currency: {{ $client->commission_currency }}</div>
        </div>
    </div>

    <div class="tf-print-grid tf-print-grid-3">
        <div class="tf-print-box"><span>Invoiced</span><strong>{{ $currencyText($summary['invoiced_by_currency']) ?: '0.00' }}</strong></div>
        <div class="tf-print-box"><span>Paid</span><strong>{{ $currencyText($summary['paid_by_currency']) ?: '0.00' }}</strong></div>
        <div class="tf-print-box"><span>Balance</span><strong>{{ $currencyText($summary['balance_by_currency']) ?: '0.00' }}</strong></div>
    </div>

    <div class="tf-print-section">
        <h2>Invoice & Payment Activity</h2>
        <table class="tf-print-table">
            <thead><tr><th>Invoice / Payment</th><th>Date</th><th>Description</th><th>Debit</th><th>Credit</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td><strong>{{ $invoice->invoice_number }}</strong></td>
                    <td>{{ $invoice->due_date?->format('Y-m-d') }}</td>
                    <td>{{ $invoice->period_start?->format('Y-m-d') }} → {{ $invoice->period_end?->format('Y-m-d') }}</td>
                    <td>{{ $invoice->currency }} {{ number_format((float) $invoice->commission_amount, 2) }}</td>
                    <td>—</td>
                    <td>{{ str_replace('_', ' ', $invoice->status) }}</td>
                </tr>
                @foreach($invoice->payments as $payment)
                    <tr class="tf-print-subrow">
                        <td>{{ $payment->status === 'REVERSED' ? 'Reversed payment' : 'Payment' }}</td>
                        <td>{{ $payment->received_at?->format('Y-m-d') }}</td>
                        <td>{{ str_replace('_', ' ', $payment->payment_method) }}{{ $payment->transaction_reference ? ' · '.$payment->transaction_reference : '' }}</td>
                        <td>{{ $payment->status === 'REVERSED' ? $payment->currency.' '.number_format((float) $payment->amount, 2) : '—' }}</td>
                        <td>{{ $payment->status === 'RECEIVED' ? $payment->currency.' '.number_format((float) $payment->amount, 2) : '—' }}</td>
                        <td>{{ $payment->status }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="6">No invoice activity for this statement period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="tf-print-footer">
        <span>TradeX Administration · Trading Floor Statement</span>
        <span>System-generated document</span>
    </div>
</section>
@endsection
