@extends('admin.layouts.app')

@section('title', 'Trading Floor Desks | TradeX Administration')
@section('page-title', 'Trading Floor Desks')

@section('content')
@php $admin = auth('admin')->user(); $canManage = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.desks.manage'); $occupied = collect($occupiedDeskIds); @endphp
<div class="page-header">
    <div><div class="page-eyebrow">ADMIN-31 · DESK / SEAT MANAGEMENT</div><h1>Desk <span>Allocation</span></h1><p>Maintain seats by floor and monitor operational availability.</p></div>
    <div><a class="btn btn-secondary" href="{{ route('admin.trading_floor.resources.index') }}">Resource Dashboard</a><a class="btn btn-secondary" href="{{ route('admin.trading_floor.floors.index') }}">Floors</a></div>
</div>

@if($canManage)
<div class="panel">
    <div class="panel-header"><div><div class="panel-eyebrow">NEW DESK</div><h2>Add Desk / Seat</h2></div></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.trading_floor.desks.store') }}" style="display:grid;grid-template-columns:220px 140px minmax(180px,1fr) 160px 170px auto;gap:12px;align-items:end;">@csrf
            <div class="form-group" style="margin:0;"><label>Floor</label><select name="trading_floor_floor_id" required>@foreach($floors as $floor)<option value="{{ $floor->id }}">{{ $floor->code }} · {{ $floor->name }}</option>@endforeach</select></div>
            <div class="form-group" style="margin:0;"><label>Desk Code</label><input name="code" required maxlength="50" placeholder="D01"></div>
            <div class="form-group" style="margin:0;"><label>Name</label><input name="name" maxlength="255" placeholder="Window Desk"></div>
            <div class="form-group" style="margin:0;"><label>Zone</label><input name="zone" maxlength="80" placeholder="A"></div>
            <div class="form-group" style="margin:0;"><label>Status</label><select name="status"><option>ACTIVE</option><option>MAINTENANCE</option><option>INACTIVE</option></select></div>
            <button class="btn btn-primary" type="submit">Add Desk</button>
        </form>
    </div>
</div>
@endif

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">SEAT MAP DATA</div><h2>Desk Directory</h2></div></div>
    <div class="panel-body">
        <form method="GET" action="{{ route('admin.trading_floor.desks.index') }}" style="display:flex;gap:10px;align-items:end;margin-bottom:18px;flex-wrap:wrap;"><div class="form-group" style="margin:0;min-width:240px;"><label>Floor Filter</label><select name="floor"><option value="">All Floors</option>@foreach($floors as $floor)<option value="{{ $floor->uuid }}" @selected($floorUuid===$floor->uuid)>{{ $floor->code }} · {{ $floor->name }}</option>@endforeach</select></div><button class="btn btn-primary">Filter</button><a class="btn btn-secondary" href="{{ route('admin.trading_floor.desks.index') }}">Reset</a></form>
        <div style="overflow-x:auto;"><table class="data-table"><thead><tr><th>Floor</th><th>Desk</th><th>Zone</th><th>Availability</th><th>Status</th><th>Control</th></tr></thead><tbody>
        @forelse($desks as $desk)
            <tr><td>{{ $desk->floor?->code }}</td><td><strong>{{ $desk->code }}</strong><div style="opacity:.65;font-size:12px;">{{ $desk->name }}</div></td><td>{{ $desk->zone ?: '—' }}</td><td><span class="badge">{{ $occupied->contains($desk->id) ? 'OCCUPIED' : 'AVAILABLE' }}</span></td><td>{{ $desk->status }}</td>
            <td>@if($canManage)<form method="POST" action="{{ route('admin.trading_floor.desks.update', $desk) }}" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">@csrf @method('PATCH')<select name="trading_floor_floor_id" style="width:160px;">@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected($desk->trading_floor_floor_id===$floor->id)>{{ $floor->code }}</option>@endforeach</select><input name="code" value="{{ $desk->code }}" required style="width:85px;"><input name="name" value="{{ $desk->name }}" style="width:150px;"><input name="zone" value="{{ $desk->zone }}" style="width:90px;"><select name="status" style="width:145px;">@foreach(['ACTIVE','MAINTENANCE','INACTIVE'] as $status)<option @selected($desk->status===$status)>{{ $status }}</option>@endforeach</select><input type="hidden" name="notes" value="{{ $desk->notes }}"><button class="btn btn-secondary">Save</button></form>@endif</td></tr>
        @empty<tr><td colspan="6" style="padding:28px;text-align:center;opacity:.7;">No desks found.</td></tr>@endforelse
        </tbody></table></div><div style="margin-top:18px;">{{ $desks->links() }}</div>
    </div>
</div>
@endsection
