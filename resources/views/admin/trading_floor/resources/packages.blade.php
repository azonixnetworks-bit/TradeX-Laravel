@extends('admin.layouts.app')

@section('title', 'Trading Floor Packages | TradeX Administration')
@section('page-title', 'Trading Floor Packages')

@section('content')
@php $admin = auth('admin')->user(); $canManage = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.packages.manage'); @endphp
<div class="page-header">
    <div><div class="page-eyebrow">ADMIN-31 · PACKAGE MANAGEMENT</div><h1>Membership <span>Packages</span></h1><p>Define pricing, billing cycle, optional commission defaults and package features.</p></div>
    <div><a class="btn btn-secondary" href="{{ route('admin.trading_floor.resources.index') }}">Resource Dashboard</a><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.index') }}">Memberships</a></div>
</div>

@if($canManage)
<div class="panel">
    <div class="panel-header"><div><div class="panel-eyebrow">NEW PACKAGE</div><h2>Create Package</h2></div></div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.trading_floor.packages.store') }}">@csrf
            <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;">
                <div class="form-group"><label>Code</label><input name="code" required maxlength="80" placeholder="PRO-MONTHLY"></div>
                <div class="form-group"><label>Name</label><input name="name" required maxlength="255" placeholder="Pro Trader Monthly"></div>
                <div class="form-group"><label>Currency</label><input name="currency" value="LKR" required maxlength="3"></div>
                <div class="form-group"><label>Price</label><input name="price_amount" type="number" min="0" step="0.01" required></div>
                <div class="form-group"><label>Billing Cycle</label><select name="billing_cycle"><option>WEEKLY</option><option selected>MONTHLY</option><option>QUARTERLY</option><option>YEARLY</option></select></div>
                <div class="form-group"><label>Duration Days</label><input name="duration_days" type="number" min="1" max="3650" placeholder="Optional"></div>
                <div class="form-group"><label>Commission Type</label><select name="commission_type"><option value="">No Override</option><option>PERCENTAGE</option><option>FIXED</option><option>HYBRID</option></select></div>
                <div class="form-group"><label>Commission %</label><input name="commission_rate" type="number" min="0" max="100" step="0.0001"></div>
                <div class="form-group"><label>Fixed Commission</label><input name="fixed_commission_amount" type="number" min="0" step="0.01"></div>
                <div class="form-group"><label>Status</label><select name="status"><option>ACTIVE</option><option>INACTIVE</option></select></div>
                <div class="form-group"><label>Sort</label><input name="sort_order" type="number" min="0" value="0"></div>
            </div>
            <div class="form-group"><label>Description</label><textarea name="description" rows="2"></textarea></div>
            <div class="form-group"><label>Features — one per line</label><textarea name="features_text" rows="4" placeholder="Dedicated desk&#10;High-speed internet&#10;Trading room access"></textarea></div>
            <button class="btn btn-primary" type="submit">Create Package</button>
        </form>
    </div>
</div>
@endif

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">COMMERCIAL CATALOG</div><h2>{{ $packages->count() }} Packages</h2></div></div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table"><thead><tr><th>Package</th><th>Price</th><th>Cycle</th><th>Commission Default</th><th>Features</th><th>Status</th><th>Control</th></tr></thead><tbody>
        @forelse($packages as $package)
            <tr>
                <td><strong>{{ $package->code }}</strong><div style="opacity:.65;font-size:12px;">{{ $package->name }}</div></td>
                <td>{{ $package->currency }} {{ number_format((float)$package->price_amount,2) }}</td><td>{{ $package->billing_cycle }}</td>
                <td>{{ $package->commission_type ?: 'No override' }}@if($package->commission_rate !== null)<div style="opacity:.65;font-size:12px;">{{ number_format((float)$package->commission_rate,2) }}%</div>@endif</td>
                <td>{{ count($package->features ?? []) }} item(s)</td><td><span class="badge">{{ $package->status }}</span></td>
                <td>@if($canManage)<form method="POST" action="{{ route('admin.trading_floor.packages.update',$package) }}" style="display:grid;grid-template-columns:110px 160px 85px 110px 120px 110px 110px 110px 120px 80px auto;gap:6px;align-items:center;min-width:1200px;">@csrf @method('PATCH')
                    <input name="code" value="{{ $package->code }}" required><input name="name" value="{{ $package->name }}" required><input name="currency" value="{{ $package->currency }}" required maxlength="3"><input name="price_amount" type="number" step="0.01" min="0" value="{{ $package->price_amount }}" required><select name="billing_cycle">@foreach(['WEEKLY','MONTHLY','QUARTERLY','YEARLY'] as $cycle)<option @selected($package->billing_cycle===$cycle)>{{ $cycle }}</option>@endforeach</select><input name="duration_days" type="number" min="1" value="{{ $package->duration_days }}"><select name="commission_type"><option value="">NONE</option>@foreach(['PERCENTAGE','FIXED','HYBRID'] as $type)<option @selected($package->commission_type===$type)>{{ $type }}</option>@endforeach</select><input name="commission_rate" type="number" step="0.0001" min="0" max="100" value="{{ $package->commission_rate }}"><input name="fixed_commission_amount" type="number" step="0.01" min="0" value="{{ $package->fixed_commission_amount }}"><select name="status"><option @selected($package->status==='ACTIVE')>ACTIVE</option><option @selected($package->status==='INACTIVE')>INACTIVE</option></select><input type="hidden" name="sort_order" value="{{ $package->sort_order }}"><input type="hidden" name="description" value="{{ $package->description }}"><input type="hidden" name="features_text" value="{{ implode("\n", $package->features ?? []) }}"><button class="btn btn-secondary">Save</button></form>@endif</td>
            </tr>
        @empty<tr><td colspan="7" style="padding:28px;text-align:center;opacity:.7;">No packages configured.</td></tr>@endforelse
        </tbody></table>
    </div>
</div>
@endsection
