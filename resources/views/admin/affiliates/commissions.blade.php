@extends('admin.layouts.app')

@section('title', 'Affiliate Commissions')
@section('page-title', 'Affiliate Commissions')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">IMMUTABLE COMMISSION LEDGER</div>
        <h1>Affiliate <span>Commissions</span></h1>
        <p>Earned commission visibility. Payment reversals and payouts are not exposed as voluntary Affiliate Admin actions.</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.affiliates.index') }}">Back to Affiliates</a>
</div>

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Ledger Entries</span><strong>{{ number_format($stats['entries']) }}</strong></div>
    <div class="user-stat-card"><span>Gross Earned</span><strong>${{ number_format($stats['gross_earned_cents'] / 100, 2) }}</strong></div>
    <div class="user-stat-card"><span>Adjustments</span><strong>${{ number_format($stats['adjustment_cents'] / 100, 2) }}</strong></div>
    <div class="user-stat-card"><span>Net Earned</span><strong>${{ number_format($stats['net_earned_cents'] / 100, 2) }}</strong></div>
    <div class="user-stat-card"><span>Special-rate Entries</span><strong>{{ number_format($stats['special_entries']) }}</strong></div>
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.affiliates.commissions.index') }}" class="user-filter-form">
        <div class="filter-search"><label>Affiliate</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, referral code..."></div>
        <div class="filter-item"><label>Rate Source</label><select name="rate_source"><option value="">All</option><option value="STANDARD" @selected(request('rate_source') === 'STANDARD')>Standard</option><option value="SPECIAL" @selected(request('rate_source') === 'SPECIAL')>Special</option></select></div>
        <div class="filter-item"><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
        <div class="filter-item"><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
        <div class="filter-item"><label>Per Page</label><select name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int) request('per_page',20) === $size)>{{ $size }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="primary-btn" type="submit">Apply</button><a class="secondary-btn" href="{{ route('admin.affiliates.commissions.index') }}">Reset</a></div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header"><div><div class="panel-eyebrow">LEDGER</div><h2>Earned Commission Entries</h2></div><div class="panel-count">{{ number_format($commissions->total()) }}</div></div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>Affiliate</th><th>Sale #</th><th>Referred User</th><th>Rate</th><th>Source</th><th>Base</th><th>Gross</th><th>Adjustments</th><th>Net</th><th>Earned</th></tr></thead>
            <tbody>
            @forelse($commissions as $commission)
                @php $adjustments = (int) $commission->adjustments->sum('amount_cents'); @endphp
                <tr>
                    <td><strong>{{ $commission->affiliate?->name ?? '—' }}</strong><br><small>{{ $commission->affiliate?->referral_code ?? '' }}</small></td>
                    <td>{{ $commission->qualified_ordinal }}</td>
                    <td>{{ $commission->qualifiedSale?->referredUser?->name ?? '—' }}</td>
                    <td>{{ number_format($commission->rate_bps / 100, 2) }}%</td>
                    <td>{{ $commission->rate_source }}</td>
                    <td>${{ number_format($commission->calculation_base_cents / 100, 2) }}</td>
                    <td>${{ number_format($commission->commission_amount_cents / 100, 2) }}</td>
                    <td>${{ number_format($adjustments / 100, 2) }}</td>
                    <td>${{ number_format(($commission->commission_amount_cents + $adjustments) / 100, 2) }}</td>
                    <td>{{ $commission->earned_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No commission entries found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding-top:18px;">{{ $commissions->links() }}</div>
</section>
@endsection
