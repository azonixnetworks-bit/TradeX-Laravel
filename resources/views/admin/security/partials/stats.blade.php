<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Active Refresh Sessions</span>
        <strong>{{ number_format($stats['active_sessions']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Trusted Active Devices</span>
        <strong>{{ number_format($stats['trusted_devices']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>TOTP Users</span>
        <strong>{{ number_format($stats['totp_users']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Revoked Devices · 24h</span>
        <strong>{{ number_format($stats['revoked_devices_24h']) }}</strong>
    </div>

    <div class="user-stat-card">
        <span>Security Events · 24h</span>
        <strong>{{ number_format($stats['security_events_24h']) }}</strong>
    </div>
</div>
