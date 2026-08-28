@extends('admin.layouts.app')

@section('title', 'Copy Trade Activation & Eligibility')
@section('page-title', 'Copy Trade Activation & Eligibility')

@section('content')
@php
    $snapshot = $account->latestVerifiedBalanceSnapshot;
    $evaluation = $account->latestActivationEvaluation;
    $benefit = $account->accountBenefit;
    $latestPeriod = $account->entitlementPeriods->sortByDesc('starts_at')->first();

    $minimumUsd = number_format($minimumBalanceMinor / 100, 2);

    $gateLabels = [
        'BROKER_PARTNER_ELIGIBILITY' => 'Broker / Partner Eligibility',
        'VERIFIED_BILLABLE_BALANCE' => 'Verified Billable Balance',
        'ACCOUNT_BENEFIT_ASSIGNMENT' => 'Account Benefit / Slot',
        'ENTITLEMENT' => 'Copy Trade Entitlement',
    ];
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">COPY TRADING · ACTIVATION CONTROL</div>
        <h1>Activation & <span>Eligibility</span></h1>
        <p>
            Evaluate the frozen Copy Trade activation gates without changing
            the broker-verification decision or bypassing commercial controls.
        </p>
    </div>
</div>

@if(session('success'))
    <div class="admin-form-callout" style="margin-bottom:18px;">
        <div class="admin-form-callout-icon" aria-hidden="true">✓</div>
        <div><strong>{{ session('success') }}</strong></div>
    </div>
@endif

@if($errors->any())
    <div class="admin-form-callout" style="margin-bottom:18px;">
        <div class="admin-form-callout-icon" aria-hidden="true">!</div>
        <div>
            <strong>Action blocked</strong>
            <ul style="margin:8px 0 0 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="admin-form-callout">
    <div class="admin-form-callout-icon" aria-hidden="true">◇</div>
    <div>
        <strong>Verification is not activation</strong>
        VERIFIED confirms the broker relationship. ACTIVE is written only when
        every ADMIN-23 gate passes. Minimum Verified Billable Balance:
        {{ $requiredCurrency }} {{ $minimumUsd }}.
        Floating equity / open-position P&amp;L is not used here.
    </div>
</div>

<section class="panel admin-form-panel" style="margin-top:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">ACCOUNT CONTEXT</div>
            <h2>{{ $account->user?->name ?? 'Unknown customer' }}</h2>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>Customer Email</label>
            <input value="{{ $account->user?->email ?? '—' }}" readonly>
        </div>

        <div class="form-group">
            <label>Broker</label>
            <input value="{{ $account->broker?->name ?? '—' }}" readonly>
        </div>

        <div class="form-group">
            <label>Market</label>
            <input value="{{ $account->brokerMarket?->market_category ?? '—' }}" readonly>
        </div>

        <div class="form-group">
            <label>Account</label>
            <input value="{{ $account->maskedAccountNumber() }}" readonly>
        </div>

        <div class="form-group">
            <label>Status</label>
            <input value="{{ str_replace('_', ' ', $account->status) }}" readonly>
        </div>

        <div class="form-group">
            <label>Verified At</label>
            <input value="{{ $account->verified_at?->format('Y-m-d H:i:s') ?? '—' }}" readonly>
        </div>
    </div>
</section>

<section class="panel admin-form-panel" style="margin-top:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">VERIFIED BILLABLE BALANCE</div>
            <h2>Balance Evidence</h2>
        </div>
    </div>

    @if($snapshot)
        <div class="admin-form-callout" style="margin-bottom:18px;">
            <div class="admin-form-callout-icon" aria-hidden="true">$</div>
            <div>
                <strong>
                    {{ $snapshot->currency }}
                    {{ number_format($snapshot->verified_billable_balance_minor / 100, 2) }}
                </strong>
                observed {{ $snapshot->observed_at?->format('Y-m-d H:i:s') ?? '—' }}
                · source {{ str_replace('_', ' ', $snapshot->source) }}.
            </div>
        </div>
    @else
        <p>No Verified Billable Balance snapshot has been recorded yet.</p>
    @endif

    @if($canActivate && $account->hasVerifiedBrokerRelationship())
        <form
            method="POST"
            action="{{ route('admin.copy_trading.accounts.activation.balance.store', ['copyTradeBrokerAccount' => $account]) }}"
        >
            @csrf

            <div class="form-grid">
                <div class="form-group">
                    <label for="verified_billable_balance_usd">
                        Verified Billable Balance (USD)
                    </label>
                    <input
                        id="verified_billable_balance_usd"
                        name="verified_billable_balance_usd"
                        type="text"
                        inputmode="decimal"
                        placeholder="250.00"
                        value="{{ old('verified_billable_balance_usd') }}"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="observed_at">Observed At</label>
                    <input
                        id="observed_at"
                        name="observed_at"
                        type="datetime-local"
                        value="{{ old('observed_at') }}"
                    >
                </div>

                <div class="form-group" style="grid-column:1/-1;">
                    <label for="source_reference">
                        Broker Portal / Source Reference
                    </label>
                    <input
                        id="source_reference"
                        name="source_reference"
                        type="text"
                        maxlength="191"
                        value="{{ old('source_reference') }}"
                        placeholder="Optional internal portal reference"
                    >
                </div>
            </div>

            <div class="admin-form-actions">
                <button class="secondary-btn" type="submit">
                    Record Balance Snapshot
                </button>
            </div>
        </form>
    @endif
