@extends('admin.layouts.app')

@section('title', $invoice->invoice_number.' | TradeX Administration')
@section('page-title', 'Commission Invoice')

@section('content')
@php
    $admin = auth('admin')->user();
    $canManagePayments = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.payments.manage');
    $canReceivePayment = $canManagePayments && ! in_array($invoice->status, ['PAID','WAIVED'], true) && (float) $invoice->balance_amount > 0;
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">{{ $invoice->invoice_number }} · COMMISSION INVOICE</div>
        <h1>{{ $invoice->client->full_name }} <span>{{ str_replace('_', ' ', $invoice->status) }}</span></h1>
        <p>{{ $invoice->client->client_code }} · {{ $invoice->period_start?->format('Y-m-d') }} → {{ $invoice->period_end?->format('Y-m-d') }}</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.documents.invoice', $invoice) }}">Print Invoice</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.commissions.index') }}">Commission Ledger</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.show', $invoice->client) }}">Customer Profile</a>
    </div>
</div>

@if($errors->any())
    <div class="panel" style="margin-top:18px;border-color:rgba(255,90,90,.45);"><div class="panel-body"><strong>Check the payment details:</strong><ul style="margin:10px 0 0;padding-left:20px;">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
@endif

<div class="dashboard-grid" style="margin-top:22px;">
    <div class="stat-card"><div class="stat-card__label">Commission Amount</div><div class="stat-card__value" style="font-size:22px;">{{ $invoice->currency }} {{ number_format((float) $invoice->commission_amount, 2) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Paid</div><div class="stat-card__value" style="font-size:22px;">{{ $invoice->currency }} {{ number_format((float) $invoice->paid_amount, 2) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Balance</div><div class="stat-card__value" style="font-size:22px;">{{ $invoice->currency }} {{ number_format((float) $invoice->balance_amount, 2) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Due Date</div><div class="stat-card__value" style="font-size:22px;">{{ $invoice->due_date?->format('Y-m-d') }}</div></div>
</div>

<div style="display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;margin-top:18px;">
    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">PAYMENT HISTORY</div><h2>Collections</h2></div></div>
        <div class="panel-body" style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead><tr><th>Received</th><th>Method</th><th>Reference</th><th>Amount</th><th>Collected By</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                    @forelse($invoice->payments as $payment)
                        <tr>
                            <td>{{ $payment->received_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ str_replace('_', ' ', $payment->payment_method) }}</td>
                            <td>{{ $payment->transaction_reference ?: '—' }}</td>
                            <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                            <td>{{ $payment->receivedByAdmin?->name ?: '—' }}</td>
                            <td><span class="badge">{{ $payment->status }}</span>@if($payment->status === 'REVERSED' && $payment->reversal_reason)<div style="font-size:11px;opacity:.65;margin-top:4px;">{{ $payment->reversal_reason }}</div>@endif</td>
                            <td style="text-align:right;white-space:nowrap;">
                                <a class="btn btn-secondary" href="{{ route('admin.trading_floor.documents.receipt', [$invoice, $payment]) }}">Receipt</a>
                                @if($payment->proof_path)<a class="btn btn-secondary" href="{{ route('admin.trading_floor.commissions.payments.proof', [$invoice, $payment]) }}">Proof</a>@endif
                                @if($canManagePayments && $payment->status === 'RECEIVED')
                                    <form method="POST" action="{{ route('admin.trading_floor.commissions.payments.reverse', [$invoice, $payment]) }}" style="display:inline-flex;gap:6px;align-items:center;margin-left:6px;">@csrf @method('PATCH')<input name="reversal_reason" required maxlength="2000" placeholder="Reversal reason" style="min-width:150px;"><button class="btn btn-secondary" type="submit">Reverse</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="padding:26px;text-align:center;opacity:.7;">No payments recorded for this invoice.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="display:grid;gap:18px;align-content:start;">
        <div class="panel"><div class="panel-header"><div><div class="panel-eyebrow">INVOICE SNAPSHOT</div><h2>Calculation</h2></div></div><div class="panel-body"><div style="display:grid;gap:13px;">
            <div><small style="opacity:.6;">Commission Type</small><div>{{ $invoice->commission_type }}</div></div>
            <div><small style="opacity:.6;">Trading Profit</small><div>{{ $invoice->trading_profit_amount !== null ? $invoice->currency.' '.number_format((float) $invoice->trading_profit_amount, 2) : '—' }}</div></div>
            <div><small style="opacity:.6;">Commission Rate</small><div>{{ $invoice->commission_rate !== null ? number_format((float) $invoice->commission_rate, 2).'%' : '—' }}</div></div>
            <div><small style="opacity:.6;">Status</small><div><strong>{{ str_replace('_', ' ', $invoice->status) }}</strong></div></div>
            <div><small style="opacity:.6;">Notes</small><div>{{ $invoice->notes ?: '—' }}</div></div>
        </div></div></div>

        @if($canReceivePayment)
            <div class="panel"><div class="panel-header"><div><div class="panel-eyebrow">COLLECTION</div><h2>Receive Payment</h2></div></div><div class="panel-body">
                <form method="POST" action="{{ route('admin.trading_floor.commissions.payments.store', $invoice) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group"><label for="amount">Amount ({{ $invoice->currency }})</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" max="{{ $invoice->balance_amount }}" required value="{{ old('amount') }}"></div>
                    <div class="form-group"><label for="payment_method">Payment Method</label><select id="payment_method" name="payment_method" required>@foreach(['CASH','BANK_TRANSFER','CARD','PAYPAL','CRYPTO','OTHER'] as $method)<option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ str_replace('_', ' ', $method) }}</option>@endforeach</select></div>
                    <div class="form-group"><label for="transaction_reference">Transaction Reference</label><input id="transaction_reference" name="transaction_reference" maxlength="190" value="{{ old('transaction_reference') }}"></div>
                    <div class="form-group"><label for="received_at">Received At</label><input id="received_at" name="received_at" type="datetime-local" required value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}"></div>
                    <div class="form-group"><label for="proof">Payment Proof</label><input id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.pdf"><small style="opacity:.65;">JPG, PNG or PDF. Max 5 MB. Stored privately.</small></div>
                    <div class="form-group"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="3" maxlength="3000">{{ old('notes') }}</textarea></div>
                    <button class="btn btn-primary" type="submit" style="width:100%;">Record Payment</button>
                </form>
            </div></div>
        @endif
    </div>
</div>
@endsection
