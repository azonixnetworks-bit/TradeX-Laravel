@extends('admin.layouts.app')

@section('title', 'Trading Floor Floors | TradeX Administration')
@section('page-title', 'Trading Floor Floors')

@section('content')
@php $admin = auth('admin')->user(); $canManage = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.floors.manage'); @endphp
<div class="page-header">
    <div><div class="page-eyebrow">ADMIN-31 · FLOOR MANAGEMENT</div><h1>Trading <span>Floors</span></h1><p>Create and maintain physical floor definitions used by desk allocation and memberships.</p></div>
    <div><a class="btn btn-secondary" href="{{ route('admin.trading_floor.resources.index') }}">Resource Dashboard</a><a class="btn btn-secondary" href="{{ route('admin.trading_floor.desks.index') }}">Desks</a></div>
</div>

@if($canManage)
<div class="panel">
    <div class="panel-header"><div><div class="panel-eyebrow">NEW FLOOR</div><h2>Add Floor</h2></div></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.trading_floor.floors.store') }}" style="display:grid;grid-template-columns:150px minmax(220px,1fr) 150px 150px 150px auto;gap:12px;align-items:end;">@csrf
            <div class="form-group" style="margin:0;"><label>Code</label><input name="code" required maxlength="50" placeholder="F1"></div>
            <div class="form-group" style="margin:0;"><label>Name</label><input name="name" required maxlength="255" placeholder="Main Trading Floor"></div>
            <div class="form-group" style="margin:0;"><label>Capacity</label><input name="capacity" type="number" min="1"></div>
            <div class="form-group" style="margin:0;"><label>Status</label><select name="status"><option>ACTIVE</option><option>INACTIVE</option></select></div>
            <div class="form-group" style="margin:0;"><label>Sort</label><input name="sort_order" type="number" min="0" value="0"></div>
            <button class="btn btn-primary" type="submit">Add Floor</button>
        </form>
    </div>
</div>
@endif

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">FLOOR DIRECTORY</div><h2>{{ $floors->count() }} Floors</h2></div></div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table"><thead><tr><th>Code</th><th>Name</th><th>Capacity</th><th>Desks</th><th>Status</th><th>Control</th></tr></thead><tbody>
        @forelse($floors as $floor)
            <tr>
                <td><strong>{{ $floor->code }}</strong></td><td>{{ $floor->name }}</td><td>{{ $floor->capacity ?: '—' }}</td><td>{{ $floor->desks_count }}</td><td><span class="badge">{{ $floor->status }}</span></td>
                <td>
                    @if($canManage)
                    <form method="POST" action="{{ route('admin.trading_floor.floors.update', $floor) }}" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">@csrf @method('PATCH')
                        <input name="code" value="{{ $floor->code }}" required style="width:90px;"><input name="name" value="{{ $floor->name }}" required style="min-width:180px;">
                        <input name="capacity" type="number" min="1" value="{{ $floor->capacity }}" style="width:90px;"><input name="sort_order" type="number" min="0" value="{{ $floor->sort_order }}" style="width:80px;">
                        <select name="status" style="width:130px;"><option @selected($floor->status==='ACTIVE')>ACTIVE</option><option @selected($floor->status==='INACTIVE')>INACTIVE</option></select>
                        <input type="hidden" name="description" value="{{ $floor->description }}"><button class="btn btn-secondary" type="submit">Save</button>
                    </form>
                    @endif
                </td>
            </tr>
        @empty<tr><td colspan="6" style="padding:28px;text-align:center;opacity:.7;">No floors configured.</td></tr>@endforelse
        </tbody></table>
    </div>
</div>
@endsection
