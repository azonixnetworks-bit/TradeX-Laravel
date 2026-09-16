@extends('admin.layouts.app')

@section('title', 'Funded Advisor Report | TradeX Administration')
@section('page-title', 'Funded Account Advisor')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/funded-account-advisor.css') }}">
@endpush

@section('content')
@php
    $status = (string) ($output['status'] ?? 'UNKNOWN');
    $statusClass = match($status) {
        'SAFE' => 'is-safe',
        'CAUTION' => 'is-caution',
        'DANGER' => 'is-danger',
        'TARGET_REACHED' => 'is-safe',
        default => '',
    };
@endphp

<div class="funded-advisor-page">
    <div class="page-header funded-advisor-header">
        <div>
            <div class="page-eyebrow">CUSTOMER RISK PLAN</div>
            <h1>{{ $report->customer_name }} <span>Funded Plan</span></h1>
            <p>{{ $report->prop_firm_name ?: 'Manual funded-account rules' }} · {{ str_replace('_', ' ', (string) ($input['phase'] ?? '')) }}</p>
        </div>
        <div class="funded-advisor-header-actions">
            <a class="secondary-btn" href="{{ route('admin.funded_advisor.index') }}">New Plan</a>
            <a class="primary-btn" href="{{ route('admin.funded_advisor.reports.pdf', $report) }}">Download PDF</a>
        </div>
    </div>

    <section class="panel funded-advisor-headline {{ $statusClass }}">
        <div>
            <span>TRADEX RECOMMENDATION</span>
            <h2>{{ $output['headline'] ?? 'Risk plan generated.' }}</h2>
        </div>
        <strong>{{ str_replace('_', ' ', $status) }}</strong>
    </section>

    <div class="funded-advisor-result-grid">
        <div class="panel funded-advisor-metric is-primary">
            <span>Risk / Trade</span>
            <strong>${{ number_format((float) ($output['recommended_risk_amount'] ?? 0), 2) }}</strong>
            <small>{{ number_format((float) ($output['recommended_risk_percent'] ?? 0), 2) }}% of account</small>
        </div>
        <div class="panel funded-advisor-metric is-primary">
            <span>Recommended Entries</span>
            <strong>{{ $output['recommended_entries'] ?? 0 }}</strong>
            <small>Requested max: {{ $output['requested_max_entries'] ?? 0 }}</small>
        </div>
        <div class="panel funded-advisor-metric">
            <span>Planned Daily Exposure</span>
            <strong>${{ number_format((float) ($output['planned_daily_exposure'] ?? 0), 2) }}</strong>
            <small>Stop when plan budget is reached</small>
        </div>
        <div class="panel funded-advisor-metric">
            <span>Minimum R:R</span>
            <strong>1 : {{ number_format((float) ($output['minimum_rr'] ?? 0), 2) }}</strong>
            <small>Target/win: ${{ number_format((float) ($output['target_profit_per_win'] ?? 0), 2) }}</small>
        </div>
        <div class="panel funded-advisor-metric">
            <span>Daily Loss Remaining</span>
            <strong>${{ number_format((float) ($output['daily_loss_remaining'] ?? 0), 2) }}</strong>
            <small>{{ number_format((float) ($output['daily_loss_used_percent'] ?? 0), 1) }}% of limit used</small>
        </div>
        <div class="panel funded-advisor-metric">
            <span>Overall DD Remaining</span>
            <strong>${{ number_format((float) ($output['overall_drawdown_remaining'] ?? 0), 2) }}</strong>
            <small>Threshold: ${{ number_format((float) ($output['drawdown_threshold'] ?? 0), 2) }}</small>
        </div>
        <div class="panel funded-advisor-metric">
            <span>Remaining Profit Target</span>
            <strong>${{ number_format((float) ($output['remaining_profit_target'] ?? 0), 2) }}</strong>
            <small>{{ number_format((float) ($output['target_progress_percent'] ?? 0), 1) }}% completed</small>
        </div>
        <div class="panel funded-advisor-metric">
            <span>Loss Capacity</span>
            <strong>{{ $output['losses_before_rule_capacity'] ?? 0 }}</strong>
            <small>Full base-risk losses before current firm capacity</small>
        </div>
    </div>

    <div class="funded-advisor-result-layout">
        <section class="panel funded-advisor-plan-panel">
            <div class="funded-advisor-panel-heading">
                <div>
                    <div class="panel-eyebrow">ENTRY DISCIPLINE</div>
                    <h2>Entry-by-Entry Plan</h2>
                </div>
                <span>01</span>
            </div>

            @if(empty($output['entry_plan']))
                <div class="funded-advisor-alert is-danger">No new trade entry is recommended under the current drawdown capacity.</div>
            @else
                <div class="funded-advisor-entry-list">
                    @foreach($output['entry_plan'] as $entry)
                        <div class="funded-advisor-entry-row">
                            <div><span>ENTRY</span><strong>{{ $entry['entry'] }}</strong></div>
                            <div><span>RISK</span><strong>${{ number_format((float) $entry['risk_amount'], 2) }}</strong></div>
                            <div><span>RISK SCALE</span><strong>{{ number_format(((float) $entry['risk_factor']) * 100, 0) }}%</strong></div>
                            <p>{{ $entry['instruction'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="funded-advisor-stop-card">
                <span>PLANNED STOP-TRADING REFERENCE</span>
                <strong>${{ number_format((float) ($output['daily_stop_reference'] ?? 0), 2) }}</strong>
                <small>Reference basis: {{ $output['daily_loss_basis'] ?? '—' }}. Confirm the firm's exact daily reset and loss calculation before trading.</small>
            </div>
        </section>

        <aside class="panel funded-advisor-rule-panel">
            <div class="funded-advisor-panel-heading">
                <div>
                    <div class="panel-eyebrow">CAPACITY</div>
                    <h2>Rule Summary</h2>
                </div>
                <span>02</span>
            </div>
            <dl class="funded-advisor-rule-list">
                <div><dt>Account Size</dt><dd>${{ number_format((float) ($input['account_size'] ?? 0), 2) }}</dd></div>
                <div><dt>Daily Limit</dt><dd>${{ number_format((float) ($output['daily_limit_amount'] ?? 0), 2) }}</dd></div>
                <div><dt>Daily Loss Used</dt><dd>${{ number_format((float) ($output['daily_loss_used'] ?? 0), 2) }}</dd></div>
                <div><dt>Max Drawdown</dt><dd>${{ number_format((float) ($output['max_drawdown_amount'] ?? 0), 2) }}</dd></div>
                <div><dt>Drawdown Type</dt><dd>{{ $output['drawdown_type'] ?? '—' }}</dd></div>
                <div><dt>Drawdown Basis</dt><dd>{{ $output['drawdown_basis'] ?? '—' }}</dd></div>
                <div><dt>Safety Reserve</dt><dd>{{ number_format((float) ($output['safety_reserve_percent'] ?? 0), 0) }}%</dd></div>
                <div><dt>Trading Days Left</dt><dd>{{ $output['remaining_minimum_trading_days'] ?? 0 }}</dd></div>
            </dl>
        </aside>
    </div>

    @if(!empty($output['warnings']))
        <section class="panel funded-advisor-warning-panel">
            <strong>Risk Warnings</strong>
            @foreach($output['warnings'] as $warning)
                <p>• {{ $warning }}</p>
            @endforeach
        </section>
    @endif

    <section class="panel funded-advisor-partner-card">
        <div>
            <div class="panel-eyebrow">TRADEX PARTNER</div>
            @if($report->partner_name_snapshot)
                <h2>{{ $report->partner_name_snapshot }}</h2>
                @if($report->partner_offer_snapshot)<p>{{ $report->partner_offer_snapshot }}</p>@endif
                @if($report->promo_code_snapshot)<div class="funded-advisor-promo">Promo Code: <strong>{{ $report->promo_code_snapshot }}</strong></div>@endif
                <small>{{ $report->affiliate_disclosure_snapshot }}</small>
            @else
                <h2>No Partner Attached</h2>
                <p>This risk plan was generated without an affiliate recommendation.</p>
            @endif
        </div>
        @if($report->affiliate_url_snapshot)
            <a class="primary-btn" href="{{ $report->affiliate_url_snapshot }}" target="_blank" rel="noopener noreferrer sponsored nofollow">Open Partner Account</a>
        @endif
    </section>

    <section class="panel funded-advisor-disclaimer">
        <strong>Risk Planning Notice</strong>
        <p>{{ $output['disclaimer'] ?? config('funded_account_advisor.disclaimer') }}</p>
    </section>
</div>
@endsection
