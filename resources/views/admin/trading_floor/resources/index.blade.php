@extends('admin.layouts.app')

@section('title', 'Trading Floor Resources | TradeX Administration')
@section('page-title', 'Trading Floor Resources')

@section('content')
<div class="page-header">
    <div><div class="page-eyebrow">ADMIN-31 · FLOOR / DESK / PACKAGE / MEMBERSHIP</div><h1>Resource <span>Control</span></h1><p>Manage physical floors, desk availability, commercial packages and customer memberships.</p></div>
    <div>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.floors.index') }}">Floors</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.desks.index') }}">Desks</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.packages.index') }}">Packages</a>
        <a class="btn btn-primary" href="{{ route('admin.trading_floor.memberships.create') }}">＋ Membership</a>
    </div>
</div>

<div class="dashboard-grid">
    <div class="stat-card"><div class="stat-card__label">Active Floors</div><div class="stat-card__value">{{ number_format($stats['active_floors']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Active Desks</div><div class="stat-card__value">{{ number_format($stats['active_desks']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Occupied</div><div class="stat-card__value">{{ number_format($stats['occupied_desks']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Available</div><div class="stat-card__value">{{ number_format($stats['available_desks']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Active Packages</div><div class="stat-card__value">{{ number_format($stats['active_packages']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Open Memberships</div><div class="stat-card__value">{{ number_format($stats['open_memberships']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Expiring ≤ 7 Days</div><div class="stat-card__value">{{ number_format($stats['expiring_7_days']) }}</div></div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">RECENT ACTIVITY</div><h2>Memberships</h2></div><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.index') }}">View All</a></div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table"><thead><tr><th>Membership</th><th>Customer</th><th>Package</th><th>Floor / Desk</th><th>Period</th><th>Status</th></tr></thead><tbody>
        @forelse($recentMemberships as $membership)
            <tr>
                <td><a href="{{ route('admin.trading_floor.memberships.show', $membership) }}">{{ $membership->membership_number }}</a></td>
                <td>{{ $membership->client?->client_code }}<div style="opacity:.65;font-size:12px;">{{ $membership->client?->full_name }}</div></td>
                <td>{{ $membership->package?->name }}</td>
                <td>{{ $membership->floor?->code }} / {{ $membership->desk?->code ?: '—' }}</td>
                <td>{{ $membership->starts_at?->format('Y-m-d') }} → {{ $membership->expires_at?->format('Y-m-d') }}</td>
                <td><span class="badge">{{ $membership->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" style="padding:28px;text-align:center;opacity:.7;">No memberships created yet.</td></tr>
        @endforelse
        </tbody></table>
    </div>
</div>
@endsection
