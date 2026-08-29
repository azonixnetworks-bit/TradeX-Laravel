@extends('admin.layouts.app')

@section('title', 'Copy Trade Billing')
@section('page-title', 'Copy Trade Billing')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">COPY TRADING · BILLING CONTROL</div>
        <h1>Copy Trade <span>Billing</span></h1>
        <p>
            Frozen fixed-tier billing, proration, renewal, grace and immutable
            invoice evidence for Forex, Deriv and Crypto.
        </p>
    </div>
</div>

<div class="admin-form-callout">
    <div class="admin-form-callout-icon">◇</div>
    <div>
        <strong>Frozen Module 12 boundary</strong>
        Billing uses Verified Billable Balance only. Floating equity, open P/L,
        broker bonus/credit and pending funds are outside the billing balance.
        Grace period:
        <strong>
            {{ $gracePeriodDays === null
                ? 'NOT CONFIGURED'
                : $gracePeriodDays.' day(s)' }}
        </strong>.
    </div>
</div>

<div class="admin-form-actions" style="margin:18px 0;">
    <a
        class="secondary-btn"
        href="{{ route('admin.copy_trading.billing.calendar.index') }}"
    >
        Forex Official Calendar
    </a>

    <a
        class="secondary-btn"
        href="{{ route('admin.copy_trading.accounts.index') }}"
    >
        Copy Trade Accounts
    </a>
</div>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">IMMUTABLE INVOICE REGISTER</div>
            <h2>Latest Billing Invoices</h2>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Broker / Market</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Issued</th>
                    <th>Account</th>
                </tr>
            </thead>
            <tbody>
            @forelse($invoices as $invoice)
                <tr>
                    <td>
                        <strong>{{ $invoice->invoice_number }}</strong>
                    </td>
                    <td>
                        {{ $invoice->user?->name ?? '—' }}<br>
                        <small>{{ $invoice->user?->email ?? '—' }}</small>
                    </td>
                    <td>
                        {{ $invoice->brokerAccount?->broker?->name ?? '—' }}
                        /
                        {{ $invoice->brokerAccount?->brokerMarket?->market_category ?? '—' }}
                    </td>
                    <td>{{ $invoice->invoice_type }}</td>
                    <td>
                        {{ $invoice->currency }}
                        {{ number_format($invoice->total_minor / 100, 2) }}
                    </td>
                    <td>{{ $invoice->status }}</td>
                    <td>
                        {{ $invoice->issued_at?->format('Y-m-d H:i') ?? '—' }}
                    </td>
                    <td>
                        @if($invoice->brokerAccount)
                            <a
                                class="secondary-btn"
                                href="{{ route('admin.copy_trading.billing.accounts.show', ['copyTradeBrokerAccount' => $invoice->brokerAccount]) }}"
                            >
                                Open
                            </a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td
                        colspan="8"
                        style="text-align:center;padding:28px;"
                    >
                        No Copy Trade billing invoices yet.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:16px 20px;">
        {{ $invoices->links() }}
    </div>
</section>
@endsection
