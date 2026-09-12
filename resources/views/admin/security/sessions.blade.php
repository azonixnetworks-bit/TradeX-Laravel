@extends('admin.layouts.app')

@section('title', 'Security Sessions')
@section('page-title', 'Security Sessions')

@section('content')
<style>
    .admin-session-table { table-layout: fixed; width: 100%; }
    .admin-session-table th,
    .admin-session-table td { vertical-align: top; word-break: break-word; }
    .admin-session-table th:nth-child(1) { width: 18%; }
    .admin-session-table th:nth-child(2) { width: 17%; }
    .admin-session-table th:nth-child(3) { width: 12%; }
    .admin-session-table th:nth-child(4) { width: 18%; }
    .admin-session-table th:nth-child(5) { width: 14%; }
    .admin-session-table th:nth-child(6) { width: 21%; }
    .session-badge {
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:5px 9px;
        border:1px solid rgba(255,255,255,.08);
        border-radius:999px;
        font-size:11px;
        font-weight:700;
        letter-spacing:.04em;
        white-space:nowrap;
    }
    .session-dot { width:7px;height:7px;border-radius:50%;background:currentColor;display:inline-block; }
    .session-online { color:#3ddc84; }
    .session-recent { color:#f0b93a; }
    .session-offline { color:#8aa0b5; }
    .session-revoked { color:#ff6b6b; }
    .session-actions { display:flex;gap:7px;flex-wrap:wrap; }
    .session-actions form { margin:0; }
    .session-meta { display:block;margin-top:4px;opacity:.75; }
    @media (max-width: 1180px) {
        .admin-session-table { table-layout:auto; min-width:940px; }
    }
</style>

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
        <a class="secondary-btn" href="{{ route('admin.security.events') }}">Security Events</a>
    @endif

    <a class="primary-btn" href="{{ route('admin.security.sessions') }}">Sessions & Devices</a>
    <a class="secondary-btn" href="{{ route('admin.security.content-protection') }}">Protection Posture</a>
</div>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <strong>Admin session control:</strong>
    Super Admin accounts are restricted to one active tracked session. ONLINE means seen within 2 minutes, RECENTLY ACTIVE within 15 minutes, and OFFLINE means no recent request. Security managers can force-log-out a single admin device or all other active sessions for that admin. The current browser session is always protected from these controls.
</section>

<section class="panel user-filter-panel" style="margin-bottom:18px;">
    <form method="GET" action="{{ route('admin.security.sessions') }}" class="user-filter-form">
        <div class="filter-item">
            <label>Admin Search</label>
            <input type="text" name="admin_search" value="{{ request('admin_search') }}" maxlength="191" placeholder="Name, email, IP, device, browser or OS">
        </div>

        <div class="filter-item">
            <label>Admin Session Status</label>
            <select name="admin_status">
                <option value="">All</option>
                @foreach(['ACTIVE', 'REVOKED', 'LOGGED_OUT'] as $adminStatus)
                    <option value="{{ $adminStatus }}" @selected(request('admin_status') === $adminStatus)>{{ $adminStatus }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Per Page</label>
            <select name="admin_per_page">
                @foreach([10,20,50,100] as $size)
                    <option value="{{ $size }}" @selected((int) request('admin_per_page', 20) === $size)>{{ $size }}</option>
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

        <div class="panel-count">{{ number_format($adminSessions->total()) }} records</div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table admin-session-table">
            <thead>
                <tr>
                    <th>Admin / Role</th>
                    <th>Device / Browser</th>
                    <th>IP Address</th>
                    <th>Login / Last Seen</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            @forelse($adminSessions as $adminSession)
                @php
                    $isCurrent = $currentAdminSessionUuid !== ''
                        && hash_equals($currentAdminSessionUuid, (string) $adminSession->uuid);
                    $roles = $adminSession->admin?->roles?->pluck('name')->filter()->implode(', ');
                    $lifecycle = $adminSession->lifecycleStatus();
                    $activity = $adminSession->activityStatus();
                    $activityClass = match($activity) {
                        'ONLINE' => 'session-online',
                        'RECENTLY_ACTIVE' => 'session-recent',
                        default => 'session-offline',
                    };
                @endphp

                <tr>
                    <td>
                        <strong>{{ $adminSession->admin?->name ?: 'Unknown Admin' }}</strong><br>
                        <small>{{ $adminSession->admin?->email ?: '—' }}</small>
                        <small class="session-meta">{{ $roles !== '' ? $roles : 'No role' }}</small>
                    </td>

                    <td>
                        <strong>{{ $adminSession->device_name ?: 'Unknown device' }}</strong><br>
                        <small>{{ $adminSession->browser ?: 'Unknown browser' }} · {{ $adminSession->platform ?: 'Unknown OS' }}</small>
                        <small class="session-meta">{{ $adminSession->device_type ?: 'Unknown type' }}</small>
                    </td>

                    <td>{{ $adminSession->ip_address ?: '—' }}</td>

                    <td>
                        <strong>{{ $adminSession->login_at?->format('Y-m-d H:i:s') ?: '—' }}</strong>
                        @if($adminSession->last_seen_at)
                            <small class="session-meta">Last seen {{ $adminSession->last_seen_at->diffForHumans() }}</small>
                        @else
                            <small class="session-meta">No activity recorded</small>
                        @endif
                    </td>

                    <td>
                        @if($lifecycle === 'ACTIVE')
                            <span class="session-badge {{ $activityClass }}">
                                <span class="session-dot"></span>{{ str_replace('_', ' ', $activity) }}
                            </span>
                            <small class="session-meta">ACTIVE SESSION</small>
                        @else
                            <span class="session-badge session-revoked">
                                <span class="session-dot"></span>{{ str_replace('_', ' ', $lifecycle) }}
                            </span>
                        @endif

                        @if($isCurrent)
                            <small class="session-meta"><strong>CURRENT DEVICE</strong></small>
                        @endif

                        @if($adminSession->revoked_reason)
                            <small class="session-meta">{{ str_replace('_', ' ', $adminSession->revoked_reason) }}</small>
                        @endif
                    </td>

                    <td>
                        <div class="session-actions">
                            @if($permissions['manage'] && !$isCurrent && $adminSession->isActive())
                                <form method="POST" action="{{ route('admin.security.admin-sessions.revoke', $adminSession) }}" onsubmit="return confirm('Force logout this admin device session?');">
                                    @csrf
                                    @method('PATCH')
                                    <button class="secondary-btn" type="submit">Force Logout</button>
                                </form>
                            @endif

                            @if($permissions['manage'] && $adminSession->admin)
                                <form method="POST" action="{{ route('admin.security.admin-sessions.revoke-all', $adminSession->admin) }}" onsubmit="return confirm('Sign out all other active sessions for this admin? Current browser session will be preserved.');">
                                    @csrf
                                    @method('PATCH')
                                    <button class="secondary-btn" type="submit">
                                        {{ $isCurrent ? 'Logout Other Devices' : 'Logout All Devices' }}
                                    </button>
                                </form>
                            @endif

                            @if(!$permissions['manage'])
                                <small>Read only</small>
                            @elseif(!$adminSession->isActive() && !$adminSession->admin)
                                <small>No action</small>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No admin sessions match the selected filters.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">{{ $adminSessions->links() }}</div>
</section>

<section class="panel user-filter-panel">
    <form method="GET" action="{{ route('admin.security.sessions') }}" class="user-filter-form">
        <div class="filter-item">
            <label>Customer Search</label>
            <input type="text" name="search" value="{{ request('search') }}" maxlength="191" placeholder="User UUID/name/email or device UUID/name/model">
        </div>

        <div class="filter-item">
            <label>Session Status</label>
            <select name="status">
                <option value="">All</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Device Slot</label>
            <select name="slot">
                <option value="">All</option>
                @foreach($slots as $slot)
                    <option value="{{ $slot }}" @selected(request('slot') === $slot)>{{ $slot }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-item">
            <label>Platform</label>
            <select name="platform">
                <option value="">All</option>
                @foreach($platforms as $platform)
                    <option value="{{ $platform }}" @selected(request('platform') === $platform)>{{ $platform }}</option>
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
                    <option value="{{ $size }}" @selected((int) request('per_page', 20) === $size)>{{ $size }}</option>
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
        <div class="panel-count">{{ number_format($sessions->total()) }} records</div>
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
                        @if($session->device_model)<br><small>{{ $session->device_model }}</small>@endif
                    </td>
                    <td>
                        {{ $session->is_trusted ? 'TRUSTED' : 'NOT TRUSTED' }}
                        @if(!$session->is_active || $session->device_revoked_at)<br><small>DEVICE REVOKED</small>@endif
                    </td>
                    <td><strong>{{ $session->session_status }}</strong><br><small>Created {{ $session->token_created_at ?: '—' }}</small></td>
                    <td>{{ $session->last_used_at ?: '—' }}</td>
                    <td>{{ $session->expires_at ?: '—' }}</td>
                    <td>{{ $session->last_login_ip ?: '—' }}</td>
                    <td>
                        @if($permissions['open_user'])
                            <a class="secondary-btn" href="{{ route('admin.users.show', $session->user_id) }}">User Security</a>
                        @else
                            Read only
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">No customer sessions match the selected filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding-top:18px;">{{ $sessions->links() }}</div>
</section>
@endsection
