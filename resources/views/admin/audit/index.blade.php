@extends('admin.layouts.app')

@section('title', 'Audit Logs')
@section('page-title', 'Audit Logs')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">IMMUTABLE ACTIVITY HISTORY</div>
        <h1>Audit <span>Logs</span></h1>
        <p>Search and review TradeX audit metadata. Raw old/new JSON payloads are never shown in this list.</p>
    </div>
</div>

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Total Audit Events</span><strong>{{ number_format($stats['total']) }}</strong></div>
    <div class="user-stat-card"><span>Events · 24h</span><strong>{{ number_format($stats['last_24h']) }}</strong></div>
    <div class="user-stat-card"><span>Admin · 24h</span><strong>{{ number_format($stats['admin_last_24h']) }}</strong></div>
    <div class="user-stat-card"><span>User · 24h</span><strong>{{ number_format($stats['user_last_24h']) }}</strong></div>
    <div class="user-stat-card"><span>System · 24h</span><strong>{{ number_format($stats['system_last_24h']) }}</strong></div>
</div>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <strong>Read-only audit boundary:</strong>
    ADMIN-12 does not edit or delete audit rows. Event detail payloads are sanitized at render time; the stored immutable row is not rewritten.
</section>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.audit.index') }}" class="user-filter-form">
        <div class="filter-item"><label>Search</label><input type="text" name="search" value="{{ request('search') }}" maxlength="191" placeholder="Action, module, target, IP, agent or numeric ID"></div>
        <div class="filter-item"><label>Actor Type</label><select name="actor_type"><option value="">All</option>@foreach($actor_types as $actorType)<option value="{{ $actorType }}" @selected(request('actor_type') === $actorType)>{{ $actorType }}</option>@endforeach</select></div>
        <div class="filter-item"><label>Actor ID</label><input type="number" min="1" name="actor_id" value="{{ request('actor_id') }}" placeholder="Exact ID"></div>
        <div class="filter-item"><label>Action</label><select name="action"><option value="">All</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>@endforeach</select></div>
        <div class="filter-item"><label>Module</label><select name="module"><option value="">All</option>@foreach($modules as $module)<option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>@endforeach</select></div>
        <div class="filter-item"><label>Target Type</label><select name="target_type"><option value="">All</option>@foreach($target_types as $targetType)<option value="{{ $targetType }}" @selected(request('target_type') === $targetType)>{{ $targetType }}</option>@endforeach</select></div>
        <div class="filter-item"><label>Target ID</label><input type="number" min="1" name="target_id" value="{{ request('target_id') }}" placeholder="Exact ID"></div>
        <div class="filter-item"><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
        <div class="filter-item"><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
        <div class="filter-item"><label>Per Page</label><select name="per_page">@foreach([10,20,50,100] as $size)<option value="{{ $size }}" @selected((int) request('per_page', 20) === $size)>{{ $size }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="primary-btn" type="submit">Apply</button><a class="secondary-btn" href="{{ route('admin.audit.index') }}">Reset</a></div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header"><div><div class="panel-eyebrow">AUDIT STREAM</div><h2>Recorded Events</h2></div><div class="panel-count">{{ number_format($logs->total()) }} records</div></div>
    <div class="users-table-wrap">
        <table class="users-table">
            <thead><tr><th>ID</th><th>Time</th><th>Action</th><th>Module</th><th>Actor</th><th>Target</th><th>IP</th><th>User Agent</th><th></th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>#{{ $log->getKey() }}</td>
                    <td>{{ $log->created_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    <td><strong>{{ $log->action }}</strong></td>
                    <td>{{ $log->module ?: '—' }}</td>
                    <td>{{ $log->actor_type }} @if($log->actor_id)#{{ $log->actor_id }}@endif</td>
                    <td>{{ $log->target_type ?: '—' }} @if($log->target_id)#{{ $log->target_id }}@endif</td>
                    <td>{{ $log->ip_address ?: '—' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit((string) $log->user_agent, 70) ?: '—' }}</td>
                    <td><a class="secondary-btn" href="{{ route('admin.audit.show', $log->getKey()) }}">Inspect</a></td>
                </tr>
            @empty
                <tr><td colspan="9">No audit logs match the selected filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding-top:18px;">{{ $logs->links() }}</div>
</section>
@endsection
