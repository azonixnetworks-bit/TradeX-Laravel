@extends('admin.layouts.app')

@section('title', 'Payment Settings')
@section('page-title', 'Payment Settings')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">SAFE CONFIGURATION HEALTH</div>
        <h1>Payment <span>Settings</span></h1>
        <p>
            Read-only provider readiness. Credentials, bank account numbers and wallet addresses are never rendered here.
        </p>
    </div>
</div>

@include('admin.settings.partials.nav')

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">FROZEN PAYMENT OWNERSHIP</div>
            <h2>Provider Status</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Provider</th>
                    <th>Enabled</th>
                    <th>Countries</th>
                    <th>Safe Readiness</th>
                    <th>Environment / Network</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>PayPal</strong></td>
                    <td>{{ $snapshot['paypal']['enabled'] ? 'YES' : 'NO' }}</td>
                    <td>{{ implode(', ', $snapshot['paypal']['countries']) ?: '—' }}</td>
                    <td>{{ $snapshot['paypal']['credentials_configured'] ? 'CONFIGURED' : 'INCOMPLETE' }}</td>
                    <td>{{ strtoupper($snapshot['paypal']['environment']) }}</td>
                </tr>
                <tr>
                    <td><strong>Local Bank</strong></td>
                    <td>{{ $snapshot['local_bank']['enabled'] ? 'YES' : 'NO' }}</td>
                    <td>{{ implode(', ', $snapshot['local_bank']['countries']) ?: '—' }}</td>
                    <td>{{ $snapshot['local_bank']['destination_configured'] ? 'CONFIGURED' : 'INCOMPLETE' }}</td>
                    <td>MANUAL RECONCILIATION</td>
                </tr>
                <tr>
                    <td><strong>USDT TRC20</strong></td>
                    <td>{{ $snapshot['usdt_trc20']['enabled'] ? 'YES' : 'NO' }}</td>
                    <td>{{ implode(', ', $snapshot['usdt_trc20']['countries']) ?: '—' }}</td>
                    <td>{{ $snapshot['usdt_trc20']['wallet_configured'] ? 'CONFIGURED' : 'INCOMPLETE' }}</td>
                    <td>{{ $snapshot['usdt_trc20']['network'] }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

@if(auth('admin')->user()->hasPermission('payments.view'))
    <div style="margin-top:18px;">
        <a
            class="primary-btn"
            href="{{ route('admin.payments.index') }}"
        >
            Open Payments Management
        </a>
    </div>
@endif

<section class="panel" style="padding:18px;margin-top:18px;">
    Pricing, provider credentials, bank destination details, USDT wallet addresses, refund/reversal rules and reconciliation actions remain outside ADMIN-14.
</section>
@endsection
