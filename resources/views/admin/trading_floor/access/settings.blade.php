@extends('admin.layouts.app')

@section('title', 'Trading Floor Access Settings | TradeX Administration')
@section('page-title', 'Trading Floor Access Settings')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">ADMIN-30 · ACCESS CONTROL</div>
        <h1>Access <span>Enforcement</span></h1>
        <p>Manage reader devices, commission enforcement and physical Trading Floor access policy.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.access.index') }}">Attendance & Access</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.index') }}">Floor Dashboard</a>
    </div>
</div>

@if(session('new_device_token'))
    <div class="panel" style="margin-top:18px;border:1px solid rgba(245,190,70,.45);">
        <div class="panel-header"><div><div class="panel-eyebrow">ONE-TIME SECRET</div><h2>Reader Provisioning Token</h2></div></div>
        <div class="panel-body">
            <p>Copy this token into the reader now. After this page changes, the raw token will not be available again.</p>
            <div style="display:grid;grid-template-columns:220px 1fr;gap:12px;">
                <div><small style="opacity:.65;">Device Code</small><div><code>{{ session('new_device_code') }}</code></div></div>
                <div><small style="opacity:.65;">Device Token</small><div style="word-break:break-all;"><code>{{ session('new_device_token') }}</code></div></div>
            </div>
        </div>
    </div>
@endif

<div style="display:grid;grid-template-columns:minmax(0,1fr) minmax(340px,.8fr);gap:18px;margin-top:18px;">
    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">POLICY</div><h2>Commission Access Rules</h2></div></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.trading_floor.access.settings.update') }}">
                @csrf
                @method('PATCH')
                <div class="form-group">
                    <label for="commission_enforcement_mode">Commission Enforcement</label>
                    <select id="commission_enforcement_mode" name="commission_enforcement_mode" required>
                        @foreach(['NONE' => 'No Restriction', 'WARNING' => 'Warning Only', 'BLOCK_AFTER_GRACE' => 'Block After Grace Period', 'BLOCK_IMMEDIATE' => 'Block Immediately When Overdue'] as $value => $label)
                            <option value="{{ $value }}" @selected($settings->commission_enforcement_mode === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="block_after_overdue_days">Global Grace Days</label>
                    <input id="block_after_overdue_days" name="block_after_overdue_days" type="number" min="0" max="365" value="{{ $settings->block_after_overdue_days }}" required>
                    <small style="opacity:.65;">Effective grace uses the larger of this value and the customer's own grace days.</small>
                </div>
                <div style="display:grid;gap:10px;margin:16px 0;">
                    <label><input type="checkbox" name="api_enabled" value="1" @checked($settings->api_enabled)> Reader API enabled</label>
                    <label><input type="checkbox" name="require_active_client" value="1" @checked($settings->require_active_client)> Require ACTIVE customer status</label>
                    <label><input type="checkbox" name="enforce_floor_match" value="1" @checked($settings->enforce_floor_match)> Enforce reader floor = customer floor</label>
                    <label><input type="checkbox" name="allow_exit_when_blocked" value="1" @checked($settings->allow_exit_when_blocked)> Always allow EXIT when otherwise blocked</label>
                </div>
                <button class="btn btn-primary" type="submit">Save Access Policy</button>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">READER PROVISIONING</div><h2>Add Device</h2></div></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.trading_floor.access.devices.store') }}">
                @csrf
                <div class="form-group"><label>Device Code</label><input name="device_code" required maxlength="100" placeholder="ENTRY-READER-01"></div>
                <div class="form-group"><label>Name</label><input name="name" required maxlength="255" placeholder="Main Entrance NFC Reader"></div>
                <div class="form-group"><label>Floor Code</label><input name="floor_code" maxlength="50" placeholder="FLOOR-01"></div>
                <button class="btn btn-primary" type="submit">Provision Reader</button>
            </form>
        </div>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">AUTHORIZED HARDWARE</div><h2>Reader Devices</h2></div></div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead><tr><th>Device</th><th>Floor</th><th>Status</th><th>Last Seen</th><th>Last IP</th><th style="text-align:right;">Control</th></tr></thead>
            <tbody>
            @forelse($devices as $device)
                <tr>
                    <td><strong>{{ $device->device_code }}</strong><div style="opacity:.65;font-size:12px;">{{ $device->name }}</div></td>
                    <td>{{ $device->floor_code ?: '—' }}</td>
                    <td><span class="badge">{{ $device->status }}</span></td>
                    <td>{{ $device->last_seen_at?->format('Y-m-d H:i:s') ?: 'Never' }}</td>
                    <td>{{ $device->last_ip ?: '—' }}</td>
                    <td style="text-align:right;">
                        @if($device->status === 'ACTIVE')
                            <form method="POST" action="{{ route('admin.trading_floor.access.devices.rotate', $device) }}" style="display:inline;">
                                @csrf @method('PATCH')
                                <button class="btn btn-secondary" type="submit">Rotate Token</button>
                            </form>
                            <form method="POST" action="{{ route('admin.trading_floor.access.devices.revoke', $device) }}" style="display:inline-flex;gap:6px;">
                                @csrf @method('PATCH')
                                <input name="revocation_reason" required maxlength="1000" placeholder="Revocation reason" style="min-width:180px;">
                                <button class="btn btn-secondary" type="submit">Revoke</button>
                            </form>
                        @else
                            <span style="opacity:.6;">Revoked {{ $device->revoked_at?->format('Y-m-d H:i') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:24px;text-align:center;opacity:.7;">No reader devices provisioned.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">SECURITY AUDIT</div><h2>Recent Scan Attempts</h2></div></div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table" style="width:100%;">
            <thead><tr><th>Time</th><th>Device</th><th>Customer</th><th>Method</th><th>Direction</th><th>Decision</th><th>Reason</th></tr></thead>
            <tbody>
            @forelse($attempts as $attempt)
                <tr>
                    <td>{{ $attempt->occurred_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $attempt->device_code }}</td>
                    <td>{{ $attempt->client?->client_code ?? 'Unknown' }}</td>
                    <td>{{ $attempt->credential_type }}</td>
                    <td>{{ $attempt->direction }}</td>
                    <td><strong>{{ $attempt->decision }}</strong></td>
                    <td>{{ $attempt->reason_code }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="padding:24px;text-align:center;opacity:.7;">No reader scan attempts yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
