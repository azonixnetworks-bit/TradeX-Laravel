@extends('admin.layouts.app')

@section('title', 'Customer Device Security')
@section('page-title', 'Customer Device Security')

@section('content')
@php
    $displayTimezone = (string) config('tradex_admin_hardening.display_timezone', 'Asia/Colombo');
    $localTime = static function ($value) use ($displayTimezone): string {
        if (blank($value)) {
            return '—';
        }

        return \Illuminate\Support\Carbon::parse($value, 'UTC')
            ->timezone($displayTimezone)
            ->format('d M Y H:i:s');
    };

    $riskLevel = (string) ($risk['level'] ?? 'NORMAL');
    $riskClass = match($riskLevel) {
        'SUSPICIOUS' => 'risk-suspicious',
        'REVIEW' => 'risk-review',
        default => 'risk-normal',
    };
    $active = (bool) $device->is_active && $device->revoked_at === null;
@endphp

<style>
    .security-detail-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px; }
    .security-detail-card { padding:18px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.012); }
    .security-detail-label { display:block;font-size:10px;letter-spacing:.08em;text-transform:uppercase;opacity:.55;margin-bottom:6px; }
    .risk-badge { display:inline-flex;padding:5px 9px;border:1px solid rgba(255,255,255,.08);border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.04em; }
    .risk-normal { color:#3ddc84; }
    .risk-review { color:#f0b93a; }
    .risk-suspicious { color:#ff6b6b; }
    .history-list { display:grid;gap:10px; }
    .history-item { padding:14px;border:1px solid rgba(255,255,255,.06);border-radius:12px; }
    .history-meta { margin-top:5px;opacity:.68;font-size:12px;line-height:1.5; }
    .action-row { display:flex;gap:8px;flex-wrap:wrap; }
    @media(max-width:760px) { .security-detail-grid { grid-template-columns:1fr; } }
</style>

<div class="page-header">
    <div>
        <div class="page-eyebrow">CUSTOMER DEVICE CONTROL</div>
        <h1>Device <span>Security</span></h1>
        <p>Review a trusted customer device, its latest risk signals, and security history without exposing authentication secrets.</p>
    </div>
</div>

<div class="action-row" style="margin-bottom:18px;">
    <a class="secondary-btn" href="{{ route('admin.security.sessions') }}">Back to Sessions</a>

    @if($canOpenUser && $device->user)
        <a class="secondary-btn" href="{{ route('admin.users.show', $device->user_id) }}">Open User Security</a>
    @endif

    @if($canManage && $active)
        <form method="POST" action="{{ route('admin.security.customer-devices.revoke', $device) }}" onsubmit="return confirm('Force revoke this customer device and sign out every active session on it?');">
            @csrf
            @method('PATCH')
            <button class="primary-btn" type="submit">Force Revoke Device</button>
        </form>
    @endif
</div>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <div class="security-detail-grid">
        <div class="security-detail-card">
            <span class="security-detail-label">Customer</span>
            <strong>{{ $device->user?->name ?: 'Unknown customer' }}</strong><br>
            <small>{{ $device->user?->email ?: '—' }}</small><br>
            <small>{{ $device->user?->status ?: '—' }}</small>
        </div>

        <div class="security-detail-card">
            <span class="security-detail-label">Risk</span>
            <span class="risk-badge {{ $riskClass }}">{{ $riskLevel }}</span>
            <div style="margin-top:8px;">Score {{ (int) ($risk['score'] ?? 0) }}</div>
            @if(!empty($risk['signals']))
                <small>{{ collect($risk['signals'])->map(fn ($signal) => str_replace('_', ' ', (string) $signal))->implode(' · ') }}</small>
            @else
                <small>No recorded risk signals.</small>
            @endif
        </div>

        <div class="security-detail-card">
            <span class="security-detail-label">Device</span>
            <strong>{{ $device->device_name ?: 'Unnamed device' }}</strong><br>
            <small>{{ $device->device_slot }} / {{ $device->platform }}</small><br>
            <small>{{ $device->device_model ?: 'Unknown model' }}</small><br>
            <small>{{ $device->os_version ?: 'Unknown OS version' }} · App {{ $device->app_version ?: '—' }}</small>
        </div>

        <div class="security-detail-card">
            <span class="security-detail-label">Trust / Status</span>
            <strong>{{ $device->is_trusted ? 'TRUSTED' : 'NOT TRUSTED' }}</strong><br>
            <small>{{ $active ? 'ACTIVE' : 'REVOKED / INACTIVE' }}</small>
            @if($device->revoke_reason)
                <br><small>Reason: {{ $device->revoke_reason }}</small>
            @endif
        </div>

        <div class="security-detail-card">
            <span class="security-detail-label">Network</span>
            <strong>{{ $device->last_login_ip ?: '—' }}</strong><br>
            <small>Last login IP</small>
        </div>

        <div class="security-detail-card">
            <span class="security-detail-label">Times ({{ $displayTimezone }})</span>
            <strong>Trusted: {{ $localTime($device->trusted_at) }}</strong><br>
            <small>Registered: {{ $localTime($device->registered_at) }}</small><br>
            <small>Last login: {{ $localTime($device->last_login_at) }}</small><br>
            <small>Last seen: {{ $localTime($device->last_seen_at) }}</small><br>
            <small>Revoked: {{ $localTime($device->revoked_at) }}</small>
        </div>
    </div>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">SECURITY AUDIT TRAIL</div>
            <h2>Recent Device Security History</h2>
        </div>
        <div class="panel-count">{{ $history->count() }} events</div>
    </div>

    <div style="padding:0 18px 18px;">
        <div class="history-list">
            @forelse($history as $event)
                @php
                    $values = is_array($event->new_values) ? $event->new_values : [];
                    $signals = collect((array) ($values['signals'] ?? []))
                        ->filter(fn ($signal) => is_string($signal))
                        ->map(fn ($signal) => str_replace('_', ' ', $signal))
                        ->implode(' · ');
                @endphp
                <div class="history-item">
                    <strong>{{ str_replace('_', ' ', $event->action) }}</strong>
                    <div class="history-meta">
                        {{ $localTime($event->created_at) }} · {{ $event->ip_address ?: 'No IP recorded' }}
                        @if(isset($values['risk_level']))
                            <br>Risk: {{ $values['risk_level'] }} · Score {{ (int) ($values['risk_score'] ?? 0) }}
                        @endif
                        @if($signals !== '')
                            <br>Signals: {{ $signals }}
                        @endif
                        @if($event->actor_type === 'ADMIN')
                            <br>Action performed by an administrator.
                        @endif
                    </div>
                </div>
            @empty
                <div class="history-item">No security history has been recorded for this device yet.</div>
            @endforelse
        </div>
    </div>
</section>
@endsection
