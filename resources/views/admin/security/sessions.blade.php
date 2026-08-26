@extends('admin.layouts.app')

@section('title', 'Security Sessions')
@section('page-title', 'Security Sessions')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">SECURITY OPERATIONS</div>
        <h1>Sessions & <span>Devices</span></h1>
        <p>
            Global read-only visibility over refresh sessions and trusted devices. Token hashes, token families, installation hashes and public keys are never displayed.
        </p>
    </div>
</div>

@include('admin.security.partials.stats')

<div
    style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;"
>
    @if($permissions['events'])
        <a
            class="secondary-btn"
            href="{{ route('admin.security.events') }}"
        >
            Security Events
        </a>
    @endif

    <a
        class="primary-btn"
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
</div>

<section
    class="panel"
    style="padding:18px;margin-bottom:18px;"
>
    <strong>Ownership boundary:</strong>
    session/device revoke, reset and sign-out actions remain in the frozen User Management security flow. ADMIN-11 only provides global monitoring.
</section>

<section class="panel user-filter-panel">
    <form
        method="GET"
        action="{{ route('admin.security.sessions') }}"
        class="user-filter-form"
    >
        <div class="filter-item">
            <label>Search</label>
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                maxlength="191"
                placeholder="User UUID/name/email or device UUID/name/model"
            >
        </div>

        <div class="filter-item">
            <label>Session Status</label>
            <select name="status">
                <option value="">All</option>
                @foreach($statuses as $status)
                    <option
                        value="{{ $status }}"
                        @selected(request('status') === $status)
                    >
                        {{ $status }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Device Slot</label>
            <select name="slot">
                <option value="">All</option>
                @foreach($slots as $slot)
                    <option
                        value="{{ $slot }}"
                        @selected(request('slot') === $slot)
                    >
                        {{ $slot }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Platform</label>
            <select name="platform">
                <option value="">All</option>
                @foreach($platforms as $platform)
                    <option
                        value="{{ $platform }}"
                        @selected(request('platform') === $platform)
                    >
                        {{ $platform }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Created From</label>
            <input
                type="date"
                name="from"
                value="{{ request('from') }}"
            >
        </div>

        <div class="filter-item">
            <label>Created To</label>
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
                href="{{ route('admin.security.sessions') }}"
            >
                Reset
            </a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">REFRESH SESSION INVENTORY</div>
            <h2>User Sessions</h2>
        </div>

        <div class="panel-count">
            {{ number_format($sessions->total()) }} records
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Device</th>
                    <th>Trust</th>
                    <th>Session</th>
                    <th>Last Used</th>
                    <th>Expires</th>
                    <th>Last Login IP</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
            @forelse($sessions as $session)
                <tr>
                    <td>
                        <strong>{{ $session->user_name }}</strong><br>
                        <small>{{ $session->user_email }}</small><br>
                        <small>{{ $session->user_status }}</small>
                    </td>

                    <td>
                        <strong>{{ $session->device_slot }} / {{ $session->platform }}</strong><br>
                        <small>{{ $session->device_name ?: 'Unnamed device' }}</small>
                        @if($session->device_model)
                            <br><small>{{ $session->device_model }}</small>
                        @endif
                    </td>

                    <td>
                        {{ $session->is_trusted ? 'TRUSTED' : 'NOT TRUSTED' }}
                        @if(!$session->is_active || $session->device_revoked_at)
                            <br><small>DEVICE REVOKED</small>
                        @endif
                    </td>

                    <td>
                        <strong>{{ $session->session_status }}</strong><br>
                        <small>Created {{ $session->token_created_at ?: '—' }}</small>
                    </td>

                    <td>
                        {{ $session->last_used_at ?: '—' }}
                    </td>

                    <td>
                        {{ $session->expires_at ?: '—' }}
                    </td>

                    <td>
                        {{ $session->last_login_ip ?: '—' }}
                    </td>

                    <td>
                        @if($permissions['open_user'])
                            <a
                                class="secondary-btn"
                                href="{{ route('admin.users.show', $session->user_id) }}"
                            >
                                User Security
                            </a>
                        @else
                            Read only
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        No sessions match the selected filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">
        {{ $sessions->links() }}
    </div>
</section>
@endsection
