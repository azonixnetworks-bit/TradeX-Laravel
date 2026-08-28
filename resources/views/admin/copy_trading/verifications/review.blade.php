@extends('admin.layouts.app')

@section('title', 'Review Copy Trade Broker Account')
@section('page-title', 'Broker Account Review')

@section('content')
@php
    $fromAccounts = request()->routeIs('admin.copy_trading.accounts.*');
    $backRoute = $fromAccounts
        ? route('admin.copy_trading.accounts.index')
        : route('admin.copy_trading.verifications.index');

    $isPending = $account->status === 'PENDING';
    $isNeedsResubmission = $account->status === 'NEEDS_RESUBMISSION';
    $isEscalated = $account->status === 'ESCALATED';
    $canResolveEscalated = $isEscalated && ($permissions['super_admin'] ?? false);
@endphp

<div class="page-header">
    <div>
        <div class="page-eyebrow">COPY TRADING · SENSITIVE VERIFICATION REVIEW</div>
        <h1>Broker Account <span>Review</span></h1>
        <p>
            Review partner attribution and broker-account eligibility evidence.
            Opening this page is audit logged because the full account number is
            exposed to an authorized reviewer.
        </p>
    </div>

    <a class="secondary-btn" href="{{ $backRoute }}">Back</a>
</div>

@if(session('success'))
    <div class="admin-form-callout" style="margin-bottom:18px;">
        <div class="admin-form-callout-icon" aria-hidden="true">✓</div>
        <div>{{ session('success') }}</div>
    </div>
@endif

@if($errors->any())
    <div class="admin-form-callout" style="margin-bottom:18px;">
        <div class="admin-form-callout-icon" aria-hidden="true">!</div>
        <div>
            <strong>Review action not completed</strong>
            <ul style="margin:8px 0 0 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<div class="admin-form-callout">
    <div class="admin-form-callout-icon" aria-hidden="true">◇</div>
    <div>
        <strong>Security and business boundary</strong>
        VERIFY confirms an approved TradeX partner relationship, LIVE broker
        account and configured eligible currency. It does not activate Copy
        Trading. Exceptional or disputed cases must be escalated.
    </div>
</div>

<section class="panel admin-form-panel" style="margin-bottom:18px;">
    <div class="admin-form-heading">
        <div class="panel-eyebrow">CUSTOMER BROKER ACCOUNT</div>
        <h2>Submitted Account</h2>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label>Customer</label>
            <div>{{ $account->user?->name ?? 'Unknown customer' }}</div>
            <small class="form-help">{{ $account->user?->email ?? '—' }}</small>
        </div>

        <div class="form-group">
            <label>Broker</label>
            <div>{{ $account->broker?->name ?? '—' }}</div>
            <small class="form-help">{{ $account->broker?->code ?? '—' }}</small>
        </div>

        <div class="form-group">
            <label>Market</label>
            <div>{{ $account->brokerMarket?->market_category ?? '—' }}</div>
            <small class="form-help">
                Required currency: {{ $account->brokerMarket?->required_currency ?? '—' }}
            </small>
        </div>

        <div class="form-group">
            <label>Status</label>
            <div>{{ str_replace('_', ' ', $account->status) }}</div>
            <small class="form-help">
                Submitted {{ $account->submitted_at?->format('Y-m-d H:i') ?? '—' }}
            </small>
        </div>

        <div class="form-group full-width">
            <label>Full Broker Account Number — Sensitive</label>
            <div
                style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:18px;letter-spacing:2px;"
            >
                {{ $fullAccountNumber }}
            </div>
            <small class="form-help">
                This value is encrypted at rest. Access to this review page is audit logged.
            </small>
        </div>

        @if($account->last_status_reason)
            <div class="form-group full-width">
                <label>Latest Status Reason</label>
                <div>{{ $account->last_status_reason }}</div>
            </div>
        @endif
    </div>
</section>

