@extends('admin.layouts.app')

@section('title', 'Security Events')
@section('page-title', 'Security Events')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">SECURITY OPERATIONS</div>
        <h1>Security <span>Events</span></h1>
        <p>
            Curated security-relevant audit activity. Sensitive audit payload values are intentionally not rendered here.
        </p>
    </div>
</div>

@include('admin.security.partials.stats')

<div
    style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;"
>
    @if($permissions['events'])
        <a
            class="primary-btn"
            href="{{ route('admin.security.events') }}"
        >
            Security Events
        </a>
    @endif

    @if($permissions['view'])
        <a
            class="secondary-btn"
            href="{{ route('admin.security.sessions') }}"
        >
            Sessions & Devices
        </a>

        <a
            class="secondary-btn"
            href="{{ route('admin.security.content-protection') }}"
        >
            Protection Posture
        </a>
    @endif
</div>

<section class="panel user-filter-panel">
    <form
        method="GET"
        action="{{ route('admin.security.events') }}"
        class="user-filter-form"
    >
        <div class="filter-item">
            <label>Search</label>
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                maxlength="191"
                placeholder="Action, module, actor, target, IP or user agent"
            >
        </div>

        <div class="filter-item">
            <label>Action</label>
            <select name="action">
                <option value="">All</option>
                @foreach($actions as $action)
                    <option
                        value="{{ $action }}"
                        @selected(request('action') === $action)
                    >
                        {{ $action }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Module</label>
            <select name="module">
                <option value="">All</option>
                @foreach($modules as $module)
                    <option
                        value="{{ $module }}"
                        @selected(request('module') === $module)
                    >
                        {{ $module }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Actor</label>
            <select name="actor_type">
                <option value="">All</option>
                @foreach($actor_types as $actorType)
                    <option
                        value="{{ $actorType }}"
                        @selected(request('actor_type') === $actorType)
                    >
                        {{ $actorType }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>From</label>
            <input
                type="date"
                name="from"
                value="{{ request('from') }}"
            >
        </div>

        <div class="filter-item">
            <label>To</label>
            <input
                type="date"
                name="to"
                value="{{ request('to') }}"
            >
        </div>

        <div class="filter-item">
            <label>Per Page</label>
            <select name="per_page">
                @foreach([10,20,50,100] as $size)
                    <option
                        value="{{ $size }}"
                        @selected((int) request('per_page', 20) === $size)
                    >
                        {{ $size }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button
                class="primary-btn"
                type="submit"
            >
                Apply
            </button>

            <a
                class="secondary-btn"
                href="{{ route('admin.security.events') }}"
            >
                Reset
            </a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">CURATED AUDIT STREAM</div>
            <h2>Security Activity</h2>
        </div>

        <div class="panel-count">
            {{ number_format($events->total()) }} events
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>Actor</th>
                    <th>Target</th>
                    <th>IP</th>
                    <th>User Agent</th>
                </tr>
            </thead>

            <tbody>
            @forelse($events as $event)
                <tr>
                    <td>
                        {{ $event->created_at?->format('Y-m-d H:i:s') ?? '—' }}
                    </td>

                    <td>
                        <strong>{{ $event->action }}</strong>
                    </td>

                    <td>
                        {{ $event->module ?: '—' }}
                    </td>

                    <td>
                        {{ $event->actor_type }}
                        @if($event->actor_id)
                            #{{ $event->actor_id }}
                        @endif
                    </td>

                    <td>
                        {{ $event->target_type ?: '—' }}
                        @if($event->target_id)
                            #{{ $event->target_id }}
                        @endif
                    </td>

                    <td>
                        {{ $event->ip_address ?: '—' }}
                    </td>

                    <td>
                        {{ \Illuminate\Support\Str::limit((string) $event->user_agent, 80) ?: '—' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        No security events match the selected filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">
        {{ $events->links() }}
    </div>
</section>
@endsection