</section>

<section class="panel admin-form-panel" style="margin-top:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">BENEFIT & ENTITLEMENT</div>
            <h2>Commercial Gate Preview</h2>
        </div>
    </div>

    @if($benefit)
        <div class="form-grid">
            <div class="form-group">
                <label>Account Slot</label>
                <input value="{{ $benefit->account_slot }}" readonly>
            </div>

            <div class="form-group">
                <label>Benefit Type</label>
                <input value="{{ str_replace('_', ' ', $benefit->benefit_type) }}" readonly>
            </div>

            <div class="form-group">
                <label>Cohort Position</label>
                <input value="{{ $benefit->cohort_position }}" readonly>
            </div>

            <div class="form-group">
                <label>Entitlement Period</label>
                <input
                    value="{{ $latestPeriod ? str_replace('_', ' ', $latestPeriod->entitlement_type) : '—' }}"
                    readonly
                >
            </div>
        </div>
    @elseif($benefitPreview)
        <div class="form-grid">
            <div class="form-group">
                <label>Preview Slot</label>
                <input value="{{ $benefitPreview['account_slot'] ?? '—' }}" readonly>
            </div>

            <div class="form-group">
                <label>Preview Benefit</label>
                <input
                    value="{{ isset($benefitPreview['benefit_type']) ? str_replace('_', ' ', (string) $benefitPreview['benefit_type']) : '—' }}"
                    readonly
                >
            </div>

            <div class="form-group">
                <label>Entitlement Gate</label>
                <input
                    value="{{ ($benefitPreview['entitlement_passed'] ?? false) ? 'PASS' : 'BLOCKED' }}"
                    readonly
                >
            </div>

            <div class="form-group">
                <label>Reason</label>
                <input
                    value="{{ str_replace('_', ' ', (string) ($benefitPreview['entitlement_reason_code'] ?? $benefitPreview['benefit_reason_code'] ?? '—')) }}"
                    readonly
                >
            </div>
        </div>
    @else
        <p>
            Benefit preview becomes available after the broker account reaches
            a verified relationship state.
        </p>
    @endif
</section>

<section class="panel users-panel" style="margin-top:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">LATEST EVALUATION</div>
            <h2>Activation Gate Results</h2>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Gate</th>
                    <th>Result</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
            @if($evaluation)
                @foreach($evaluation->gates as $gate)
                    <tr>
                        <td>{{ $gateLabels[$gate->gate_code] ?? str_replace('_', ' ', $gate->gate_code) }}</td>
                        <td>
                            <span class="user-status {{ $gate->passed ? 'status-active' : 'status-blocked' }}">
                                <span class="user-status-dot"></span>
                                {{ $gate->passed ? 'PASS' : 'BLOCKED' }}
                            </span>
                        </td>
                        <td>{{ str_replace('_', ' ', $gate->reason_code) }}</td>
                    </tr>
                @endforeach
            @else
                @foreach($gateLabels as $code => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>Not evaluated</td>
                        <td>—</td>
                    </tr>
                @endforeach
            @endif
            </tbody>
        </table>
    </div>

    @if($canActivate && $account->isVerified())
        <form
            method="POST"
            action="{{ route('admin.copy_trading.accounts.activation.evaluate', ['copyTradeBrokerAccount' => $account]) }}"
            style="padding:18px 20px;"
        >
            @csrf
            <button class="primary-btn" type="submit">
                Evaluate & Activate
            </button>
        </form>
    @elseif($account->isActive())
        <div style="padding:18px 20px;">
            <span class="user-status status-active">
                <span class="user-status-dot"></span>
                ACTIVE
            </span>
            <span style="margin-left:8px;">
                Activated {{ $account->activated_at?->format('Y-m-d H:i:s') ?? '—' }}
            </span>
        </div>
    @endif
</section>

<div class="admin-form-actions" style="margin-top:18px;">
    <a
        class="secondary-btn"
        href="{{ route('admin.copy_trading.accounts.index') }}"
    >
        Back to Accounts
    </a>

    <a
        class="secondary-btn"
        href="{{ route('admin.copy_trading.accounts.show', ['copyTradeBrokerAccount' => $account]) }}"
    >
        Verification Detail
    </a>
</div>
@endsection
