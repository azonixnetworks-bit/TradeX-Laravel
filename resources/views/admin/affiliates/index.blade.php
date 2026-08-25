@extends('admin.layouts.app')

@section('title', 'TradeX Affiliates')
@section('page-title', 'Affiliates')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">AFFILIATE MANAGEMENT</div>
        <h1>TradeX <span>Affiliates</span></h1>
        <p>Direct-sale affiliate performance, commercial stages and commission visibility.</p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        @if($permissions['manage_stages'])
            <a class="secondary-btn" href="{{ route('admin.affiliates.stages.index') }}">Stages</a>
        @endif
        @if($permissions['view_commissions'])
            <a class="primary-btn" href="{{ route('admin.affiliates.commissions.index') }}">Commission Ledger</a>
        @endif
    </div>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px 18px;margin-bottom:18px;">{{ session('success') }}</section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Affiliate Accounts</span><strong>{{ number_format($stats['affiliates']) }}</strong></div>
    <div class="user-stat-card"><span>Ledger Profiles</span><strong>{{ number_format($stats['profiles']) }}</strong></div>
    <div class="user-stat-card"><span>Qualified Sales</span><strong class="text-green">{{ number_format($stats['qualified_sales']) }}</strong></div>
    <div class="user-stat-card"><span>Special Rates</span><strong class="text-gold">{{ number_format($stats['special_rates']) }}</strong></div>
    <div class="user-stat-card"><span>Net Commission</span><strong>${{ number_format($stats['net_commission_cents'] / 100, 2) }}</strong></div>
    <div class="user-stat-card"><span>Milestone Gifts</span><strong>${{ number_format($stats['milestone_gifts_cents'] / 100, 2) }}</strong></div>
</div>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.affiliates.index') }}" class="user-filter-form">
        <div class="filter-search">
            <label>Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, referral code...">
        </div>

        <div class="filter-item">
            <label>Stage</label>
            <select name="stage">
                <option value="">All stages</option>
                @foreach($stages as $stage)
                    <option value="{{ $stage->code }}" @selected(request('stage') === $stage->code)>{{ $stage->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Rate</label>
            <select name="special">
                <option value="">All rates</option>
                <option value="1" @selected(request('special') === '1')>Special only</option>
            </select>
        </div>

        <div class="filter-item">
            <label>Sort</label>
            <select name="sort">
                <option value="qualified_desc" @selected(request('sort', 'qualified_desc') === 'qualified_desc')>Qualified sales</option>
                <option value="newest" @selected(request('sort') === 'newest')>Newest</option>
                <option value="name_asc" @selected(request('sort') === 'name_asc')>Name A-Z</option>
                <option value="name_desc" @selected(request('sort') === 'name_desc')>Name Z-A</option>
            </select>
        </div>

        <div class="filter-item">
            <label>Per Page</label>
            <select name="per_page">
                @foreach([10,20,50,100] as $size)
                    <option value="{{ $size }}" @selected((int) request('per_page', 20) === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button class="primary-btn" type="submit">Apply</button>
            <a class="secondary-btn" href="{{ route('admin.affiliates.index') }}">Reset</a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div><div class="panel-eyebrow">DIRECT-SALE PROGRAM</div><h2>Affiliate Accounts</h2></div>
        <div class="panel-count">{{ number_format($affiliates->total()) }} records</div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Affiliate</th>
                    <th>Referral Code</th>
                    <th>Direct Referrals</th>
                    <th>Qualified Sales</th>
                    <th>Stage</th>
                    <th>Rate</th>
                    <th>Net Commission</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($affiliates as $affiliate)
                    @php
                        $stage = $stageMap->get($affiliate->current_stage_id);
                        $earned = (int) ($affiliate->lifetime_commission_earned_cents ?? 0);
                        $adjustment = (int) ($affiliate->commission_adjustment_cents ?? 0);
                    @endphp
                    <tr>
                        <td><strong>{{ $affiliate->name }}</strong><br><small>{{ $affiliate->email }}</small></td>
                        <td>{{ $affiliate->referral_code ?: '—' }}</td>
                        <td>{{ number_format($affiliate->referrals_count) }}</td>
                        <td>{{ number_format((int) ($affiliate->qualified_sales_count ?? 0)) }}</td>
                        <td>{{ $stage?->name ?? 'Not started' }}</td>
                        <td>
                            @if($affiliate->special_commission_bps !== null)
                                <strong class="text-gold">{{ number_format($affiliate->special_commission_bps / 100, 2) }}% Special</strong>
                            @elseif($stage)
                                {{ number_format($stage->standard_commission_bps / 100, 2) }}%
                            @else
                                —
                            @endif
                        </td>
                        <td>${{ number_format(($earned + $adjustment) / 100, 2) }}</td>
                        <td style="white-space:nowrap;">
                            <a class="secondary-btn" href="{{ route('admin.affiliates.show', $affiliate) }}">View</a>
                            @if($permissions['view_tree'])
                                <a class="secondary-btn" href="{{ route('admin.affiliates.tree', $affiliate) }}">Tree</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">No affiliate records match the current filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">{{ $affiliates->links() }}</div>
</section>
@endsection
