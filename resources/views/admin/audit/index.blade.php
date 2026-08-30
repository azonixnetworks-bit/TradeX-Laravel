@extends('admin.layouts.app')

@section('title', 'Audit Logs')
@section('page-title', 'Audit Logs')

@section('content')
@php
    $auditTimezone = (string) config('tradex_reporting.audit_timezone', 'Asia/Colombo');
    $today = \Carbon\CarbonImmutable::now($auditTimezone)->toDateString();
    $yesterday = \Carbon\CarbonImmutable::now($auditTimezone)->subDay()->toDateString();
    $last7 = \Carbon\CarbonImmutable::now($auditTimezone)->subDays(6)->toDateString();
    $last30 = \Carbon\CarbonImmutable::now($auditTimezone)->subDays(29)->toDateString();

    $reportFilters = request()->except(['page', 'per_page']);
    $exportFrom = request('from') ?: $today;
    $exportTo = request('to') ?: (request('from') ?: $today);
    $exportQuery = array_merge(
        $reportFilters,
        ['from' => $exportFrom, 'to' => $exportTo]
    );
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">IMMUTABLE ACTIVITY HISTORY</div>
        <h1>Audit <span>Logs</span></h1>
        <p>Search, review and securely export TradeX audit metadata. Sensitive payload keys are redacted before report download.</p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a
            class="primary-btn"
            href="{{ route('admin.audit.export', $exportQuery) }}"
        >Download CSV</a>
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
    Audit rows remain immutable. Exported old/new payloads are sanitized at download time; passwords, tokens, OTP/TOTP, account identifiers, wallet addresses and similar sensitive values are redacted.
</section>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <div class="panel-eyebrow">QUICK REPORT PERIODS · {{ $auditTimezone }}</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">
        <a class="secondary-btn" href="{{ route('admin.audit.index', array_merge($reportFilters, ['from' => $today, 'to' => $today])) }}">Today</a>
        <a class="secondary-btn" href="{{ route('admin.audit.index', array_merge($reportFilters, ['from' => $yesterday, 'to' => $yesterday])) }}">Yesterday</a>
        <a class="secondary-btn" href="{{ route('admin.audit.index', array_merge($reportFilters, ['from' => $last7, 'to' => $today])) }}">Last 7 Days</a>
        <a class="secondary-btn" href="{{ route('admin.audit.index', array_merge($reportFilters, ['from' => $last30, 'to' => $today])) }}">Last 30 Days</a>
    </div>
    <p style="margin-bottom:0;"><small>If no From/To date is selected, Download CSV exports today only. Maximum single export window: {{ config('tradex_reporting.max_range_days', 366) }} days.</small></p>
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
                    <td>{{ $log->created_at?->timezone($auditTimezone)->format('Y-m-d H:i:s') ?? '—' }}</td>
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

    @include('admin.components.pagination', [
        'paginator' => $logs,
    ])
</section>
@endsection
