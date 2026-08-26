@extends('admin.layouts.app')

@section('title', 'Payout Review')
@section('page-title', 'Payout Review')

@section('content')
@php
    $amount = $money->present((int) $payout->amount_cents, (string) $payout->currency);
    $available = $money->present((int) $balance['available_cents']);
    $locked = $money->present((int) $balance['locked_cents']);
    $paid = $money->present((int) $balance['paid_cents']);
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">PAYOUT REVIEW</div>
        <h1>{{ $payout->method }} <span>{{ $amount['currency'] }} {{ $amount['amount'] }}</span></h1>
        <p>{{ $payout->uuid }}</p>
    </div>
    <a class="secondary-btn" href="{{ route('admin.payouts.index') }}">Back to Payouts</a>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px 18px;margin-bottom:18px;">{{ session('success') }}</section>
@endif

@if($errors->has('payout_review'))
    <section class="panel" style="padding:14px 18px;margin-bottom:18px;">
        <strong>{{ $errors->first('payout_review') }}</strong>
    </section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Status</span><strong>{{ $payout->status }}</strong></div>
    <div class="user-stat-card"><span>Requested</span><strong>{{ $payout->requested_at?->format('Y-m-d H:i') }}</strong></div>
    <div class="user-stat-card"><span>Available Balance</span><strong>USD {{ $available['amount'] }}</strong></div>
    <div class="user-stat-card"><span>Locked Balance</span><strong>USD {{ $locked['amount'] }}</strong></div>
    <div class="user-stat-card"><span>Paid Balance</span><strong>USD {{ $paid['amount'] }}</strong></div>
</div>

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">AFFILIATE</div>
            <h2>{{ $payout->user?->name ?? 'Unknown User' }}</h2>
        </div>
    </div>

    <p><strong>Email:</strong> {{ $payout->user?->email ?? '—' }}</p>
    <p><strong>Referral Code:</strong> {{ $payout->user?->referral_code ?? '—' }}</p>
    <p><strong>Country:</strong> {{ $payout->user?->country_code ?? '—' }}</p>
</section>

<section class="panel" style="padding:22px;margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">IMMUTABLE DESTINATION SNAPSHOT</div>
            <h2>Masked Destination</h2>
        </div>
        @if($permissions['sensitive_destination'])
            <a class="secondary-btn" href="{{ route('admin.payouts.destination', $payout) }}">Reveal for Payout Processing</a>
        @endif
    </div>

    @if($payout->method === \App\Models\PayoutRequest::METHOD_LOCAL_BANK)
        <p><strong>Bank:</strong> {{ $destination['bank_name'] ?? '—' }}</p>
        <p><strong>Holder:</strong> {{ $destination['account_holder'] ?? '—' }}</p>
        <p><strong>Account:</strong> {{ $destination['account_number'] ?? '—' }}</p>
    @else
        <p><strong>Destination:</strong> {{ $destination['label'] ?? 'Configured' }}</p>
        @if(isset($destination['network']))
            <p><strong>Network:</strong> {{ $destination['network'] }}</p>
        @endif
    @endif

    <p><small>The payout amount and destination snapshot cannot be edited by the admin portal.</small></p>
</section>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">COMMISSION ALLOCATION</div>
            <h2>Locked Ledger Sources</h2>
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Commission</th>
                    <th>Earned</th>
                    <th>Allocated</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            @foreach($payout->allocations as $allocation)
                @php
                    $commission = $allocation->commission;
                    $earned = $commission
                        ? $money->present((int) $commission->commission_amount_cents)
                        : null;
                    $allocated = $money->present((int) $allocation->amount_cents);
                @endphp
                <tr>
                    <td>{{ $commission?->uuid ?? 'Missing commission' }}</td>
                    <td>{{ $earned ? 'USD '.$earned['amount'] : '—' }}</td>
                    <td>USD {{ $allocated['amount'] }}</td>
                    <td>{{ $commission?->status ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>

@if($payout->status === \App\Models\PayoutRequest::STATUS_REJECTED && filled($payout->user_visible_reason))
    <section class="panel" style="padding:22px;margin-bottom:18px;">
        <div class="panel-eyebrow">REJECTION REASON</div>
        <p>{{ $payout->user_visible_reason }}</p>
    </section>
@endif

@if($payout->status === \App\Models\PayoutRequest::STATUS_PAID)
    <section class="panel" style="padding:22px;margin-bottom:18px;">
        <div class="panel-eyebrow">SETTLEMENT</div>
        <p><strong>Paid at:</strong> {{ $payout->paid_at?->format('Y-m-d H:i:s') }}</p>
        <p><strong>Provider reference:</strong> Recorded securely</p>
    </section>
@endif

@if($payout->status === \App\Models\PayoutRequest::STATUS_PENDING_REVIEW)
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:18px;">
        @if($permissions['approve'])
            <section class="panel" style="padding:22px;">
                <div class="panel-eyebrow">APPROVE</div>
                <h2>Approve Payout</h2>
                <form method="POST" action="{{ route('admin.payouts.approve', $payout) }}">
                    @csrf
                    @method('PATCH')
                    <label>Review reason</label>
                    <textarea name="reason" rows="4" required minlength="10" maxlength="500">{{ old('reason') }}</textarea>
                    <label>Internal note (optional)</label>
                    <textarea name="internal_note" rows="3" maxlength="1000">{{ old('internal_note') }}</textarea>
                    <button class="primary-btn" type="submit">Approve Payout</button>
                </form>
            </section>
        @endif

        @if($permissions['reject'])
            <section class="panel" style="padding:22px;">
                <div class="panel-eyebrow">REJECT</div>
                <h2>Reject Payout</h2>
                <form method="POST" action="{{ route('admin.payouts.reject', $payout) }}">
                    @csrf
                    @method('PATCH')
                    <label>User-visible reason</label>
                    <textarea name="reason" rows="4" required minlength="10" maxlength="500">{{ old('reason') }}</textarea>
                    <label>Internal note (optional)</label>
                    <textarea name="internal_note" rows="3" maxlength="1000">{{ old('internal_note') }}</textarea>
                    <button class="secondary-btn" type="submit">Reject Payout</button>
                </form>
            </section>
        @endif
    </div>
@endif

@if(
    $payout->status === \App\Models\PayoutRequest::STATUS_APPROVED
    && $permissions['mark_paid']
)
    <section class="panel" style="padding:22px;margin-top:18px;">
        <div class="panel-eyebrow">SETTLEMENT CONFIRMATION</div>
        <h2>Mark Paid</h2>
        <p>Only use this after the external PayPal/Binance/USDT/bank transfer is actually completed.</p>

        <form method="POST" action="{{ route('admin.payouts.mark-paid', $payout) }}">
            @csrf
            @method('PATCH')
            <label>Provider / transaction reference</label>
            <input type="text" name="provider_reference" value="{{ old('provider_reference') }}" required minlength="4" maxlength="190">
            <label>Internal note (optional)</label>
            <textarea name="internal_note" rows="3" maxlength="1000">{{ old('internal_note') }}</textarea>
            <button class="primary-btn" type="submit">Confirm Paid</button>
        </form>
    </section>
@endif
@endsection
