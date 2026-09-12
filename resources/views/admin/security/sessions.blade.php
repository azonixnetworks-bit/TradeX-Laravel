@extends('admin.layouts.app')

@section('title', 'Security Sessions')
@section('page-title', 'Security Sessions')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">SECURITY OPERATIONS</div>
        <h1>Sessions & <span>Devices</span></h1>
        <p>
            Monitor admin and customer sessions without exposing authentication secrets, raw tokens or private keys.
        </p>
    </div>
</div>

@include('admin.security.partials.stats')

<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
    @if($permissions['events'])
        <a class="secondary-btn" href="{{ route('admin.security.events') }}">
            Security Events
        </a>
    @endif

    <a class="primary-btn" href="{{ route('admin.security.sessions') }}">
        Sessions & Devices
    </a>

    <a class="secondary-btn" href="{{ route('admin.security.content-protection') }}">
        Protection Posture
    </a>
</div>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <strong>Admin session control:</strong>
    Super Admin accounts are restricted to one active tracked admin session. Authorized security managers can force-log-out other admin sessions from this screen. The current browser session cannot be revoked from its own row.
</section>

<section class="panel user-filter-panel" style="margin-bottom:18px;">
    <form method="GET" action="{{ route('admin.security.sessions') }}" class="user-filter-form">
        <div class="filter-item">
            <label>Admin Search</label>
            <input
                type="text"
                name="admin_search"
                value="{{ request('admin_search') }}"
                maxlength="191"
                placeholder="Name, email, IP, device, browser or OS"
            >
        </div>

        <div class="filter-item">
            <label>Admin Session Status</label>
            <select name="admin_status">
                <option value="">All</option>
                @foreach(['ACTIVE', 'REVOKED', 'LOGGED_OUT'] as $adminStatus)
                    <option
                        value="{{ $adminStatus }}"
                        @selected(request('admin_status') === $adminStatus)
                    >
                        {{ $adminStatus }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Per Page</label>
            <select name="admin_per_page">
                @foreach([10,20,50,100] as $size)
                    <option
                        value="{{ $size }}"
                        @selected((int) request('admin_per_page', 20) === $size)
                    >
                        {{ $size }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="filter-actions">
            <button class="primary-btn" type="submit">Apply</button>
            <a class="secondary-btn" href="{{ route('admin.security.sessions') }}">Reset</a>
        </div>
    </form>
</section>

<section class="panel users-panel" style="margin-bottom:24px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">ADMIN DEVICE CONTROL</div>
            <h2>Admin Sessions</h2>
        </div>

        <div class="panel-count">
            {{ number_format($adminSessions->total()) }} records
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Admin</th>
                    <th>Role</th>
                    <th>Device</th>
                    <th>Browser / OS</th>
                    <th>IP Address</th>
                    <th>Login Time</th>
                    <th>Last Seen</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            @forelse($adminSessions as $adminSession)
                @php
                    $isCurrent = $currentAdminSessionUuid !== ''
                        && hash_equals($currentAdminSessionUuid, (string) $adminSession->uuid);

                    $sessionStatus = $adminSession->revoked_at
                        ? 'REVOKED'
                        : ($adminSession->logged_out_at ? 'LOGGED OUT' : 'ACTIVE');

                    $roles = $adminSession->admin?->roles?->pluck('name')->filter()->implode(', ');
                @endphp

                <tr>
                    <td>
                        <strong>{{ $adminSession->admin?->name ?: 'Unknown Admin' }}</strong><br>
                        <small>{{ $adminSession->admin?->email ?: '—' }}</small>
                    </td>

                    <td>
                        {{ $roles !== '' ? $roles : 'No role' }}
                    </td>

                    <td>
                        <strong>{{ $adminSession->device_name ?: 'Unknown device' }}</strong><br>
                        <small>{{ $adminSession->device_type ?: 'Unknown type' }}</small>
                    </td>

                    <td>
                        <strong>{{ $adminSession->browser ?: 'Unknown' }}</strong><br>
                        <small>{{ $adminSession->platform ?: 'Unknown' }}</small>
                    </td>

                    <td>
                        {{ $adminSession->ip_address ?: '—' }}
                    </td>

                    <td>
                        {{ $adminSession->login_at?->format('Y-m-d H:i:s') ?: '—' }}
                    </td>

                    <td>
                        @if($adminSession->last_seen_at)
                            {{ $adminSession->last_seen_at->format('Y-m-d H:i:s') }}<br>
                            <small>{{ $adminSession->last_seen_at->diffForHumans() }}</small>
                        @else
                            —
                        @endif
                    </td>

                    <td>
                        <strong>{{ $sessionStatus }}</strong>

                        @if($isCurrent)
                            <br><small>CURRENT DEVICE</small>
                        @endif

                        @if($adminSession->revoked_reason)
                            <br><small>{{ $adminSession->revoked_reason }}</small>
                        @endif
                    </td>

                    <td>
                        @if(
                            $permissions['manage']
                            && !$isCurrent
                            && !$adminSession->revoked_at
                            && !$adminSession->logged_out_at
                        )
                            <form
                                method="POST"
                                action="{{ route('admin.security.admin-sessions.revoke', $adminSession) }}"
                                onsubmit="return confirm('Force logout this admin device session?');"
                            >
                                @csrf
                                @method('PATCH')

                                <button class="secondary-btn" type="submit">
                                    Force Logout
                                </button>
                            </form>
                        @elseif($isCurrent)
                            <small>Current session</small>
                        @else
                            <small>No action</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">
                        No admin sessions match the selected filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">
        {{ $adminSessions->links() }}
    </div>
</section>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.security.sessions') }}" class="user-filter-form">
        <div class="filter-item">
            <label>Customer Search</label>
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
            <input type="date" name="from" value="{{ request('from') }}">
        </div>

        <div class="filter-item">
            <label>Created To</label>
            <input type="date" name="to" value="{{ request('to') }}">
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
            <button class="primary-btn" type="submit">Apply</button>
            <a class="secondary-btn" href="{{ route('admin.security.sessions') }}">Reset</a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">CUSTOMER REFRESH SESSION INVENTORY</div>
            <h2>Customer Sessions</h2>
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

                    <td>{{ $session->last_used_at ?: '—' }}</td>
                    <td>{{ $session->expires_at ?: '—' }}</td>
                    <td>{{ $session->last_login_ip ?: '—' }}</td>

                    <td>
                        @if($permissions['open_user'])
                            <a class="secondary-btn" href="{{ route('admin.users.show', $session->user_id) }}">
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
                        No customer sessions match the selected filters.
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
