@extends('admin.layouts.app')

@section('title', 'Copy Trade Account Billing')
@section('page-title', 'Copy Trade Account Billing')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">
            COPY TRADING · ACCOUNT BILLING
        </div>

        <h1>Billing <span>Evidence</span></h1>

        <p>
            {{ $account->user?->name ?? 'Unknown customer' }} ·
            {{ $account->broker?->name ?? '—' }} ·
            {{ $account->brokerMarket?->market_category ?? '—' }} ·
            {{ $account->maskedAccountNumber() }}
        </p>
    </div>
</div>

@if(session('success'))
    <div
        class="admin-form-callout"
        style="margin-bottom:18px;"
    >
        <div class="admin-form-callout-icon">✓</div>
        <div>
            <strong>{{ session('success') }}</strong>
        </div>
    </div>
@endif

@if($errors->any())
    <div
        class="admin-form-callout"
        style="margin-bottom:18px;"
    >
        <div class="admin-form-callout-icon">!</div>
        <div>
            <strong>Billing action blocked</strong>
            <ul style="margin:8px 0 0 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="admin-form-callout">
    <div class="admin-form-callout-icon">◇</div>
    <div>
        <strong>Commercial boundary</strong>
        This page never changes broker verification and never force-closes
        existing positions. Billing suspension only blocks new copied-trade
        eligibility; Module 13 safety remains separate.
    </div>
</div>

@if($canManage)
<div
    class="admin-form-actions"
    style="margin:18px 0;"
>
    <form
        method="POST"
        action="{{ route('admin.copy_trading.billing.accounts.initialize', ['copyTradeBrokerAccount' => $account]) }}"
    >
        @csrf
        <button class="primary-btn" type="submit">
            Initialize / Check Billing
        </button>
    </form>

    <button
        class="secondary-btn"
        type="button"
        onclick="document.getElementById('externalDepositBillingForm').scrollIntoView({behavior:'smooth'});"
    >
        External Deposit Upgrade
    </button>

    <form
        method="POST"
        action="{{ route('admin.copy_trading.billing.accounts.sweep', ['copyTradeBrokerAccount' => $account]) }}"
    >
        @csrf
        <button class="secondary-btn" type="submit">
            Run Account Sweep
        </button>
    </form>
</div>
@endif

<section class="panel admin-form-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">ACCOUNT CONTEXT</div>
            <h2>Billing Status</h2>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>Account Status</label>
            <input
                value="{{ str_replace('_', ' ', $account->status) }}"
                readonly
            >
        </div>

        <div class="form-group">
            <label>Benefit</label>
            <input
                value="{{ $account->accountBenefit ? str_replace('_', ' ', $account->accountBenefit->benefit_type) : 'Not assigned yet' }}"
                readonly
            >
        </div>

        <div class="form-group">
            <label>Market</label>
            <input
                value="{{ $account->brokerMarket?->market_category ?? '—' }}"
                readonly
            >
        </div>

        <div class="form-group">
            <label>Currency</label>
            <input value="USD" readonly>
        </div>
    </div>
</section>


@if($canManage && $account->isActive())
<section
    id="externalDepositBillingForm"
    class="panel admin-form-panel"
    style="margin-top:18px;"
