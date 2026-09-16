@extends('admin.layouts.app')

@section('title', 'Funded No Trade Zone | TradeX Administration')
@section('page-title', 'Funded No Trade Zone')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/funded-account-advisor.css') }}">
@endpush

@section('content')
<div class="funded-advisor-page">
    <div class="page-header funded-advisor-header">
        <div>
            <div class="page-eyebrow">ADMIN-31 · TRADING TOOLS</div>
            <h1>Funded <span>No Trade Zone</span></h1>
            <p>Company-specific economic-news restrictions for challenge, verification and funded account phases.</p>
        </div>

        @if($canManageRules)
            <a class="primary-btn" href="{{ route('admin.funded_no_trade_zone.rules.create') }}">+ New Rule</a>
        @endif
    </div>

    <section class="panel funded-advisor-callout">
        <strong>Shared compliance engine</strong>
        <p>Rules maintained here are designed to feed the same TradeX no-trade-zone calculation used by customer screens, future mobile API responses and Trading Routine PDF snapshots. Economic event times remain UTC at the backend.</p>
    </section>

    <div class="funded-advisor-result-grid">
        <section class="panel funded-advisor-metric is-primary">
            <span>Active Rules</span>
            <strong>{{ number_format($activeRuleCount) }}</strong>
            <small>Currently enabled funded-company rules.</small>
        </section>
        <section class="panel funded-advisor-metric">
            <span>Companies Covered</span>
            <strong>{{ number_format($partnerRuleCount) }}</strong>
            <small>Funded account partners with rule coverage.</small>
        </section>
        <section class="panel funded-advisor-metric">
            <span>Verification Required</span>
            <strong>{{ number_format($needsVerificationCount) }}</strong>
            <small>Missing or older than 30 days.</small>
        </section>
        <section class="panel funded-advisor-metric">
            <span>High Impact · 24H</span>
            <strong>{{ number_format($upcomingHighImpactCount) }}</strong>
            <small>Upcoming economic events available to the engine.</small>
        </section>
    </div>

    <section class="panel funded-advisor-partner-table-panel">
        <div class="funded-advisor-panel-heading">
            <div>
                <div class="panel-eyebrow">RULE LIBRARY</div>
                <h2>Funded Company News Restrictions</h2>
            </div>
            <span>{{ str_pad((string) $rules->total(), 2, '0', STR_PAD_LEFT) }}</span>
        </div>

        <div class="funded-advisor-table-wrap">
            <table class="funded-advisor-table">
                <thead>
                    <tr>
                        <th>Company / Program</th>
                        <th>Phase</th>
                        <th>Policy</th>
                        <th>Impact</th>
                        <th>No Trade Window</th>
                        <th>Verified</th>
                        <th>Status</th>
                        @if($canManageRules)<th>Manage</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr>
                            <td>
                                <strong>{{ $rule->partner?->name ?? 'Deleted Partner' }}</strong>
                                <small>{{ $rule->program_name ?: 'All programs' }}</small>
                            </td>
                            <td>{{ $rule->phase }}</td>
                            <td><span class="funded-advisor-status-pill {{ $rule->policy === 'ALLOWED' ? 'is-safe' : 'is-muted' }}">{{ $rule->policy }}</span></td>
                            <td>{{ implode(', ', (array) $rule->impact_levels) ?: 'All' }}</td>
                            <td>{{ $rule->minutes_before }}m before · {{ $rule->minutes_after }}m after</td>
                            <td>
                                {{ $rule->verified_at?->format('Y-m-d') ?? 'Not verified' }}
                                @if($rule->source_reference)<small>{{ $rule->source_reference }}</small>@endif
                            </td>
                            <td><span class="funded-advisor-status-pill {{ $rule->is_active ? 'is-safe' : 'is-muted' }}">{{ $rule->is_active ? 'ACTIVE' : 'DISABLED' }}</span></td>
                            @if($canManageRules)
                                <td><a class="secondary-btn" href="{{ route('admin.funded_no_trade_zone.rules.edit', $rule) }}">Edit</a></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="funded-advisor-empty">No funded no-trade rules yet. Add the first verified company rule.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rules->hasPages())
            <div style="margin-top:16px">{{ $rules->links() }}</div>
        @endif
    </section>

    <section class="panel funded-advisor-callout">
        <strong>Next integration boundary</strong>
        <p>Customer funded-account selection will map these rules against ADMIN-29 economic events. The shared service already returns UTC start/end times, affected instruments, rule provenance and trading-action restrictions for later API/PDF consumption.</p>
    </section>
</div>
@endsection
