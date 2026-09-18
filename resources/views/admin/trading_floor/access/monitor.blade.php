@extends('admin.layouts.app')

@section('title', 'Trading Floor Attendance & Access | TradeX Administration')
@section('page-title', 'Trading Floor Attendance & Access')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-30 · ATTENDANCE & ACCESS</div>
        <h1>Floor <span>Access Monitor</span></h1>
        <p>Live audit of NFC/fingerprint entry, exit, warnings and denied access attempts.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.index') }}">Floor Dashboard</a>
        @if(auth('admin')->user()?->isSuperAdmin() || auth('admin')->user()?->hasPermission('trading_floor.settings.manage'))
            <a class="btn btn-primary" href="{{ route('admin.trading_floor.access.settings') }}">Access Settings</a>
        @endif
    </div>
</div>

<div class="dashboard-grid" style="margin-top:22px;">
    <div class="stat-card"><div class="stat-card__label">Present Now</div><div class="stat-card__value">{{ number_format($stats['present_now']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Entries</div><div class="stat-card__value">{{ number_format($stats['entries']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Warnings</div><div class="stat-card__value">{{ number_format($stats['warnings']) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Denied</div><div class="stat-card__value">{{ number_format($stats['denied']) }}</div></div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">ACCESS LOG</div><h2>Reader Attempts</h2></div></div>
    <div class="panel-body">
        <form method="GET" action="{{ route('admin.trading_floor.access.index') }}" style="display:grid;grid-template-columns:minmax(220px,1fr) 180px 180px auto auto;gap:10px;align-items:end;margin-bottom:18px;">
            <div class="form-group" style="margin:0;"><label>Search</label><input name="search" value="{{ $search }}" placeholder="Customer, device or reason"></div>
            <div class="form-group" style="margin:0;"><label>Date</label><input type="date" name="date" value="{{ $date }}" required></div>
            <div class="form-group" style="margin:0;"><label>Decision</label><select name="decision"><option value="">All</option>@foreach(['GRANTED','WARNING','DENIED'] as $option)<option value="{{ $option }}" @selected($decision === $option)>{{ $option }}</option>@endforeach</select></div>
            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-secondary" href="{{ route('admin.trading_floor.access.index') }}">Today</a>
        </form>

        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead><tr><th>Time</th><th>Customer</th><th>Reader</th><th>Method</th><th>Direction</th><th>Decision</th><th>Reason</th></tr></thead>
                <tbody>
                @forelse($attempts as $attempt)
                    <tr>
                        <td>{{ $attempt->occurred_at?->format('H:i:s') }}</td>
                        <td>
                            @if($attempt->client)
                                <a href="{{ route('admin.trading_floor.clients.show', $attempt->client) }}">{{ $attempt->client->client_code }}</a>
                                <div style="opacity:.65;font-size:12px;">{{ $attempt->client->full_name }}</div>
                            @else
                                <strong>Unknown Credential</strong>
                            @endif
                        </td>
                        <td>{{ $attempt->device_code }}<div style="opacity:.65;font-size:12px;">{{ $attempt->device?->name }}</div></td>
                        <td>{{ $attempt->credential_type }}</td>
                        <td>{{ $attempt->direction }}</td>
                        <td><strong>{{ $attempt->decision }}</strong></td>
                        <td>{{ $attempt->reason_code }}<div style="opacity:.65;font-size:12px;">{{ $attempt->reason }}</div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:28px;text-align:center;opacity:.7;">No access attempts found for this filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:18px;">{{ $attempts->links() }}</div>
    </div>
</div>
@endsection
