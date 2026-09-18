@extends('admin.layouts.app')

@section('title', 'Create Commission Invoice | TradeX Administration')
@section('page-title', 'Create Commission Invoice')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">{{ $client->client_code }} · COMMISSION BILLING</div>
        <h1>Create <span>Invoice</span></h1>
        <p>{{ $client->full_name }} · {{ $client->commission_type }} · {{ $client->billing_cycle }}</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.show', $client) }}">Customer Profile</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.commissions.index') }}">Commission Ledger</a>
    </div>
</div>

@if($errors->any())
    <div class="panel" style="margin-top:18px;border-color:rgba(255,90,90,.45);">
        <div class="panel-body">
            <strong>Check the invoice details:</strong>
            <ul style="margin:10px 0 0;padding-left:20px;">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    </div>
@endif

<div style="display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:18px;margin-top:18px;">
    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">BILLING PERIOD</div><h2>Invoice Details</h2></div></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.trading_floor.commissions.store', $client) }}">
                @csrf
                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
                    <div class="form-group">
                        <label for="period_start">Period Start</label>
                        <input id="period_start" name="period_start" type="date" required value="{{ old('period_start') }}">
                    </div>
                    <div class="form-group">
                        <label for="period_end">Period End</label>
                        <input id="period_end" name="period_end" type="date" required value="{{ old('period_end') }}">
                    </div>
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input id="due_date" name="due_date" type="date" required value="{{ old('due_date') }}">
                    </div>
                    <div class="form-group">
                        <label for="trading_profit_amount">Trading Profit ({{ $client->commission_currency }})</label>
                        <input id="trading_profit_amount" name="trading_profit_amount" type="number" min="0" step="0.01" value="{{ old('trading_profit_amount') }}" @required(in_array($client->commission_type, ['PERCENTAGE','HYBRID'], true))>
                        <small style="opacity:.65;">Required for percentage and hybrid agreements.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="3000" placeholder="Optional billing note">{{ old('notes') }}</textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.show', $client) }}">Cancel</a>
                    <button class="btn btn-primary" type="submit">Create Invoice</button>
                </div>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">AGREEMENT SNAPSHOT</div><h2>Calculation Rule</h2></div></div>
        <div class="panel-body">
            <div style="display:grid;gap:15px;">
                <div><small style="opacity:.6;">Customer</small><div><strong>{{ $client->client_code }}</strong> · {{ $client->full_name }}</div></div>
                <div><small style="opacity:.6;">Commission Type</small><div>{{ $client->commission_type }}</div></div>
                <div><small style="opacity:.6;">Percentage</small><div>{{ $client->commission_rate !== null ? number_format((float) $client->commission_rate, 2).'%' : '—' }}</div></div>
                <div><small style="opacity:.6;">Fixed Amount</small><div>{{ $client->fixed_commission_amount !== null ? $client->commission_currency.' '.number_format((float) $client->fixed_commission_amount, 2) : '—' }}</div></div>
                <div><small style="opacity:.6;">Billing Cycle</small><div>{{ $client->billing_cycle }}</div></div>
                <div><small style="opacity:.6;">Grace Days</small><div>{{ $client->grace_days }}</div></div>
            </div>
            <p style="margin-top:18px;opacity:.68;font-size:12px;">The current agreement values are copied into the invoice. Future changes to the customer agreement will not rewrite this invoice.</p>
        </div>
    </div>
</div>
@endsection
