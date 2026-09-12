@extends('admin.layouts.app')

@section('title', 'Admin Session Details')
@section('page-title', 'Admin Session Details')

@section('content')
@php
    $isCurrent = $currentAdminSessionUuid !== ''
        && hash_equals($currentAdminSessionUuid, (string) $adminSession->uuid);
    $roles = $adminSession->admin?->roles?->pluck('name')->filter()->implode(', ');
    $lifecycle = $adminSession->lifecycleStatus();
    $activity = $adminSession->activityStatus();
@endphp

<style>
    .session-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;}
    .session-detail-card{padding:18px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.015);}
    .session-detail-card small{display:block;opacity:.68;margin-bottom:7px;text-transform:uppercase;letter-spacing:.06em;}
    .session-detail-card strong{font-size:14px;word-break:break-word;}
    .risk-pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.1);font-size:11px;font-weight:700;letter-spacing:.04em;}
    .risk-normal{color:#3ddc84}.risk-review{color:#f0b93a}.risk-suspicious{color:#ff6b6b}
    .signal-list{margin:12px 0 0;padding-left:18px;}
    .signal-list li{margin:7px 0;}
    .session-detail-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px;}
    @media(max-width:850px){.session-detail-grid{grid-template-columns:1fr;}}
</style>

<div class="page-header">
    <div>
        <div class="page-eyebrow">SECURITY OPERATIONS</div>
        <h1>Admin Session <span>Details</span></h1>
        <p>Review device, network, activity and risk signals for this tracked admin session.</p>
    </div>
</div>

<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
    <a class="secondary-btn" href="{{ route('admin.security.sessions') }}">Back to Sessions</a>
</div>

<section class="panel" style="padding:20px;margin-bottom:18px;">
    <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;flex-wrap:wrap;">
        <div>
            <div class="panel-eyebrow">ADMIN IDENTITY</div>
            <h2 style="margin:4px 0 6px;">{{ $adminSession->admin?->name ?: 'Unknown Admin' }}</h2>
            <div>{{ $adminSession->admin?->email ?: '—' }}</div>
            <small style="display:block;margin-top:6px;opacity:.7;">{{ $roles !== '' ? $roles : 'No role assigned' }}</small>
        </div>

        <div>
            <span class="risk-pill {{ $risk['level'] === 'SUSPICIOUS' ? 'risk-suspicious' : ($risk['level'] === 'REVIEW' ? 'risk-review' : 'risk-normal') }}">
                {{ $risk['level'] }} · SCORE {{ $risk['score'] }}
            </span>
            @if($isCurrent)
                <div style="margin-top:8px;font-size:11px;font-weight:700;">CURRENT DEVICE</div>
            @endif
        </div>
    </div>
</section>

<section class="panel" style="padding:20px;margin-bottom:18px;">
    <div class="panel-eyebrow">SESSION INFORMATION</div>
    <div class="session-detail-grid" style="margin-top:14px;">
        <div class="session-detail-card"><small>Device</small><strong>{{ $adminSession->device_name ?: 'Unknown' }} · {{ $adminSession->device_type ?: 'Unknown type' }}</strong></div>
        <div class="session-detail-card"><small>Browser / OS</small><strong>{{ $adminSession->browser ?: 'Unknown' }} · {{ $adminSession->platform ?: 'Unknown' }}</strong></div>
        <div class="session-detail-card"><small>IP Address</small><strong>{{ $adminSession->ip_address ?: '—' }}</strong></div>
        <div class="session-detail-card"><small>Lifecycle</small><strong>{{ str_replace('_', ' ', $lifecycle) }} / {{ str_replace('_', ' ', $activity) }}</strong></div>
        <div class="session-detail-card"><small>Login Time</small><strong>{{ $adminSession->login_at?->format('Y-m-d H:i:s') ?: '—' }}</strong></div>
        <div class="session-detail-card"><small>Last Seen</small><strong>{{ $adminSession->last_seen_at?->format('Y-m-d H:i:s') ?: '—' }} @if($adminSession->last_seen_at) · {{ $adminSession->last_seen_at->diffForHumans() }} @endif</strong></div>
        <div class="session-detail-card"><small>Logged Out</small><strong>{{ $adminSession->logged_out_at?->format('Y-m-d H:i:s') ?: '—' }}</strong></div>
        <div class="session-detail-card"><small>Revoked</small><strong>{{ $adminSession->revoked_at?->format('Y-m-d H:i:s') ?: '—' }}</strong></div>
        <div class="session-detail-card"><small>Revocation Reason</small><strong>{{ $adminSession->revoked_reason ? str_replace('_', ' ', $adminSession->revoked_reason) : '—' }}</strong></div>
        <div class="session-detail-card"><small>Session Reference</small><strong>{{ substr((string) $adminSession->uuid, 0, 8) }}…{{ substr((string) $adminSession->uuid, -6) }}</strong></div>
    </div>
</section>

<section class="panel" style="padding:20px;margin-bottom:18px;">
    <div class="panel-eyebrow">RISK SIGNALS</div>
    <h2 style="margin:4px 0 8px;">{{ $risk['level'] }}</h2>
    <p style="margin:0;opacity:.75;">Signals are heuristic review indicators, not proof of account compromise.</p>
    <ul class="signal-list">
        @foreach($risk['signals'] as $signal)
            <li>{{ $riskService->readableSignal($signal) }}</li>
        @endforeach
    </ul>
</section>

<section class="panel" style="padding:20px;">
    <div class="panel-eyebrow">CLIENT INFORMATION</div>
    <div class="session-detail-card" style="margin-top:14px;">
        <small>User Agent</small>
        <strong>{{ $adminSession->user_agent ?: 'Not recorded' }}</strong>
    </div>

    <div class="session-detail-actions">
        @if($canManage && !$isCurrent && $adminSession->isActive())
            <form method="POST" action="{{ route('admin.security.admin-sessions.revoke', $adminSession) }}" onsubmit="return confirm('Force logout this admin device session?');">
                @csrf
                @method('PATCH')
                <button class="primary-btn" type="submit">Force Logout</button>
            </form>
        @endif

        @if($canManage && $adminSession->admin)
            <form method="POST" action="{{ route('admin.security.admin-sessions.revoke-all', $adminSession->admin) }}" onsubmit="return confirm('Sign out all other active sessions for this admin? Current browser session will be preserved.');">
                @csrf
                @method('PATCH')
                <button class="secondary-btn" type="submit">{{ $isCurrent ? 'Logout Other Devices' : 'Logout All Devices' }}</button>
            </form>
        @endif
    </div>
</section>
@endsection
