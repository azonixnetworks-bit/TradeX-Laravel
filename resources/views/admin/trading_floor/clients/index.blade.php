@extends('admin.layouts.app')

@section('title', 'Trading Floor Customers | TradeX Administration')
@section('page-title', 'Trading Floor Customers')

@section('content')
@php
    $admin = auth('admin')->user();
    $canManage = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.clients.manage');
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-30 · CUSTOMER DIRECTORY</div>
        <h1>Trading Floor <span>Customers</span></h1>
        <p>Registered floor clients, commission setup, desk allocation and access credential status.</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.index') }}">Dashboard</a>
        @if($canManage)
            <a class="btn btn-primary" href="{{ route('admin.trading_floor.clients.create') }}">＋ Register Customer</a>
        @endif
    </div>
</div>

<div class="panel" style="margin-top:22px;">
    <div class="panel-body">
        <form method="GET" action="{{ route('admin.trading_floor.clients.index') }}" style="display:grid;grid-template-columns:minmax(240px,1fr) 180px auto;gap:12px;align-items:end;">
            <div class="form-group" style="margin:0;">
                <label for="search">Search</label>
                <input id="search" name="search" type="text" value="{{ $search }}" placeholder="Code, name, mobile, email or identity">
            </div>
            <div class="form-group" style="margin:0;">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All statuses</option>
                    @foreach(['ACTIVE', 'INACTIVE', 'SUSPENDED'] as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.index') }}">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">CLIENT DIRECTORY</div>
            <h2>{{ number_format($clients->total()) }} Registered</h2>
        </div>
    </div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Floor / Desk</th>
                    <th>Commission</th>
                    <th>Access</th>
                    <th>Status</th>
                    <th style="text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $client)
                    <tr>
                        <td>
                            <strong>{{ $client->full_name }}</strong>
                            <div style="opacity:.68;font-size:12px;">{{ $client->client_code }} · {{ $client->mobile ?: ($client->email ?: 'No contact') }}</div>
                        </td>
                        <td>{{ $client->floor_code ?: '—' }} / {{ $client->desk_code ?: '—' }}</td>
                        <td>
                            <strong>{{ $client->commission_type }}</strong>
                            <div style="opacity:.68;font-size:12px;">
                                @if(in_array($client->commission_type, ['PERCENTAGE', 'HYBRID'], true))
                                    {{ number_format((float) $client->commission_rate, 2) }}%
                                @endif
                                @if($client->commission_type === 'HYBRID') · @endif
                                @if(in_array($client->commission_type, ['FIXED', 'HYBRID'], true))
                                    {{ $client->commission_currency }} {{ number_format((float) $client->fixed_commission_amount, 2) }}
                                @endif
                            </div>
                        </td>
                        <td>{{ number_format($client->access_credentials_count) }} credential(s)</td>
                        <td><span class="badge">{{ $client->status }}</span></td>
                        <td style="text-align:right;">
                            <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.show', $client) }}">Open</a>
                            @if($canManage)
                                <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.edit', $client) }}">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:28px;text-align:center;opacity:.72;">No trading floor customers found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top:18px;">{{ $clients->links() }}</div>
    </div>
</div>
@endsection
