@extends('admin.layouts.app')

@section('title', $client->client_code.' · '.$client->full_name.' | TradeX Administration')
@section('page-title', 'Trading Floor Customer')

@section('content')
@php
    $admin = auth('admin')->user();
    $canManageClient = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.clients.manage');
    $canManageAccess = $admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.access.manage');
    $invoiceBalance = $client->commissionInvoices->sum(fn ($invoice) => (float) $invoice->balance_amount);
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">{{ $client->client_code }} · TRADING FLOOR CUSTOMER</div>
        <h1>{{ $client->full_name }} <span>{{ $client->status }}</span></h1>
        <p>{{ $client->floor_code ?: 'No floor' }} · {{ $client->desk_code ?: 'No desk' }} · {{ $client->package_code ?: 'No package' }}</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.index') }}">Directory</a>
        @if($canManageClient)<a class="btn btn-primary" href="{{ route('admin.trading_floor.clients.edit', $client) }}">Edit Customer</a>@endif
    </div>
</div>

<div class="dashboard-grid" style="margin-top:22px;">
    <div class="stat-card"><div class="stat-card__label">Commission Type</div><div class="stat-card__value" style="font-size:22px;">{{ $client->commission_type }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Billing Cycle</div><div class="stat-card__value" style="font-size:22px;">{{ $client->billing_cycle }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Active Credentials</div><div class="stat-card__value">{{ number_format($client->accessCredentials->where('status', 'ACTIVE')->count()) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Outstanding Commission</div><div class="stat-card__value" style="font-size:22px;">{{ $client->commission_currency }} {{ number_format($invoiceBalance, 2) }}</div></div>
</div>

<div style="display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:18px;margin-top:18px;">
    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">PROFILE</div><h2>Customer Details</h2></div></div>
        <div class="panel-body">
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
                <div><small style="opacity:.6;">Mobile</small><div>{{ $client->mobile ?: '—' }}</div></div>
                <div><small style="opacity:.6;">WhatsApp</small><div>{{ $client->whatsapp_number ?: '—' }}</div></div>
                <div><small style="opacity:.6;">Email</small><div>{{ $client->email ?: '—' }}</div></div>
                <div><small style="opacity:.6;">Identity</small><div>{{ $client->identity_type ?: '—' }} {{ $client->identity_number ?: '' }}</div></div>
                <div><small style="opacity:.6;">Joined</small><div>{{ $client->joined_at?->format('Y-m-d') ?: '—' }}</div></div>
                <div><small style="opacity:.6;">Linked User ID</small><div>{{ $client->user_id ?: '—' }}</div></div>
                <div><small style="opacity:.6;">Emergency Contact</small><div>{{ $client->emergency_contact_name ?: '—' }}</div></div>
                <div><small style="opacity:.6;">Emergency Phone</small><div>{{ $client->emergency_contact_phone ?: '—' }}</div></div>
            </div>
            <div style="margin-top:16px;"><small style="opacity:.6;">Address</small><div>{{ $client->address ?: '—' }}</div></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">COMMISSION</div><h2>Agreement</h2></div></div>
        <div class="panel-body">
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
                <div><small style="opacity:.6;">Type</small><div>{{ $client->commission_type }}</div></div>
                <div><small style="opacity:.6;">Currency</small><div>{{ $client->commission_currency }}</div></div>
                <div><small style="opacity:.6;">Percentage</small><div>{{ $client->commission_rate !== null ? number_format((float) $client->commission_rate, 2).'%' : '—' }}</div></div>
                <div><small style="opacity:.6;">Fixed Amount</small><div>{{ $client->fixed_commission_amount !== null ? $client->commission_currency.' '.number_format((float) $client->fixed_commission_amount, 2) : '—' }}</div></div>
                <div><small style="opacity:.6;">Billing Cycle</small><div>{{ $client->billing_cycle }}</div></div>
                <div><small style="opacity:.6;">Grace Days</small><div>{{ $client->grace_days }}</div></div>
            </div>
        </div>
    </div>
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header">
        <div><div class="panel-eyebrow">ACCESS CONTROL</div><h2>NFC / Fingerprint Credentials</h2></div>
        <span>Raw credential values are never displayed or stored</span>
    </div>
    <div class="panel-body">
        @if($canManageAccess)
            <form method="POST" action="{{ route('admin.trading_floor.clients.credentials.store', $client) }}" style="display:grid;grid-template-columns:180px minmax(240px,1fr) 220px auto;gap:12px;align-items:end;margin-bottom:20px;">
                @csrf
                <div class="form-group" style="margin:0;">
                    <label for="credential_type">Type</label>
                    <select id="credential_type" name="credential_type" required>
                        <option value="NFC">NFC CARD</option>
                        <option value="FINGERPRINT">FINGERPRINT</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="credential_reference">Card UID / Device User Reference</label>
                    <input id="credential_reference" name="credential_reference" type="password" autocomplete="off" required maxlength="255" placeholder="Scanned UID or device-side user reference">
                </div>
                <div class="form-group" style="margin:0;">
                    <label for="device_code">Device Code</label>
                    <input id="device_code" name="device_code" type="text" maxlength="100" placeholder="e.g. ENTRY-READER-01">
                </div>
                <button class="btn btn-primary" type="submit">Assign</button>
            </form>
        @endif

        <div style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead><tr><th>Type</th><th>Protected Reference</th><th>Device</th><th>Issued</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                    @forelse($client->accessCredentials as $credential)
                        <tr>
                            <td>{{ $credential->credential_type }}</td>
                            <td><code>{{ substr($credential->credential_reference, 0, 12) }}…</code></td>
                            <td>{{ $credential->device_code ?: '—' }}</td>
                            <td>{{ $credential->issued_at?->format('Y-m-d H:i') ?: '—' }}</td>
                            <td><span class="badge">{{ $credential->status }}</span></td>
                            <td style="text-align:right;">
                                @if($canManageAccess && $credential->status === 'ACTIVE')
                                    <form method="POST" action="{{ route('admin.trading_floor.clients.credentials.revoke', [$client, $credential]) }}" style="display:inline-flex;gap:8px;align-items:center;">
                                        @csrf
                                        @method('PATCH')
                                        <input name="revocation_reason" type="text" required maxlength="1000" placeholder="Reason" style="min-width:180px;">
                                        <button class="btn btn-secondary" type="submit">Revoke</button>
                                    </form>
                                @else
                                    <span style="opacity:.55;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:24px;text-align:center;opacity:.7;">No access credentials assigned.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p style="margin:14px 0 0;opacity:.64;font-size:12px;">Fingerprint biometric templates remain on the biometric device/provider. TradeX stores only a one-way protected device reference for matching and audit purposes.</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:18px;">
    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">ATTENDANCE</div><h2>Recent Access Activity</h2></div></div>
        <div class="panel-body" style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead><tr><th>Time</th><th>Direction</th><th>Method</th><th>Decision</th></tr></thead>
                <tbody>
                    @forelse($client->accessLogs as $log)
                        <tr><td>{{ $log->accessed_at?->format('Y-m-d H:i:s') }}</td><td>{{ $log->direction }}</td><td>{{ $log->method }}</td><td>{{ $log->decision }}</td></tr>
                    @empty
                        <tr><td colspan="4" style="padding:22px;text-align:center;opacity:.7;">No access records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">BILLING</div><h2>Recent Commission Invoices</h2></div></div>
        <div class="panel-body" style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead><tr><th>Invoice</th><th>Due</th><th>Amount</th><th>Balance</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse($client->commissionInvoices as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->due_date?->format('Y-m-d') }}</td>
                            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->commission_amount, 2) }}</td>
                            <td>{{ $invoice->currency }} {{ number_format((float) $invoice->balance_amount, 2) }}</td>
                            <td>{{ $invoice->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding:22px;text-align:center;opacity:.7;">No commission invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
