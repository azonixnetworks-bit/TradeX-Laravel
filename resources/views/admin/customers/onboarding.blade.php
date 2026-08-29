@extends('admin.layouts.app')

@section('title', 'Customer Onboarding')
@section('page-title', 'Customer Onboarding')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">CUSTOMER ONBOARDING</div>
        <h1>Onboarding <span>Queue</span></h1>
        <p>
            Track secure admin invitations, delivery progress, registration
            matching, expiry, conflicts and revocations.
        </p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a
            class="secondary-btn"
            href="{{ route('admin.customers.dashboard') }}"
        >
            Customer Dashboard
        </a>

        @if(auth('admin')->user()?->hasPermission('users.invite'))
            <a
                class="primary-btn"
                href="{{ route('admin.customers.create') }}"
            >
                Add Customer
            </a>
        @endif
    </div>
</div>

@if(session('success'))
    <section class="panel" style="padding:14px;margin-bottom:18px;">
        {{ session('success') }}
    </section>
@endif

@if(session('warning'))
    <section class="panel" style="padding:14px;margin-bottom:18px;">
        <strong>Attention:</strong> {{ session('warning') }}
    </section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>Pending</span>
        <strong>{{ number_format($summary['pending']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Matched / Registered</span>
        <strong class="text-green">{{ number_format($summary['matched']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Expired</span>
        <strong>{{ number_format($summary['expired']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Identity Conflict</span>
        <strong class="text-red">{{ number_format($summary['conflict']) }}</strong>
    </div>
    <div class="user-stat-card">
        <span>Revoked</span>
        <strong>{{ number_format($summary['revoked']) }}</strong>
    </div>
</div>

<section class="panel" style="padding:18px;margin-bottom:18px;">
    <form method="GET" action="{{ route('admin.customers.onboarding') }}">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
            <div class="form-group" style="margin:0;min-width:220px;">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All</option>
                    @foreach(['PENDING', 'MATCHED', 'EXPIRED', 'CONFLICT', 'REVOKED'] as $option)
                        <option
                            value="{{ $option }}"
                            @selected($status === $option)
                        >
                            {{ $option }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button class="primary-btn" type="submit">Apply</button>
            <a class="secondary-btn" href="{{ route('admin.customers.onboarding') }}">
                Reset
            </a>
        </div>
    </form>
</section>

<section class="panel users-panel">
    <div class="panel-header">
        <div>
            <div class="panel-eyebrow">ONBOARDING RECORDS</div>
            <h2>Customer Invitations</h2>
        </div>

        <div class="panel-count">
            @if($invitations->total() > 0)
                Showing {{ number_format($invitations->firstItem()) }}–{{ number_format($invitations->lastItem()) }}
                of {{ number_format($invitations->total()) }}
            @else
                No invitations
            @endif
        </div>
    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>WhatsApp</th>
                    <th>Country</th>
                    <th>Referral</th>
                    <th>Status</th>
                    <th>Stage</th>
                    <th>Matched Account</th>
                    <th>Expires</th>
                    <th>Invite Sends</th>
                    <th>Created By</th>
                    <th>Note</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($invitations as $invitation)
                <tr>
                    <td>
                        <div class="user-contact">
                            <strong>{{ $invitation->name }}</strong>
                            <span>{{ $invitation->email }}</span>
                        </div>
                    </td>
                    <td>{{ $invitation->whatsapp_number }}</td>
                    <td>{{ $invitation->country_code }}</td>
                    <td>
                        {{ $invitation->referrer?->referral_code
                            ?? $invitation->referral_code_input
                            ?? '—' }}
                    </td>
                    <td>
                        <strong>{{ $invitation->status }}</strong>
                    </td>
                    <td>
                        <strong>{{ $invitation->onboarding_stage ?? 'INVITE_CREATED' }}</strong>
                    </td>
                    <td>
                        @if($invitation->matchedUser)
                            <a
                                href="{{ route('admin.users.show', $invitation->matchedUser) }}"
                                class="secondary-btn"
                            >
                                {{ $invitation->matchedUser->name }}
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $invitation->expires_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td>{{ number_format((int) $invitation->invite_send_count) }}</td>
                    <td>{{ $invitation->createdByAdmin?->name ?? 'System' }}</td>
                    <td>{{ filled($invitation->internal_note) ? 'Recorded' : '—' }}</td>
                    <td>
                        @if(
                            $invitation->status === 'PENDING'
                            && auth('admin')->user()?->hasPermission('users.invite')
                        )
                            <div style="display:flex;gap:8px;flex-wrap:wrap;min-width:220px;">
                                <form
                                    method="POST"
                                    action="{{ route('admin.customers.onboarding.resend', $invitation) }}"
                                >
                                    @csrf
                                    <button class="secondary-btn" type="submit">
                                        Resend Invite
                                    </button>
                                </form>

                                <form
                                    method="POST"
                                    action="{{ route('admin.customers.onboarding.revoke', $invitation) }}"
                                    onsubmit="return confirm('Revoke this onboarding invitation?');"
                                >
                                    @csrf
                                    <button class="secondary-btn" type="submit">
                                        Revoke
                                    </button>
                                </form>
                            </div>
                        @elseif($invitation->status === 'REVOKED')
                            <span>
                                Revoked
                                @if($invitation->revokedByAdmin)
                                    by {{ $invitation->revokedByAdmin->name }}
                                @endif
                            </span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="users-empty">
                        <div class="empty-icon">◎</div>
                        <strong>No onboarding invitations found</strong>
                        <span>Create one from Add Customer.</span>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @include(
        'admin.components.pagination',
        ['paginator' => $invitations]
    )
</section>
@endsection
