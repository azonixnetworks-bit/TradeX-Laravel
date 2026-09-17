@extends('admin.layouts.app')
@section('title','Create Trading Floor Membership | TradeX Administration')
@section('page-title','Create Trading Floor Membership')
@section('content')
<div class="page-header"><div><div class="page-eyebrow">ADMIN-31 · MEMBERSHIP SETUP</div><h1>Create <span>Membership</span></h1><p>Assign a customer to a package, floor and optional desk. Pricing is snapshotted from the package.</p></div><div><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.index') }}">Memberships</a></div></div>
@if($errors->any())<div class="alert alert-danger"><strong>Please correct the membership details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="panel"><div class="panel-header"><div><div class="panel-eyebrow">ASSIGNMENT</div><h2>Membership Details</h2></div></div><div class="panel-body"><form method="POST" action="{{ route('admin.trading_floor.memberships.store') }}">@csrf
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
<div class="form-group"><label>Customer</label><select name="trading_floor_client_id" required><option value="">Select customer</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(old('trading_floor_client_id')==$client->id || ($clientUuid!=='' && $clientUuid===$client->uuid))>{{ $client->client_code }} · {{ $client->full_name }}</option>@endforeach</select></div>
<div class="form-group"><label>Package</label><select name="trading_floor_package_id" required><option value="">Select package</option>@foreach($packages as $package)<option value="{{ $package->id }}" @selected(old('trading_floor_package_id')==$package->id)>{{ $package->code }} · {{ $package->name }} · {{ $package->currency }} {{ number_format((float)$package->price_amount,2) }}</option>@endforeach</select></div>
<div class="form-group"><label>Floor</label><select name="trading_floor_floor_id" required><option value="">Select floor</option>@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected(old('trading_floor_floor_id')==$floor->id)>{{ $floor->code }} · {{ $floor->name }}</option>@endforeach</select></div>
<div class="form-group"><label>Desk / Seat</label><select name="trading_floor_desk_id"><option value="">No dedicated desk</option>@foreach($desks as $desk)<option value="{{ $desk->id }}" @disabled(in_array($desk->id,$occupiedDeskIds,true)) @selected(old('trading_floor_desk_id')==$desk->id)>{{ $desk->floor?->code }} · {{ $desk->code }}{{ in_array($desk->id,$occupiedDeskIds,true) ? ' · OCCUPIED' : '' }}</option>@endforeach</select></div>
<div class="form-group"><label>Start Date</label><input type="date" name="starts_at" required value="{{ old('starts_at',now()->format('Y-m-d')) }}"></div>
<div class="form-group"><label>Expiry Date</label><input type="date" name="expires_at" value="{{ old('expires_at') }}"><small>Leave blank to use package duration/billing cycle.</small></div>
<div class="form-group"><label>Payment Due Date</label><input type="date" name="due_date" value="{{ old('due_date',now()->format('Y-m-d')) }}"></div>
<div class="form-group"><label>Paid Amount</label><input type="number" name="paid_amount" min="0" step="0.01" value="{{ old('paid_amount',0) }}"></div>
<div class="form-group"><label>Status</label><select name="status"><option value="PENDING" @selected(old('status','PENDING')==='PENDING')>PENDING</option><option value="ACTIVE" @selected(old('status')==='ACTIVE')>ACTIVE</option></select></div>
<div class="form-group"><label><input type="checkbox" name="auto_renew" value="1" @checked(old('auto_renew'))> Auto Renew</label></div>
</div>
<div class="form-group"><label>Notes</label><textarea name="notes" rows="4" maxlength="5000">{{ old('notes') }}</textarea></div>
<div style="display:flex;justify-content:flex-end;gap:10px;"><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.index') }}">Cancel</a><button class="btn btn-primary" type="submit">Create Membership</button></div>
</form></div></div>
@endsection