>
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">
                EXTERNAL DEPOSIT EVIDENCE
            </div>
            <h2>Evaluate Higher-Tier Top-Up</h2>
        </div>
    </div>

    <div class="admin-form-callout" style="margin-bottom:18px;">
        <div class="admin-form-callout-icon">◇</div>
        <div>
            Only a verified external deposit / top-up may trigger a mid-period
            tier upgrade. Trading P/L, withdrawals and ordinary balance movement
            must not be submitted here.
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('admin.copy_trading.billing.accounts.upgrade', ['copyTradeBrokerAccount' => $account]) }}"
    >
        @csrf

        <div class="form-grid">
            <div class="form-group">
                <label>Verified Balance Snapshot</label>
                <select
                    name="balance_snapshot_uuid"
                    required
                >
                    <option value="">Select snapshot</option>

                    @foreach($account->verifiedBalanceSnapshots->take(20) as $snapshot)
                        <option
                            value="{{ $snapshot->uuid }}"
                            @selected(old('balance_snapshot_uuid') === $snapshot->uuid)
                        >
                            {{ $snapshot->currency }}
                            {{ number_format($snapshot->verified_billable_balance_minor / 100, 2) }}
                            ·
                            {{ $snapshot->observed_at?->format('Y-m-d H:i:s') }}
                            ·
                            {{ $snapshot->source }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>External Deposit Amount (USD)</label>
                <input
                    type="text"
                    inputmode="decimal"
                    name="deposit_amount_usd"
                    value="{{ old('deposit_amount_usd') }}"
                    placeholder="1000.00"
                    required
                >
            </div>

            <div
                class="form-group"
                style="grid-column:1/-1;"
            >
                <label>Broker / Processor Source Reference</label>
                <input
                    type="text"
                    name="source_reference"
                    maxlength="191"
                    value="{{ old('source_reference') }}"
                    placeholder="Optional internal evidence reference"
                >
            </div>
        </div>

        <div class="admin-form-actions">
            <button
                class="secondary-btn"
                type="submit"
            >
                Record Deposit & Evaluate Tier
            </button>
        </div>
    </form>
</section>
@endif

<section
    class="panel users-panel"
    style="margin-top:18px;"
>
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">BILLING CYCLES</div>
            <h2>Cycle History</h2>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Basis</th>
                    <th>Period</th>
                    <th>Tier</th>
                    <th>Full Fee</th>
                    <th>Grace Until</th>
                </tr>
            </thead>
            <tbody>
            @forelse($account->billingCycles as $cycle)
                <tr>
                    <td>{{ $cycle->status }}</td>
                    <td>{{ $cycle->cycle_basis }}</td>
                    <td>
                        {{ $cycle->period_start?->format('Y-m-d H:i') }}
                        →
                        {{ $cycle->period_end?->format('Y-m-d H:i') }}
                    </td>
                    <td>
                        {{ $cycle->active_tier_code ?? '—' }}
                    </td>
                    <td>
                        USD
                        {{ number_format($cycle->active_full_fee_minor / 100, 2) }}
                    </td>
                    <td>
                        {{ $cycle->grace_expires_at?->format('Y-m-d H:i') ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td
                        colspan="6"
                        style="text-align:center;padding:24px;"
                    >
                        No billing cycles yet.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section
    class="panel users-panel"
    style="margin-top:18px;"
>
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">INVOICES</div>
            <h2>Account Invoice Register</h2>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Balance / Tier Evidence</th>
                    <th>Payment Reconciliation</th>
                </tr>
            </thead>
            <tbody>
            @forelse($account->billingInvoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_number }}</td>
                    <td>{{ $invoice->invoice_type }}</td>
                    <td>
                        USD
                        {{ number_format($invoice->total_minor / 100, 2) }}
                    </td>
                    <td>{{ $invoice->status }}</td>
                    <td>
                        @if($invoice->snapshot)
                            Actual VBB:
                            USD
                            {{ number_format($invoice->snapshot->verified_billable_balance_minor / 100, 2) }}
                            <br>
                            Billing-eligible:
                            USD
                            {{ number_format($invoice->snapshot->billing_eligible_balance_minor / 100, 2) }}
                            <br>
                            <small>
                                {{ $invoice->snapshot->tier_code }}
                                ·
                                {{ $invoice->snapshot->formula_code }}
                                ·
                                {{ $invoice->snapshot->ratio_numerator }}/{{ $invoice->snapshot->ratio_denominator }}
                            </small>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($invoice->isPaid())
                            PAID ·
                            {{ $invoice->paid_at?->format('Y-m-d H:i') }}
                        @elseif($canReconcile)
                            <form
                                method="POST"
                                action="{{ route('admin.copy_trading.billing.invoices.link-payment', ['copyTradeBillingInvoice' => $invoice]) }}"
                            >
                                @csrf
                                <input
                                    type="text"
                                    name="payment_uuid"
                                    placeholder="Cleared payment UUID"
                                    required
                                    style="min-width:240px;"
                                >
                                <button
                                    class="secondary-btn"
                                    type="submit"
                                >
                                    Link Cleared Payment
                                </button>
                            </form>
                        @else
                            OPEN
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td
                        colspan="6"
                        style="text-align:center;padding:24px;"
                    >
                        No invoices yet.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@if($canAdjust)
<section
    class="panel admin-form-panel"
    style="margin-top:18px;"
>
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">
                IMMUTABLE ADJUSTMENT EVIDENCE
            </div>
            <h2>Record Exception / Correction Evidence</h2>
        </div>
    </div>

    <div class="admin-form-callout" style="margin-bottom:18px;">
        <div class="admin-form-callout-icon">!</div>
        <div>
            This creates an audit record only. It does not overwrite an issued
            invoice, issue a refund, or move money automatically.
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('admin.copy_trading.billing.accounts.adjustments.store', ['copyTradeBrokerAccount' => $account]) }}"
    >
        @csrf

        <div class="form-grid">
            <div class="form-group">
                <label>Invoice</label>
                <select name="billing_invoice_uuid">
                    <option value="">Account-level evidence</option>
                    @foreach($account->billingInvoices as $invoice)
                        <option value="{{ $invoice->uuid }}">
                            {{ $invoice->invoice_number }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label>Adjustment Type</label>
                <select
                    name="adjustment_type"
                    required
                >
                    <option value="CORRECTION_EVIDENCE">
                        Correction Evidence
                    </option>
                    <option value="DISPUTE_EVIDENCE">
                        Dispute Evidence
                    </option>
                    <option value="PROCESSOR_REVERSAL_EVIDENCE">
                        Processor Reversal Evidence
                    </option>
                    <option value="CONFIRMED_BILLING_ERROR">
                        Confirmed Billing Error
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>Reference Amount (minor USD units)</label>
                <input
                    type="number"
                    name="amount_minor"
                    value="0"
                    required
                >
            </div>

            <div
                class="form-group"
                style="grid-column:1/-1;"
            >
                <label>Reason</label>
                <textarea
                    name="reason"
                    minlength="10"
                    maxlength="4000"
                    required
                ></textarea>
            </div>

            <div
                class="form-group"
                style="grid-column:1/-1;"
            >
                <label>Evidence Note</label>
                <textarea
                    name="evidence_note"
                    maxlength="4000"
                ></textarea>
            </div>
        </div>

        <div class="admin-form-actions">
            <button
                class="secondary-btn"
                type="submit"
            >
                Record Immutable Evidence
            </button>
        </div>
    </form>
</section>
@endif

<section
    class="panel users-panel"
    style="margin-top:18px;"
>
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">ADJUSTMENT HISTORY</div>
            <h2>Immutable Evidence Register</h2>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Invoice</th>
                    <th>Reference Amount</th>
                    <th>Admin</th>
                    <th>Recorded</th>
                </tr>
            </thead>
            <tbody>
            @forelse($account->billingAdjustments as $adjustment)
                <tr>
                    <td>{{ $adjustment->adjustment_type }}</td>
                    <td>
                        {{ $adjustment->invoice?->invoice_number ?? 'Account level' }}
                    </td>
                    <td>
                        {{ $adjustment->amount_minor }}
                    </td>
                    <td>
                        {{ $adjustment->createdByAdmin?->name ?? '—' }}
                    </td>
                    <td>
                        {{ $adjustment->created_at?->format('Y-m-d H:i') ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td
                        colspan="5"
                        style="text-align:center;padding:24px;"
                    >
                        No adjustment evidence recorded.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<div
    class="admin-form-actions"
    style="margin-top:18px;"
>
    <a
        class="secondary-btn"
        href="{{ route('admin.copy_trading.billing.index') }}"
    >
        Billing Register
    </a>

    <a
        class="secondary-btn"
        href="{{ route('admin.copy_trading.accounts.activation.show', ['copyTradeBrokerAccount' => $account]) }}"
    >
        Activation & Eligibility
    </a>
</div>
@endsection