@if($isEscalated && !($permissions['super_admin'] ?? false))
    <div class="admin-form-callout" style="margin-bottom:18px;">
        <div class="admin-form-callout-icon" aria-hidden="true">!</div>
        <div>
            <strong>Escalated case</strong>
            This exceptional broker-account case requires Founder/Super Admin
            review. Delegated verification admins cannot resolve it.
        </div>
    </div>
@endif

@if(($permissions['verify'] ?? false) && ($isPending || $canResolveEscalated))
<section class="panel admin-form-panel" style="margin-bottom:18px;">
    <div class="admin-form-heading">
        <div class="panel-eyebrow">VERIFY PARTNER RELATIONSHIP</div>
        <h2>Verify Broker Account</h2>
        <p>
            Confirm all three eligibility facts against the approved partner
            portal/evidence before verification.
        </p>
    </div>

    <form
        method="POST"
        enctype="multipart/form-data"
        action="{{ route('admin.copy_trading.verifications.verify', ['copyTradeBrokerAccount' => $account]) }}"
    >
        @csrf
        @method('PATCH')

        <div class="form-grid">
            <div class="form-group">
                <label for="verified_currency">Verified Currency</label>
                <input
                    id="verified_currency"
                    type="text"
                    name="verified_currency"
                    maxlength="10"
                    required
                    value="{{ old('verified_currency', $account->brokerMarket?->required_currency) }}"
                    autocomplete="off"
                >
                <small class="form-help">
                    Must exactly match the broker-market configured eligible currency.
                </small>
            </div>

            <div class="form-group">
                <label for="portal_reference">Partner Portal Reference</label>
                <input
                    id="portal_reference"
                    type="text"
                    name="portal_reference"
                    maxlength="500"
                    value="{{ old('portal_reference') }}"
                    placeholder="Reference / case / portal evidence identifier"
                    autocomplete="off"
                >
                <small class="form-help">
                    Portal reference or an evidence file is mandatory.
                </small>
            </div>

            <div class="form-group full-width">
                <label style="display:flex;gap:10px;align-items:flex-start;">
                    <input
                        type="checkbox"
                        name="partner_attribution_confirmed"
                        value="1"
                        required
                        @checked(old('partner_attribution_confirmed'))
                    >
                    <span>
                        I confirmed this broker account is under the approved TradeX IB / referral relationship.
                    </span>
                </label>
            </div>

            <div class="form-group full-width">
                <label style="display:flex;gap:10px;align-items:flex-start;">
                    <input
                        type="checkbox"
                        name="live_account_confirmed"
                        value="1"
                        required
                        @checked(old('live_account_confirmed'))
                    >
                    <span>I confirmed this is a LIVE broker account, not demo.</span>
                </label>
            </div>

            <div class="form-group">
                <label for="verify_evidence">Evidence File</label>
                <input
                    id="verify_evidence"
                    type="file"
                    name="evidence"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                >
                <small class="form-help">JPG, PNG, WEBP or PDF. Private storage only.</small>
            </div>

            <div class="form-group full-width">
                <label for="verify_reason">Internal Verification Note</label>
                <textarea
                    id="verify_reason"
                    name="reason"
                    maxlength="1000"
                    rows="4"
                    placeholder="Optional verification context. Do not store passwords or broker login credentials."
                >{{ old('reason') }}</textarea>
            </div>
        </div>

        <div class="admin-form-actions">
            <button class="primary-btn" type="submit">Verify Broker Account</button>
        </div>
    </form>
</section>
@endif

