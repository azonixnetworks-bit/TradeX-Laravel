@extends('admin.layouts.app')

@section('title', 'Affiliate Detail')
@section('page-title', 'Affiliate Detail')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">AFFILIATE PROFILE</div>
        <h1>{{ $affiliate->name }}</h1>
        <p>{{ $affiliate->email }} · Referral code: <strong>{{ $affiliate->referral_code ?: '—' }}</strong></p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="secondary-btn" href="{{ route('admin.affiliates.index') }}">Back</a>
        @if($permissions['view_tree'])
            <a class="primary-btn" href="{{ route('admin.affiliates.tree', $affiliate) }}">Direct Referral Tree</a>
        @endif
    </div>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px 18px;margin-bottom:18px;">{{ session('success') }}</section>
@endif
@if($errors->any())
    <section class="panel" style="padding:14px 18px;margin-bottom:18px;">
        <strong>Action not completed.</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Direct Referrals</span><strong>{{ number_format($affiliate->referrals()->count()) }}</strong></div>
    <div class="user-stat-card"><span>Qualified Sales</span><strong>{{ number_format((int) ($profile?->qualified_sales_count ?? 0)) }}</strong></div>
    <div class="user-stat-card"><span>Current Stage</span><strong>{{ $profile?->currentStage?->name ?? 'Not started' }}</strong></div>
    <div class="user-stat-card"><span>Net Eligible Revenue</span><strong>${{ number_format(((int) ($profile?->qualified_net_eligible_revenue_cents ?? 0)) / 100, 2) }}</strong></div>
    <div class="user-stat-card"><span>Net Commission</span><strong>${{ number_format(((int) ($profile?->netCommissionCents() ?? 0)) / 100, 2) }}</strong></div>
    <div class="user-stat-card"><span>Milestone Gifts</span><strong>${{ number_format(((int) ($profile?->milestone_gifts_earned_cents ?? 0)) / 100, 2) }}</strong></div>
</div>

<section class="panel" style="padding:22px;margin-bottom:20px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">COMMERCIAL RATE</div><h2>Commission Control</h2></div>
    </div>

    @if($profile?->hasApprovedSpecialCommission())
        <p>
            Approved special rate: <strong class="text-gold">{{ number_format($profile->special_commission_bps / 100, 2) }}%</strong><br>
            Approved by: {{ $profile->specialCommissionApprovedByAdmin?->name ?? 'Management' }}<br>
            Reason: {{ $profile->special_commission_reason }}
        </p>
    @else
        <p>Future qualified sales use the standard progressive stage rate.</p>
    @endif

    @if($permissions['manage'])
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;align-items:start;">
            <form method="POST" action="{{ route('admin.affiliates.special_commission.update', $affiliate) }}">
                @csrf
                @method('PATCH')
                <label>Special rate (%) — above 20 and maximum 24</label>
                <input type="number" step="0.01" min="20.01" max="24" name="rate_percent" value="{{ old('rate_percent', $profile?->special_commission_bps ? number_format($profile->special_commission_bps / 100, 2, '.', '') : '') }}" required>
                <label>Management approval reason</label>
                <textarea name="reason" maxlength="500" required>{{ old('reason', $profile?->special_commission_reason) }}</textarea>
                <button class="primary-btn" type="submit">Approve / Replace Special Rate</button>
            </form>

            @if($profile?->hasApprovedSpecialCommission())
                <form method="POST" action="{{ route('admin.affiliates.special_commission.destroy', $affiliate) }}" onsubmit="return confirm('Clear the approved special rate and return future sales to standard stage rates?');">
                    @csrf
                    @method('DELETE')
                    <p>This does not recalculate previously earned commissions.</p>
                    <button class="secondary-btn" type="submit">Clear Special Rate</button>
                </form>
            @endif
        </div>
    @endif
</section>

<section class="panel users-panel" style="margin-bottom:20px;">
    <div class="panel-header"><div><div class="panel-eyebrow">DIRECT ONLY</div><h2>Recent Direct Referrals</h2></div></div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>Name</th><th>Email</th><th>Account</th><th>Status</th><th>Joined</th></tr></thead>
            <tbody>
            @forelse($directReferrals as $referral)
                <tr><td>{{ $referral->name }}</td><td>{{ $referral->email }}</td><td>{{ $referral->account_type ?? '—' }}</td><td>{{ $referral->status ?? '—' }}</td><td>{{ $referral->created_at?->format('Y-m-d') }}</td></tr>
            @empty
                <tr><td colspan="5">No direct referrals yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="panel users-panel" style="margin-bottom:20px;">
    <div class="panel-header"><div><div class="panel-eyebrow">QUALIFIED SALES</div><h2>Recent Sales</h2></div></div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>#</th><th>Referred User</th><th>Net Revenue</th><th>Status</th><th>Qualified</th></tr></thead>
            <tbody>
            @forelse($sales as $sale)
                <tr><td>{{ $sale->qualified_ordinal }}</td><td>{{ $sale->referredUser?->name ?? '—' }}</td><td>${{ number_format($sale->net_eligible_revenue_cents / 100, 2) }}</td><td>{{ $sale->status }}</td><td>{{ $sale->qualified_at?->format('Y-m-d H:i') }}</td></tr>
            @empty
                <tr><td colspan="5">No qualified sales recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

@if($permissions['view_commissions'])
<section class="panel users-panel">
    <div class="panel-header"><div><div class="panel-eyebrow">LEDGER</div><h2>Recent Commissions</h2></div></div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>Rate</th><th>Source</th><th>Gross</th><th>Adjustments</th><th>Net</th><th>Earned</th></tr></thead>
            <tbody>
            @forelse($commissions as $commission)
                @php $adjustments = (int) $commission->adjustments->sum('amount_cents'); @endphp
                <tr><td>{{ number_format($commission->rate_bps / 100, 2) }}%</td><td>{{ $commission->rate_source }}</td><td>${{ number_format($commission->commission_amount_cents / 100, 2) }}</td><td>${{ number_format($adjustments / 100, 2) }}</td><td>${{ number_format(($commission->commission_amount_cents + $adjustments) / 100, 2) }}</td><td>{{ $commission->earned_at?->format('Y-m-d H:i') }}</td></tr>
            @empty
                <tr><td colspan="6">No commission entries recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endif
@endsection
