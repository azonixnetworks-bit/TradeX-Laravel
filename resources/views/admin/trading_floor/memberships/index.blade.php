@extends('admin.layouts.app')
@section('title','Trading Floor Memberships | TradeX Administration')
@section('page-title','Trading Floor Memberships')
@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-31 · MEMBERSHIP MANAGEMENT</div>
        <h1>Floor <span>Memberships</span></h1>
        <p>Customer membership periods, renewals, package snapshots, payment status and seat allocation.</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.resources.index') }}">Resource Dashboard</a>
        <a class="btn btn-primary" href="{{ route('admin.trading_floor.memberships.create') }}">＋ New Membership</a>
    </div>
</div>

<div class="dashboard-grid" style="margin-top:22px;">
    <a class="stat-card" href="{{ route('admin.trading_floor.memberships.index',['status'=>'ACTIVE']) }}" style="text-decoration:none;"><div class="stat-card__label">Active</div><div class="stat-card__value">{{ number_format($stats['active']) }}</div></a>
    <a class="stat-card" href="{{ route('admin.trading_floor.memberships.index',['view'=>'EXPIRING']) }}" style="text-decoration:none;"><div class="stat-card__label">Expiring in 7 Days</div><div class="stat-card__value">{{ number_format($stats['expiring_7']) }}</div></a>
    <a class="stat-card" href="{{ route('admin.trading_floor.memberships.index',['view'=>'OVERDUE_PAYMENT']) }}" style="text-decoration:none;"><div class="stat-card__label">Overdue Payment</div><div class="stat-card__value">{{ number_format($stats['overdue_payment']) }}</div></a>
    <a class="stat-card" href="{{ route('admin.trading_floor.memberships.index',['view'=>'AUTO_RENEW']) }}" style="text-decoration:none;"><div class="stat-card__label">Auto Renew Enabled</div><div class="stat-card__value">{{ number_format($stats['auto_renew']) }}</div></a>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-body">
        <form method="GET" action="{{ route('admin.trading_floor.memberships.index') }}" style="display:grid;grid-template-columns:minmax(240px,1fr) 200px 200px auto auto;gap:10px;align-items:end;">
            <div class="form-group" style="margin:0;"><label>Search</label><input name="search" value="{{ $search }}" placeholder="Membership, customer code or name"></div>
            <div class="form-group" style="margin:0;"><label>Status</label><select name="status"><option value="">All Statuses</option>@foreach(['PENDING','ACTIVE','EXPIRED','SUSPENDED','CANCELLED'] as $option)<option value="{{ $option }}" @selected($status===$option)>{{ $option }}</option>@endforeach</select></div>
            <div class="form-group" style="margin:0;"><label>Operations View</label><select name="view"><option value="">All Records</option><option value="EXPIRING" @selected($view==='EXPIRING')>Expiring in 7 Days</option><option value="OVERDUE_PAYMENT" @selected($view==='OVERDUE_PAYMENT')>Overdue Payment</option><option value="AUTO_RENEW" @selected($view==='AUTO_RENEW')>Auto Renew Enabled</option></select></div>
            <button class="btn btn-primary">Filter</button>
            <a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.index') }}">Reset</a>
        </form>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">MEMBERSHIP DIRECTORY</div><h2>{{ number_format($memberships->total()) }} Records</h2></div></div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Membership</th><th>Customer</th><th>Package</th><th>Floor / Desk</th><th>Period</th><th>Price</th><th>Payment</th><th>Auto Renew</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($memberships as $membership)
                <tr>
                    <td><strong>{{ $membership->membership_number }}</strong></td>
                    <td>{{ $membership->client?->client_code }}<div style="opacity:.65;font-size:12px;">{{ $membership->client?->full_name }}</div></td>
                    <td>{{ $membership->package?->name }}</td>
                    <td>{{ $membership->floor?->code }} / {{ $membership->desk?->code ?: '—' }}</td>
                    <td>{{ $membership->starts_at?->format('Y-m-d') }} → {{ $membership->expires_at?->format('Y-m-d') }}</td>
                    <td>{{ $membership->currency }} {{ number_format((float)$membership->price_amount,2) }}</td>
                    <td>{{ $membership->payment_status }}<div style="opacity:.65;font-size:12px;">Balance {{ $membership->currency }} {{ number_format((float)$membership->balance_amount,2) }}</div></td>
                    <td>{{ $membership->auto_renew ? 'YES' : 'NO' }}</td>
                    <td><span class="badge">{{ $membership->status }}</span></td>
                    <td><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.show',$membership) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="10" style="padding:28px;text-align:center;opacity:.7;">No memberships found.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:18px;">{{ $memberships->links() }}</div>
    </div>
</div>
@endsection
