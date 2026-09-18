@extends('admin.layouts.app')
@section('title',$membership->membership_number.' | TradeX Administration')
@section('page-title','Trading Floor Membership')
@section('content')
@php
    $admin=auth('admin')->user();
    $canManage=$admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.memberships.manage');
    $canManagePayments=$admin?->isSuperAdmin() || $admin?->hasPermission('trading_floor.membership_payments.manage');
    $openMembership=in_array($membership->status,['PENDING','ACTIVE','SUSPENDED'],true);
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">{{ $membership->membership_number }} · ADMIN-31</div>
        <h1>{{ $membership->client?->full_name }} <span>{{ $membership->status }}</span></h1>
        <p>{{ $membership->client?->client_code }} · {{ $membership->package?->name }} · {{ $membership->floor?->code }} / {{ $membership->desk?->code ?: 'No desk' }}</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.index') }}">Memberships</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.documents.invoice',$membership) }}">Print Invoice</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.seat_map') }}">Seat Map</a>
        <a class="btn btn-secondary" href="{{ route('admin.trading_floor.clients.show',$membership->client) }}">Customer Profile</a>
    </div>
</div>

<div class="dashboard-grid" style="margin-top:22px;">
    <div class="stat-card"><div class="stat-card__label">Package</div><div class="stat-card__value" style="font-size:18px;">{{ $membership->package?->code }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Floor / Desk</div><div class="stat-card__value" style="font-size:18px;">{{ $membership->floor?->code }} / {{ $membership->desk?->code ?: '—' }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Price</div><div class="stat-card__value" style="font-size:18px;">{{ $membership->currency }} {{ number_format((float)$membership->price_amount,2) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Balance</div><div class="stat-card__value" style="font-size:18px;">{{ $membership->currency }} {{ number_format((float)$membership->balance_amount,2) }}</div></div>
    <div class="stat-card"><div class="stat-card__label">Payment</div><div class="stat-card__value" style="font-size:18px;">{{ $membership->payment_status }}</div></div>
</div>

<div style="display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;margin-top:18px;">
    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">MEMBERSHIP SNAPSHOT</div><h2>Details</h2></div></div>
        <div class="panel-body">
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
                <div><small>Start</small><div>{{ $membership->starts_at?->format('Y-m-d') }}</div></div>
                <div><small>Expiry</small><div>{{ $membership->expires_at?->format('Y-m-d') }}</div></div>
                <div><small>Due Date</small><div>{{ $membership->due_date?->format('Y-m-d') ?: '—' }}</div></div>
                <div><small>Billing Cycle</small><div>{{ $membership->billing_cycle }}</div></div>
                <div><small>Paid</small><div>{{ $membership->currency }} {{ number_format((float)$membership->paid_amount,2) }}</div></div>
                <div><small>Balance</small><div>{{ $membership->currency }} {{ number_format((float)$membership->balance_amount,2) }}</div></div>
                <div><small>Auto Renew</small><div>{{ $membership->auto_renew ? 'YES' : 'NO' }}</div></div>
                <div><small>Activated</small><div>{{ $membership->activated_at?->format('Y-m-d H:i') ?: '—' }}</div></div>
                @if($membership->renewedFrom)<div><small>Renewed From</small><div><a href="{{ route('admin.trading_floor.memberships.show',$membership->renewedFrom) }}">{{ $membership->renewedFrom->membership_number }}</a></div></div>@endif
            </div>
            @if($membership->notes)<p style="margin-top:18px;">{{ $membership->notes }}</p>@endif
        </div>
    </div>

    <div style="display:grid;gap:18px;align-content:start;">
        @if($canManage && $membership->status==='PENDING')
            <div class="panel"><div class="panel-header"><div><div class="panel-eyebrow">ACTIVATION</div><h2>Activate Membership</h2></div></div><div class="panel-body"><p>Activation synchronizes the customer floor, desk and package allocation.</p><form method="POST" action="{{ route('admin.trading_floor.memberships.activate',$membership) }}">@csrf @method('PATCH')<button class="btn btn-primary" type="submit" style="width:100%;">Activate</button></form></div></div>
        @endif

        @if($canManage && in_array($membership->status,['ACTIVE','EXPIRED'],true))
            <div class="panel">
                <div class="panel-header"><div><div class="panel-eyebrow">RENEWAL</div><h2>Renew Membership</h2></div></div>
                <div class="panel-body">
                    <p style="margin-top:0;">Creates a new pending membership. The current record remains unchanged for history.</p>
                    <form method="POST" action="{{ route('admin.trading_floor.memberships.renew',$membership) }}">
                        @csrf
                        <div class="form-group"><label>Package</label><select name="trading_floor_package_id">@foreach($packages as $package)<option value="{{ $package->id }}" @selected($package->id===$membership->trading_floor_package_id)>{{ $package->code }} · {{ $package->name }} · {{ $package->currency }} {{ number_format((float)$package->price_amount,2) }}</option>@endforeach</select></div>
                        <div class="form-group"><label>Floor</label><select name="trading_floor_floor_id">@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected($floor->id===$membership->trading_floor_floor_id)>{{ $floor->code }} · {{ $floor->name }}</option>@endforeach</select></div>
                        <div class="form-group"><label>Desk / Seat</label><select name="trading_floor_desk_id"><option value="">No dedicated desk</option>@foreach($desks as $desk)<option value="{{ $desk->id }}" @selected($desk->id===$membership->trading_floor_desk_id)>{{ $desk->floor?->code }} / {{ $desk->code }} {{ $desk->name ? '· '.$desk->name : '' }}</option>@endforeach</select></div>
                        <div class="form-group"><label>Start Date (optional)</label><input type="date" name="starts_at"></div>
                        <div class="form-group"><label>Due Date (optional)</label><input type="date" name="due_date"></div>
                        <label style="display:flex;gap:8px;align-items:center;margin:12px 0;"><input type="checkbox" name="auto_renew" value="1" @checked($membership->auto_renew)> Continue Auto Renew</label>
                        <div class="form-group"><label>Reason / Note</label><textarea name="reason" rows="2" maxlength="2000" placeholder="Renewal note"></textarea></div>
                        <button class="btn btn-primary" type="submit" style="width:100%;">Create Renewal</button>
                    </form>
                </div>
            </div>
        @endif

        @if($canManage && !in_array($membership->status,['CANCELLED','EXPIRED'],true))
            <div class="panel"><div class="panel-header"><div><div class="panel-eyebrow">CLOSURE</div><h2>Cancel Membership</h2></div></div><div class="panel-body"><form method="POST" action="{{ route('admin.trading_floor.memberships.cancel',$membership) }}">@csrf @method('PATCH')<div class="form-group"><label>Reason</label><textarea name="reason" required rows="3" maxlength="2000"></textarea></div><button class="btn btn-secondary" type="submit" style="width:100%;">Cancel & Release Seat</button></form></div></div>
        @endif
    </div>
</div>

@if($canManage && $openMembership)
<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin-top:18px;">
    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">PACKAGE CHANGE</div><h2>Upgrade / Downgrade</h2></div></div>
        <div class="panel-body">
            <p style="margin-top:0;">Target package full current price becomes the new membership price snapshot. No automatic proration or FX conversion is performed.</p>
            <form method="POST" action="{{ route('admin.trading_floor.memberships.package.update',$membership) }}">
                @csrf @method('PATCH')
                <div class="form-group"><label>Target Package</label><select name="trading_floor_package_id" required>@foreach($packages as $package)<option value="{{ $package->id }}" @selected($package->id===$membership->trading_floor_package_id)>{{ $package->code }} · {{ $package->name }} · {{ $package->currency }} {{ number_format((float)$package->price_amount,2) }}</option>@endforeach</select></div>
                <div class="form-group"><label>Reason</label><textarea name="reason" required rows="3" maxlength="2000"></textarea></div>
                <button class="btn btn-primary" type="submit">Update Package</button>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">SEAT TRANSFER</div><h2>Floor / Desk Transfer</h2></div></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.trading_floor.memberships.transfer',$membership) }}">
                @csrf @method('PATCH')
                <div class="form-group"><label>Floor</label><select name="trading_floor_floor_id" required>@foreach($floors as $floor)<option value="{{ $floor->id }}" @selected($floor->id===$membership->trading_floor_floor_id)>{{ $floor->code }} · {{ $floor->name }}</option>@endforeach</select></div>
                <div class="form-group"><label>Desk / Seat</label><select name="trading_floor_desk_id"><option value="">No dedicated desk</option>@foreach($desks as $desk)<option value="{{ $desk->id }}" @selected($desk->id===$membership->trading_floor_desk_id)>{{ $desk->floor?->code }} / {{ $desk->code }} {{ $desk->name ? '· '.$desk->name : '' }}</option>@endforeach</select></div>
                <div class="form-group"><label>Reason</label><textarea name="reason" required rows="3" maxlength="2000"></textarea></div>
                <button class="btn btn-primary" type="submit">Transfer Allocation</button>
            </form>
        </div>
    </div>
</div>
@endif

<div style="display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr);gap:18px;margin-top:18px;">
    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">PAYMENT LEDGER</div><h2>Membership Payments</h2></div></div>
        <div class="panel-body" style="overflow-x:auto;">
            <table class="data-table" style="width:100%;">
                <thead><tr><th>Received</th><th>Method</th><th>Reference</th><th>Amount</th><th>Admin</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($membership->payments as $payment)
                    <tr>
                        <td>{{ $payment->received_at?->format('Y-m-d H:i') }}</td><td>{{ str_replace('_',' ',$payment->payment_method) }}</td><td>{{ $payment->transaction_reference ?: '—' }}</td><td>{{ $payment->currency }} {{ number_format((float)$payment->amount,2) }}</td><td>{{ $payment->receivedByAdmin?->name ?: '—' }}</td><td><span class="badge">{{ $payment->status }}</span>@if($payment->status==='REVERSED')<div style="font-size:11px;opacity:.65;">{{ $payment->reversal_reason }}</div>@endif</td>
                        <td><div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap;"><a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.documents.receipt',[$membership,$payment]) }}">Receipt</a>@if($canManagePayments && $payment->status==='RECEIVED')<form method="POST" action="{{ route('admin.trading_floor.memberships.payments.reverse',[$membership,$payment]) }}" style="display:inline-flex;gap:6px;">@csrf @method('PATCH')<input name="reversal_reason" required maxlength="2000" placeholder="Reversal reason" style="min-width:150px;"><button class="btn btn-secondary" type="submit">Reverse</button></form>@endif</div></td>
                    </tr>
                @empty<tr><td colspan="7" style="padding:24px;text-align:center;opacity:.7;">No membership payments recorded.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($canManagePayments && (float)$membership->balance_amount>0)
    <div class="panel">
        <div class="panel-header"><div><div class="panel-eyebrow">COLLECTION</div><h2>Record Payment</h2></div></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('admin.trading_floor.memberships.payments.store',$membership) }}">
                @csrf
                <div class="form-group"><label>Amount ({{ $membership->currency }})</label><input type="number" name="amount" min="0.01" step="0.01" max="{{ $membership->balance_amount }}" required></div>
                <div class="form-group"><label>Payment Method</label><select name="payment_method" required>@foreach(['CASH','BANK_TRANSFER','CARD','PAYPAL','CRYPTO','OTHER'] as $method)<option value="{{ $method }}">{{ str_replace('_',' ',$method) }}</option>@endforeach</select></div>
                <div class="form-group"><label>Transaction Reference</label><input name="transaction_reference" maxlength="190"></div>
                <div class="form-group"><label>Received At</label><input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></div>
                <div class="form-group"><label>Notes</label><textarea name="notes" rows="3" maxlength="3000"></textarea></div>
                <button class="btn btn-primary" type="submit" style="width:100%;">Record Payment</button>
            </form>
        </div>
    </div>
    @endif
</div>

<div class="panel" style="margin-top:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">AUDIT HISTORY</div><h2>Membership Changes</h2></div></div>
    <div class="panel-body" style="overflow-x:auto;">
        <table class="data-table" style="width:100%;"><thead><tr><th>Time</th><th>Change</th><th>Reason</th><th>Changed By</th></tr></thead><tbody>
        @forelse($membership->changes as $change)<tr><td>{{ $change->occurred_at?->format('Y-m-d H:i:s') }}</td><td><strong>{{ str_replace('_',' ',$change->change_type) }}</strong></td><td>{{ $change->reason ?: '—' }}</td><td>{{ $change->changedByAdmin?->name ?: (($change->metadata['source'] ?? null)==='SYSTEM_SWEEP' ? 'SYSTEM SWEEP' : '—') }}</td></tr>@empty<tr><td colspan="4" style="padding:24px;text-align:center;opacity:.7;">No membership changes recorded yet.</td></tr>@endforelse
        </tbody></table>
    </div>
</div>

@if($membership->renewals->isNotEmpty())
<div class="panel" style="margin-top:18px;"><div class="panel-header"><div><div class="panel-eyebrow">RENEWAL CHAIN</div><h2>Renewed Memberships</h2></div></div><div class="panel-body"><div style="display:flex;gap:10px;flex-wrap:wrap;">@foreach($membership->renewals as $renewal)<a class="btn btn-secondary" href="{{ route('admin.trading_floor.memberships.show',$renewal) }}">{{ $renewal->membership_number }} · {{ $renewal->status }}</a>@endforeach</div></div></div>
@endif
@endsection
