@extends('admin.layouts.app')

@section('title', 'Trading Routine | TradeX Administration')
@section('page-title', 'Trading Routine')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/trading-routine.css') }}">
@endpush

@section('content')
<div class="trading-routine-page">
    <div class="page-header trading-routine-header">
        <div>
            <div class="page-eyebrow">TRADING TOOLS</div>
            <h1>Daily Trading <span>Routine</span></h1>
            <p>Monitor customer-owned morning plans and WhatsApp PDF submission outcomes. Customer routine inputs are managed from the authenticated TradeX app, not from Administration.</p>
        </div>
        <div class="trading-routine-health">
            <span class="trading-routine-pill {{ $automationEnabled ? '' : 'is-off' }}">Automation {{ $automationEnabled ? 'ON' : 'OFF' }}</span>
            <span class="trading-routine-pill {{ strtolower($whatsappDriver) === 'meta' && $templateConfigured ? '' : 'is-off' }}">WhatsApp {{ strtoupper($whatsappDriver) }} · Template {{ $templateConfigured ? 'READY' : 'MISSING' }}</span>
        </div>
    </div>

    <div class="trading-routine-stats">
        <div class="trading-routine-stat"><span>Enabled Customers</span><strong>{{ number_format($stats['enabled_customers']) }}</strong></div>
        <div class="trading-routine-stat"><span>WhatsApp Opt-ins</span><strong>{{ number_format($stats['whatsapp_opted_in']) }}</strong></div>
        <div class="trading-routine-stat"><span>Today's Plans</span><strong>{{ number_format($stats['today_plans']) }}</strong></div>
        <div class="trading-routine-stat"><span>Provider Accepted Today</span><strong>{{ number_format($stats['today_accepted']) }}</strong></div>
        <div class="trading-routine-stat"><span>Failed Deliveries</span><strong>{{ number_format($stats['failed_deliveries']) }}</strong></div>
    </div>

    <div class="trading-routine-note">
        <strong>Customer-owned configuration</strong>
        <p>Name, email and WhatsApp destination come from the authenticated customer profile. Admins monitor delivery and PDF history only; they do not fill a customer's routine or silently opt a customer into WhatsApp.</p>
    </div>

    <section class="panel trading-routine-table-panel">
        <div class="trading-routine-table-head">
            <div>
                <div class="panel-eyebrow">RECENT</div>
                <h2>Daily Plans & Delivery State</h2>
            </div>
        </div>

        @if($recentPlans->count())
            <div class="trading-routine-table-wrap">
                <table class="trading-routine-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Plan Date</th>
                            <th>Timezone</th>
                            <th>Risk / Trade</th>
                            <th>Max Trades</th>
                            <th>WhatsApp</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentPlans as $plan)
                            @php
                                $routine = (array) $plan->plan_json;
                                $status = strtoupper((string) ($plan->delivery?->status ?? 'PENDING'));
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $plan->customer_name_snapshot }}</strong>
                                    <small>{{ $plan->customer_email_snapshot ?: 'No email snapshot' }}</small>
                                </td>
                                <td>{{ $plan->plan_date?->format('Y-m-d') }}</td>
                                <td>{{ $plan->timezone_snapshot }}</td>
                                <td>
                                    ${{ number_format((float) ($routine['risk_per_trade_amount'] ?? 0), 2) }}
                                    <small>{{ number_format((float) ($routine['risk_per_trade_percent'] ?? 0), 2) }}%</small>
                                </td>
                                <td>{{ (int) ($routine['max_trades'] ?? 0) }}</td>
                                <td>
                                    <span class="trading-routine-status is-{{ strtolower($status) }}">{{ $status }}</span>
                                    @if($plan->delivery?->failure_code)
                                        <small>{{ $plan->delivery->failure_code }}</small>
                                    @endif
                                </td>
                                <td>
                                    <a class="trading-routine-pdf" href="{{ route('admin.trading_routine.plans.pdf', $plan) }}">Download PDF</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="trading-routine-pagination">{{ $recentPlans->links() }}</div>
        @else
            <div class="trading-routine-empty">No customer trading routine plans have been generated yet.</div>
        @endif
    </section>
</div>
@endsection
