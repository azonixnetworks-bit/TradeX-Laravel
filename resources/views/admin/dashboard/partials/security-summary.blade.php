@if($canViewSecurity && is_array($securitySummary))
<style>
    .dashboard-security-summary{margin-bottom:24px;}
    .security-summary-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-bottom:16px;}
    .security-summary-card{padding:16px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.015);}
    .security-summary-card small{display:block;opacity:.62;text-transform:uppercase;letter-spacing:.06em;font-size:10px;margin-bottom:8px;}
    .security-summary-card strong{display:block;font-size:24px;line-height:1.05;}
    .security-summary-card span{display:block;margin-top:6px;font-size:11px;opacity:.7;}
    .security-alerts{display:grid;gap:10px;margin-top:14px;}
    .security-alert{display:grid;grid-template-columns:minmax(180px,1.2fr) minmax(150px,.8fr) minmax(120px,.6fr) auto;gap:12px;align-items:center;padding:13px 14px;border:1px solid rgba(255,255,255,.07);border-radius:12px;background:rgba(255,255,255,.012);}
    .security-alert strong{display:block;}
    .security-alert small{display:block;margin-top:3px;opacity:.68;}
    .security-alert-level{font-size:11px;font-weight:800;letter-spacing:.05em;}
    .security-alert-review{color:#f0b93a;}
    .security-alert-suspicious{color:#ff6b6b;}
    @media(max-width:1180px){.security-summary-grid{grid-template-columns:repeat(3,minmax(0,1fr));}.security-alert{grid-template-columns:1fr 1fr;}}
    @media(max-width:720px){.security-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.security-alert{grid-template-columns:1fr;}}
</style>

<section class="panel dashboard-security-summary">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">SECURITY COMMAND CENTER</div>
            <h2>Admin Security Summary</h2>
        </div>
        <a class="secondary-btn" href="{{ route('admin.security.sessions') }}">Open Security</a>
    </div>

    <div style="padding:0 18px 18px;">
        <div class="security-summary-grid">
            <div class="security-summary-card">
                <small>Active Sessions</small>
                <strong>{{ number_format((int) $securitySummary['active_sessions']) }}</strong>
                <span>Tracked admin sessions</span>
            </div>
            <div class="security-summary-card">
                <small>Online Now</small>
                <strong>{{ number_format((int) $securitySummary['online_sessions']) }}</strong>
                <span>Seen within 2 minutes</span>
            </div>
            <div class="security-summary-card">
                <small>Needs Review</small>
                <strong>{{ number_format((int) $securitySummary['review_sessions']) }}</strong>
                <span>New login pattern signals</span>
            </div>
            <div class="security-summary-card">
                <small>Suspicious</small>
                <strong>{{ number_format((int) $securitySummary['suspicious_sessions']) }}</strong>
                <span>High-risk session signals</span>
            </div>
            <div class="security-summary-card">
                <small>Revoked 24h</small>
                <strong>{{ number_format((int) $securitySummary['revoked_24h']) }}</strong>
                <span>Admin sessions revoked</span>
            </div>
            <div class="security-summary-card">
                <small>Security Events 24h</small>
                <strong>{{ number_format((int) $securitySummary['security_events_24h']) }}</strong>
                <span>AUTH + SECURITY events</span>
            </div>
        </div>

        <div class="panel-eyebrow">SECURITY NOTIFICATIONS</div>
        <div class="security-alerts">
            @forelse($securityAlerts as $alert)
                @php
                    $payload = (array) ($alert->new_values ?? []);
                    $riskLevel = (string) ($payload['risk_level'] ?? ($alert->action === 'ADMIN_SUSPICIOUS_LOGIN_DETECTED' ? 'SUSPICIOUS' : 'REVIEW'));
                    $signals = collect((array) ($payload['signals'] ?? []))
                        ->map(fn ($value) => str_replace('_', ' ', (string) $value))
                        ->implode(' · ');
                @endphp
                <div class="security-alert">
                    <div>
                        <strong>{{ $payload['admin_email'] ?? ('Admin #'.($alert->actor_id ?? '—')) }}</strong>
                        <small>{{ $payload['device_name'] ?? 'Unknown device' }} · {{ $payload['browser'] ?? 'Unknown browser' }} · {{ $payload['platform'] ?? 'Unknown OS' }}</small>
                    </div>
                    <div>
                        <span class="security-alert-level {{ $riskLevel === 'SUSPICIOUS' ? 'security-alert-suspicious' : 'security-alert-review' }}">{{ $riskLevel }}</span>
                        <small>{{ $signals !== '' ? $signals : 'Risk signal recorded' }}</small>
                    </div>
                    <div>
                        <strong>{{ $payload['ip_address'] ?? ($alert->ip_address ?? '—') }}</strong>
                        <small>{{ $securityAlertTimes[(string) $alert->getKey()] ?? '—' }} · {{ $systemTimezone }}</small>
                    </div>
                    <div>
                        <a class="secondary-btn" href="{{ route('admin.security.events', ['search' => $alert->action]) }}">Review</a>
                    </div>
                </div>
            @empty
                <div style="padding:14px 0;opacity:.7;">No suspicious or review-level admin login alerts recorded yet.</div>
            @endforelse
        </div>
    </div>
</section>
@endif