@if(($isPending || $isNeedsResubmission || $canResolveEscalated))
<section class="panel admin-form-panel" style="margin-bottom:18px;">
    <div class="admin-form-heading">
        <div class="panel-eyebrow">EXCEPTION DECISIONS</div>
        <h2>Reject / Resubmit / Escalate</h2>
        <p>Every decision requires a reason and is written to the immutable audit trail.</p>
    </div>

    <div class="form-grid">
        @if(($permissions['request_resubmission'] ?? false) && $isPending)
            <form
                method="POST"
                enctype="multipart/form-data"
                action="{{ route('admin.copy_trading.verifications.request-resubmission', ['copyTradeBrokerAccount' => $account]) }}"
                class="form-group"
            >
                @csrf
                @method('PATCH')
                <label for="resubmit_reason">Needs Resubmission</label>
                <textarea
                    id="resubmit_reason"
                    name="reason"
                    maxlength="1000"
                    rows="4"
                    required
                    placeholder="What must the customer correct?"
                ></textarea>
                <input
                    type="text"
                    name="portal_reference"
                    maxlength="500"
                    placeholder="Optional portal reference"
                    style="margin-top:10px;"
                >
                <input
                    type="file"
                    name="evidence"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    style="margin-top:10px;"
                >
                <button class="secondary-btn" type="submit" style="margin-top:12px;">
                    Request Resubmission
                </button>
            </form>
        @endif

        @if(($permissions['reject'] ?? false) && ($isPending || $isNeedsResubmission || $canResolveEscalated))
            <form
                method="POST"
                enctype="multipart/form-data"
                action="{{ route('admin.copy_trading.verifications.reject', ['copyTradeBrokerAccount' => $account]) }}"
                class="form-group"
            >
                @csrf
                @method('PATCH')
                <label for="reject_reason">Reject</label>
                <textarea
                    id="reject_reason"
                    name="reason"
                    maxlength="1000"
                    rows="4"
                    required
                    placeholder="Document the rejection reason."
                ></textarea>
                <input
                    type="text"
                    name="portal_reference"
                    maxlength="500"
                    placeholder="Optional portal reference"
                    style="margin-top:10px;"
                >
                <input
                    type="file"
                    name="evidence"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    style="margin-top:10px;"
                >
                <button class="secondary-btn" type="submit" style="margin-top:12px;">
                    Reject Account
                </button>
            </form>
        @endif

        @if(($permissions['escalate'] ?? false) && !$isEscalated && ($isPending || $isNeedsResubmission))
            <form
                method="POST"
                enctype="multipart/form-data"
                action="{{ route('admin.copy_trading.verifications.escalate', ['copyTradeBrokerAccount' => $account]) }}"
                class="form-group"
            >
                @csrf
                @method('PATCH')
                <label for="escalate_reason">Escalate Exceptional Case</label>
                <textarea
                    id="escalate_reason"
                    name="reason"
                    maxlength="1000"
                    rows="4"
                    required
                    placeholder="Duplicate, ownership dispute, suspicious evidence, inconsistent details, security flag, etc."
                ></textarea>
                <input
                    type="text"
                    name="portal_reference"
                    maxlength="500"
                    placeholder="Optional portal reference"
                    style="margin-top:10px;"
                >
                <input
                    type="file"
                    name="evidence"
                    accept="image/jpeg,image/png,image/webp,application/pdf"
                    style="margin-top:10px;"
                >
                <button class="secondary-btn" type="submit" style="margin-top:12px;">
                    Escalate to Super Admin
                </button>
            </form>
        @endif
    </div>
</section>
@endif

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">AUDITED REVIEW HISTORY</div>
            <h2>Verification Timeline</h2>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Status</th>
                    <th>Evidence</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
            @forelse($account->reviews as $review)
                <tr>
                    <td>{{ $review->created_at?->format('Y-m-d H:i:s') ?? '—' }}</td>
                    <td>{{ $review->adminUser?->name ?? 'System / unavailable' }}</td>
                    <td>{{ str_replace('_', ' ', $review->action) }}</td>
                    <td>
                        {{ $review->old_status ?? '—' }}
                        →
                        {{ $review->new_status ?? '—' }}
                    </td>
                    <td>
                        @if(($permissions['view_evidence'] ?? false) && $review->hasEvidenceFile())
                            <a
                                class="secondary-btn"
                                href="{{ route('admin.copy_trading.verifications.reviews.evidence', [
                                    'copyTradeBrokerAccount' => $account,
                                    'copyTradeBrokerAccountReview' => $review->id,
                                ]) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                View Evidence
                            </a>
                        @elseif($review->portal_reference)
                            Portal reference recorded
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $review->reason ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:30px;">
                        No admin verification decisions recorded yet.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
