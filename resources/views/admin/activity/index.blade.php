@extends('admin.layouts.app')

@section('title', 'User Activity Analytics')
@section('page-title', 'User Activity Analytics')

@push('styles')
<style>
.activity-dashboard .activity-metric-note { color:#8b98ac;font-size:12px;margin-top:7px }
.activity-dashboard .activity-grid { display:grid;grid-template-columns:repeat(auto-fit,minmax(235px,1fr));gap:14px;margin-bottom:20px }
.activity-dashboard .activity-chart { display:grid;grid-template-columns:repeat(auto-fit,minmax(34px,1fr));gap:7px;align-items:end;min-height:170px;padding:18px 14px 12px;overflow-x:auto }
.activity-dashboard .activity-bar-cell { display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:7px;min-width:25px;height:155px }
.activity-dashboard .activity-bar-track { height:120px;width:80%;display:flex;align-items:flex-end;justify-content:center;background:rgba(135,145,158,.07);border-radius:8px 8px 3px 3px }
.activity-dashboard .activity-bar { min-height:2px;background:linear-gradient(180deg,#f9d777,#ba8e26);border-radius:6px 6px 3px 3px;width:100%;max-width:35px }
.activity-dashboard .activity-bar-caption { font-size:10px;color:#9ba7b9;white-space:nowrap }
.activity-dashboard .activity-helper { color:#97a5b9;font-size:13px }
.activity-dashboard .activity-table-link { color:#f5c55b;text-decoration:underline }
.activity-dashboard .activity-module-list { display:flex;flex-wrap:wrap;gap:10px;padding:18px }
.activity-dashboard .activity-module-pill { background:rgba(195,151,52,.10);border:1px solid rgba(195,151,52,.25);border-radius:12px;padding:8px 12px;font-size:12px }
.activity-dashboard .activity-module-pill strong {color:#e6bf63;margin-left:7px}
@media(max-width:700px){.activity-dashboard .activity-chart {grid-template-columns:repeat(7,minmax(34px,1fr))}}
</style>
@endpush

@section('content')
<div class="activity-dashboard">
    <div class="page-header">
        <div>
            <div class="page-eyebrow">SUPER ADMIN ONLY · UTC · 45-DAY RETENTION</div>
            <h1>User Activity <span>Analytics</span></h1>
            <p>Read-only user engagement, estimated foreground duration and event timeline. Data is client-reported analytics, not security or payment evidence.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="secondary-btn" href="{{ route('admin.users.index') }}">Customers</a>
            <a class="primary-btn" href="{{ route('admin.activity.export', request()->except('page')) }}">Download CSV (max 10,000)</a>
        </div>
    </div>

    <section class="panel user-filter-panel">
        <form class="user-filter-form" method="GET" action="{{ route('admin.activity.index') }}">
            <div class="filter-item">
                <label for="activity-from">From (UTC)</label>
                <input id="activity-from" type="date" name="from" value="{{ $filters['from'] }}" required>
            </div>
            <div class="filter-item">
                <label for="activity-to">To (UTC)</label>
                <input id="activity-to" type="date" name="to" value="{{ $filters['to'] }}" required>
            </div>
            <div class="filter-item">
                <label for="activity-user">User ID</label>
                <input id="activity-user" type="number" min="1" name="user_id" value="{{ $filters['user_id'] }}" placeholder="Exact customer ID">
            </div>
            <div class="filter-item">
                <label for="activity-search">Customer search</label>
                <input id="activity-search" type="search" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="Customer name or email">
            </div>
            <div class="filter-item">
                <label for="activity-module">Module</label>
                <select id="activity-module" name="module">
                    <option value="">All modules</option>
                    @foreach(['app','learning','trade_ideas','journal','calculator','news','funded_accounts','copy_trading','investments','affiliate','support','profile'] as $module)
                        <option value="{{ $module }}" @selected($filters['module'] === $module)>{{ str($module)->replace('_',' ')->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-item">
                <label for="activity-event">Event</label>
                <select id="activity-event" name="event_name">
                    <option value="">All events</option>
                    @foreach(['session_started','session_heartbeat','session_paused','session_resumed','session_ended','screen_viewed','feature_opened','lesson_viewed','lesson_completed','signal_viewed','journal_opened','journal_saved','calculator_used','news_viewed'] as $eventName)
                        <option value="{{ $eventName }}" @selected($filters['event_name'] === $eventName)>{{ str($eventName)->replace('_',' ')->headline() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-item">
                <label for="activity-per-page">Rows per page</label>
                <select id="activity-per-page" name="per_page">
                    @foreach([20,50,100] as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="primary-btn">Apply</button>
                <a href="{{ route('admin.activity.index') }}" class="secondary-btn">Reset</a>
            </div>
        </form>
    </section>

    <div class="activity-grid">
        <div class="user-stat-card"><span>Active Users</span><strong>{{ number_format($summary['active_users']) }}</strong><p class="activity-metric-note">Unique customers in selected dates</p></div>
        <div class="user-stat-card"><span>Events</span><strong>{{ number_format($summary['events']) }}</strong><p class="activity-metric-note">Allowlisted mobile telemetry</p></div>
        <div class="user-stat-card"><span>Sessions</span><strong>{{ number_format($summary['sessions']) }}</strong><p class="activity-metric-note">Distinct reported session identifiers</p></div>
        <div class="user-stat-card"><span>Estimated Active Minutes</span><strong>{{ number_format($summary['estimated_minutes'], 1) }}</strong><p class="activity-metric-note">Capped foreground event intervals; not exact device screen-time</p></div>
    </div>

    <section class="panel" style="margin-bottom:18px">
        <div class="panel-header">
            <div><div class="panel-eyebrow">DAILY ENGAGEMENT · UTC</div><h2>Active Customers</h2></div>
            <div class="panel-count">{{ count($summary['daily']) }} days</div>
        </div>
        @php
            $chartMax = max(1, ...array_column($summary['daily'], 'active_users'));
        @endphp
        <div class="activity-chart" role="img" aria-label="Daily active customers chart">
            @foreach($summary['daily'] as $day)
                <div class="activity-bar-cell" title="{{ $day['date'] }}: {{ $day['active_users'] }} users, {{ $day['sessions'] }} sessions, {{ $day['estimated_minutes'] }} estimated minutes">
                    <strong style="font-size:11px">{{ $day['active_users'] }}</strong>
                    <div class="activity-bar-track"><div class="activity-bar" style="height:{{ max(2, round($day['active_users'] / $chartMax * 100)) }}%"></div></div>
                    <span class="activity-bar-caption">{{ substr($day['date'], 5) }}</span>
                </div>
            @endforeach
        </div>
        <div class="users-table-wrap">
            <table class="users-table">
                <thead><tr><th>UTC Date</th><th>Active Users</th><th>Sessions</th><th>Events</th><th>Est. Active Minutes</th></tr></thead>
                <tbody>
                    @foreach(array_reverse($summary['daily']) as $day)
                        <tr><td>{{ $day['date'] }}</td><td>{{ number_format($day['active_users']) }}</td><td>{{ number_format($day['sessions']) }}</td><td>{{ number_format($day['events']) }}</td><td>{{ number_format($day['estimated_minutes'], 1) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" style="margin-bottom:18px">
        <div class="panel-header"><div><div class="panel-eyebrow">ENGAGEMENT MIX</div><h2>Module Usage</h2></div></div>
        <div class="activity-module-list">
            @forelse($summary['modules'] as $module)
                <span class="activity-module-pill">{{ str($module->module)->replace('_',' ')->headline() }} <strong>{{ number_format($module->events) }}</strong></span>
            @empty
                <span class="activity-helper">No user activity has been reported in this date range.</span>
            @endforelse
        </div>
    </section>

    <section class="panel users-panel">
        <div class="panel-header">
            <div><div class="panel-eyebrow">CUSTOMER TIMELINE</div><h2>Activity Events</h2>
                <p class="activity-helper">Module and Event filters apply to the timeline and CSV. The overview above always includes all events for the chosen dates and customer filters.</p>
            </div>
            <div class="panel-count">{{ number_format($events->total()) }} events</div>
        </div>
        <div class="users-table-wrap">
            <table class="users-table">
                <thead><tr><th>UTC Time</th><th>Customer</th><th>Module</th><th>Activity</th><th>Subject ID</th><th>Session</th></tr></thead>
                <tbody>
                @forelse($events as $event)
                    <tr>
                        <td>{{ $event->occurred_at?->utc()->format('Y-m-d H:i:s') }}</td>
                        <td>
                            <a class="activity-table-link" href="{{ route('admin.users.show', $event->user_id) }}">
                                {{ $event->user?->name ?: 'Deleted customer' }} (#{{ $event->user_id }})
                            </a>
                        </td>
                        <td>{{ str($event->module)->replace('_',' ')->headline() }}</td>
                        <td>{{ str($event->event_name)->replace('_',' ')->headline() }}</td>
                        <td>{{ $event->subject_id ?: '—' }}</td>
                        <td><small>{{ $event->session_id ?: '—' }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="6">No activity records match these filters. Verify Laravel activation and Flutter telemetry if data is expected.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.components.pagination', ['paginator' => $events])
    </section>

    <p class="activity-helper" style="margin-top:16px">
        Session duration is estimated using mobile foreground event gaps capped at three minutes. App termination, offline delays and missing events may affect this estimate. Times displayed in UTC. CSV is limited to 10,000 rows per export.
    </p>
</div>
@endsection
