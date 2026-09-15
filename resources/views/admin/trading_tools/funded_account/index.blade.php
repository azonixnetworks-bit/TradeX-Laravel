@extends('admin.layouts.app')

@section('title', 'Funded Account Advisor | TradeX Administration')
@section('page-title', 'Funded Account Advisor')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/funded-account-advisor.css') }}">
@endpush

@section('content')
<div class="funded-advisor-page">
    <div class="page-header funded-advisor-header">
        <div>
            <div class="page-eyebrow">TRADING TOOLS</div>
            <h1>Funded Account <span>Advisor</span></h1>
            <p>Customer-generated risk plans, report oversight and funded-account partner management.</p>
        </div>

        @if($canManagePartners)
            <a class="secondary-btn" href="{{ route('admin.funded_advisor.partners.index') }}">Manage Funded Partners</a>
        @endif
    </div>

    <section class="panel funded-advisor-callout">
        <strong>Customer-owned workflow</strong>
        <p>Admins do not enter customer identity or funded-account risk inputs here. The customer completes the advisor from the authenticated TradeX mobile experience. Customer name and email are read automatically from the logged-in TradeX account and saved into the generated report snapshot.</p>
    </section>

    <div class="funded-advisor-grid">
        <section class="panel funded-advisor-side-panel">
            <div class="panel-eyebrow">REPORTS</div>
            <h2>{{ number_format($reportCount) }}</h2>
            <p>Total funded-account advisor reports stored by TradeX.</p>
        </section>

        <section class="panel funded-advisor-side-panel">
            <div class="panel-eyebrow">ACTIVE PARTNERS</div>
            <h2>{{ number_format($activePartnerCount) }}</h2>
            <p>Active funded-account partners available to customers.</p>
        </section>

        <section class="panel funded-advisor-side-panel">
            <div class="panel-eyebrow">CUSTOMER IDENTITY</div>
            <h2>Auto</h2>
            <p>Name and email come from the authenticated customer profile; admins cannot override them.</p>
        </section>
    </div>

    <section class="panel funded-advisor-form-panel">
        <div class="funded-advisor-panel-heading">
            <div>
                <div class="panel-eyebrow">RECENT</div>
                <h2>Customer-Generated Reports</h2>
            </div>
            <span>01</span>
        </div>

        @forelse($recentReports as $report)
            @php
                $output = (array) $report->output_json;
                $status = (string) ($output['status'] ?? 'UNKNOWN');
            @endphp

            <a class="funded-advisor-report-link" href="{{ route('admin.funded_advisor.reports.show', $report) }}">
                <strong>{{ $report->customer_name }}</strong>
                <span>
                    {{ $report->customer_email ?: 'No email snapshot' }}
                    · {{ $report->prop_firm_name ?: 'Manual Firm Rules' }}
                    · {{ $status }}
                </span>
                <small>
                    {{ $report->generated_at?->format('Y-m-d H:i') }}
                    @if($report->user_id)
                        · Customer generated
                    @else
                        · Legacy admin generated
                    @endif
                </small>
            </a>
        @empty
            <div class="funded-advisor-empty">No funded-account advisor reports generated yet.</div>
        @endforelse
    </section>

    <section class="panel funded-advisor-callout">
        <strong>Admin responsibility</strong>
        <p>Maintain funded partners, referral URLs, promo details and review generated customer reports. Customer trading inputs remain customer-owned and are never entered by the admin on this page.</p>
    </section>
</div>
@endsection
