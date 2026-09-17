@extends('admin.layouts.app')

@section('title', 'Seat Availability Map | TradeX Administration')
@section('page-title', 'Seat Availability Map')

@section('content')
<div class="page-header">
    <div><div class="page-eyebrow">ADMIN-31 · DESK AVAILABILITY</div><h1>Trading Floor <span>Seat Map</span></h1><p>Live operational desk availability derived from open memberships. ACTIVE desks without an open membership are available.</p></div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;"><a class="btn btn-secondary" href="{{ route('admin.trading_floor.desks.index') }}">Manage Desks</a><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.index') }}">Memberships</a><a class="btn btn-primary" href="{{ route('admin.trading_floor.memberships.create') }}">＋ Allocate Membership</a></div>
</div>

<div class="dashboard-grid" style="margin-top:18px;">
    <div class="stat-card"><div class="stat-card__label">Total Desks</div><div class="stat-card__value">{{ number_format($stats['total_desks']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Active Desks</div><div class="stat-card__value">{{ number_format($stats['active_desks']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Occupied / Reserved</div><div class="stat-card__value">{{ number_format($stats['occupied']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Available</div><div class="stat-card__value">{{ number_format($stats['available']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Unavailable</div><div class="stat-card__value">{{ number_format($stats['unavailable']) }}</div></div>
</div>

@forelse($floors as $floor)
<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">{{ $floor->code }} · {{ $floor->status }}</div><h2>{{ $floor->name }}</h2></div><span>{{ $floor->desks->count() }} desk(s)</span></div>
    <div class="panel-body">
        @if($floor->desks->isEmpty())
            <div style="padding:24px;text-align:center;opacity:.7;">No desks configured for this floor.</div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;">
                @foreach($floor->desks as $desk)
                    @php
                        $membership = $occupancy->get($desk->id);
                        $isUnavailable = $desk->status !== 'ACTIVE';
                        $isOccupied = $membership !== null;
                        $state = $isUnavailable ? $desk->status : ($isOccupied ? 'OCCUPIED' : 'AVAILABLE');
                    @endphp
                    <div class="panel" style="padding:16px;min-height:150px;border-width:1px;{{ $isOccupied ? 'box-shadow:inset 0 0 0 1px rgba(245,190,70,.28);' : '' }}">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:start;"><div><div class="panel-eyebrow">{{ $desk->zone ?: 'GENERAL' }}</div><h3 style="margin:6px 0 0;">{{ $desk->code }}</h3></div><span class="badge">{{ $state }}</span></div>
                        @if($desk->name)<div style="margin-top:7px;opacity:.72;">{{ $desk->name }}</div>@endif
                        @if($membership)
                            <div style="margin-top:13px;padding-top:12px;border-top:1px solid rgba(255,255,255,.07);"><strong>{{ $membership->client?->client_code }}</strong><div style="font-size:12px;opacity:.72;">{{ $membership->client?->full_name }}</div><div style="font-size:12px;opacity:.62;margin-top:4px;">{{ $membership->package?->code }} · {{ $membership->status }}</div><a class="btn btn-secondary" style="margin-top:10px;" href="{{ route('admin.trading_floor.memberships.show',$membership) }}">Open Membership</a></div>
                        @elseif(!$isUnavailable)
                            <div style="margin-top:16px;opacity:.7;">Ready for allocation</div>
                        @else
                            <div style="margin-top:16px;opacity:.7;">Desk cannot be allocated while status is {{ $desk->status }}.</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@empty
<div class="panel" style="margin-top:18px;"><div class="panel-body" style="padding:28px;text-align:center;opacity:.7;">No floors configured.</div></div>
@endforelse
@endsection
