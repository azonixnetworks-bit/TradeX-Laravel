<section class="panel" style="padding:18px;margin-bottom:20px" id="admin-device-overview">
    <div class="page-header">
        <div><div class="page-eyebrow">SUPER ADMIN SECURITY</div><h2>Admin Devices &amp; Sessions</h2></div>
        <a class="secondary-btn" href="{{ route('admin.administration.admins.index') }}">All Admins</a>
    </div>
    <p>The configured Super Admin can approve five devices in any mix. Other admins can approve two desktop browsers and one mobile browser. Signed-in counts show sessions that have not logged out or timed out; a closed browser may remain listed until timeout.</p>
    <div style="overflow-x:auto">
        <table class="data-table" style="width:100%;min-width:780px">
            <thead><tr><th>Admin</th><th>Approved devices</th><th>Signed-in devices</th><th>Sessions</th><th>Blocked devices</th><th>Last activity (UTC)</th><th>Manage</th></tr></thead>
            <tbody>
            @foreach($deviceAdmins as $deviceAdmin)
                <tr>
                    <td>{{ $deviceAdmin->name }}<br><small>{{ $deviceAdmin->email }}</small></td>
                    <td>{{ $deviceAdmin->approved_devices_count }} / {{ data_get($deviceAdmin->device_policy, 'total_limit') }}<br><small>{{ data_get($deviceAdmin->device_policy, 'label') }} · Active: Mobile {{ $deviceAdmin->mobile_devices_count }} · Desktop {{ $deviceAdmin->desktop_devices_count }}</small></td>
                    <td>{{ $deviceAdmin->signed_in_devices_count }}</td>
                    <td>{{ $deviceAdmin->signed_in_sessions_count }}</td>
                    <td>{{ $deviceAdmin->blocked_devices_count }}</td>
                    <td>{{ $deviceAdmin->device_sessions_max_last_seen_at ?? 'Not signed in since device enforcement' }}</td>
                    <td><a class="primary-btn" href="{{ route('admin.administration.admins.devices.index', $deviceAdmin) }}">View Devices</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $deviceAdmins->fragment('admin-device-overview')->links() }}
</section>
