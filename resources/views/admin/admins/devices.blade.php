@extends('admin.layouts.app')

@section('title', 'Admin Devices')
@section('page-title', 'Admin Devices')

@section('content')
<div class="page-header">
    <div>
        <h1>Devices · {{ $adminUser->name }}</h1>
        <p>One mobile and one desktop browser. Blocked attempts do not interrupt approved devices.</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.administration.admins.index') }}">Back to Admin Users</a>
</div>
@if(session('success'))<div class="panel" role="status" style="padding:16px">{{ session('success') }}</div>@endif
@if($errors->any())<div class="panel" role="alert" style="padding:16px">{{ $errors->first() }}</div>@endif
<section class="panel" style="padding:16px;margin-bottom:20px">
    <p>Only Super Admin can approve or revoke devices. Revoke the existing device in a category before approving its replacement. Revoking your current device signs you out on your next request.</p>
    <p>Device identity is tied to this browser’s secure cookie. Another browser, private browsing or cleared cookies counts as a new device. Browser/OS details are reported by the browser; IMEI and hardware serial numbers are unavailable.</p>
    <div style="overflow-x:auto">
        <table class="data-table" style="width:100%;min-width:760px">
            <thead><tr><th>Device</th><th>Status</th><th>Last login IP / time</th><th>Browser / OS details</th><th>Manage</th></tr></thead>
            <tbody>
            @forelse($devices as $device)
                <tr>
                    <td>#{{ $device->id }} · {{ $device->device_slot }} @if(session('tradex_admin_device_id') == $device->id)<strong>(Current)</strong>@endif</td>
                    <td>{{ $device->status }}<br><small>{{ $device->live_sessions_count }} signed-in session(s)</small></td>
                    <td>{{ $device->ip_address }}<br>{{ $device->last_seen_at?->utc()->format('Y-m-d H:i:s') }} UTC</td>
                    <td style="max-width:360px;overflow-wrap:anywhere">{{ $device->user_agent }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.administration.admins.devices.update', [$adminUser, $device]) }}">
                            @csrf @method('PATCH')
                            <label for="reason-{{ $device->id }}">Reason</label>
                            <input id="reason-{{ $device->id }}" name="reason" required minlength="5" maxlength="500" placeholder="Reason for access change">
                            @if($device->status !== 'ACTIVE')
                                <button class="primary-btn" name="action" value="approve">Approve device</button>
                            @endif
                            @if($device->status !== 'REVOKED')
                                <button class="secondary-btn" name="action" value="revoke">Revoke device</button>
                            @endif
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No devices registered. Devices are recorded after successful authentication.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $devices->links() }}
</section>
<section class="panel" style="padding:16px">
    <h2>Signed-in Sessions</h2>
    <p>Sessions disappear after logout, revoke or timeout. Closing a browser does not immediately end its server session.</p>
    <div style="overflow-x:auto"><table class="data-table" style="width:100%;min-width:580px">
        <thead><tr><th>Device</th><th>IP</th><th>Last activity (UTC)</th><th>Expires (UTC)</th></tr></thead>
        <tbody>@forelse($sessions as $deviceSession)
            <tr><td>#{{ $deviceSession->admin_device_id }}</td><td>{{ $deviceSession->ip_address }}</td><td>{{ $deviceSession->last_seen_at?->utc()->format('Y-m-d H:i:s') }}</td><td>{{ $deviceSession->expires_at?->utc()->format('Y-m-d H:i:s') }}</td></tr>
        @empty<tr><td colspan="4">No signed-in sessions.</td></tr>@endforelse</tbody>
    </table></div>
</section>
<section class="panel" style="padding:16px">
    <h2>Recent blocked attempts and alerts</h2>
    <p>ACCEPTED means the provider accepted the message; inbox or WhatsApp receipt is not confirmed.</p>
    @forelse($incidents as $incident)
        <article style="padding:12px 0;border-bottom:1px solid #687080">
            <strong>#{{ $incident->id }} · {{ $incident->reason }} · {{ $incident->device_slot }}</strong>
            <p>{{ $incident->created_at->utc()->format('Y-m-d H:i:s') }} UTC · {{ $incident->ip_address }}</p>
            <p style="overflow-wrap:anywhere">{{ $incident->user_agent }}</p>
            @forelse($alerts->get($incident->id, collect()) as $alert)
                <p>{{ strtoupper($alert->channel) }} · {{ $alert->recipient }} · {{ $alert->status }} @if($alert->failure_code) ({{ $alert->failure_code }}) @endif</p>
            @empty
                <p>Alert dispatch not scheduled. Check the server queue and notification configuration.</p>
            @endforelse
        </article>
    @empty
        <p>No blocked attempts.</p>
    @endforelse
</section>
@endsection
