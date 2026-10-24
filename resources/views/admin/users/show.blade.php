{{-- TradeX Admin skeleton --}}
@extends('admin.layouts.app')

@section('title', 'TradeX User Details')

@section('page-title', 'User Details')


@php
    /*
    |--------------------------------------------------------------------------
    | ADMIN-02E-4D — UI Permission State
    |--------------------------------------------------------------------------
    |
    | Server-side controllers remain the authoritative security boundary.
    | These flags only control what privileged UI is rendered.
    |
    */

    $currentAdmin =
        auth('admin')->user();

    $canActivateUser =
        $currentAdmin?->hasPermission(
            'users.activate'
        ) ?? false;

    $canSuspendUser =
        $currentAdmin?->hasPermission(
            'users.suspend'
        ) ?? false;

    $canBlockUser =
        $currentAdmin?->hasPermission(
            'users.block'
        ) ?? false;

    $canManageUserLifecycle =
        $currentAdmin?->isSuperAdmin()
        ?? false;

    $canRevokeDevices =
        $canViewDevices
        &&
        (
            $currentAdmin?->hasPermission(
                'users.devices.revoke'
            ) ?? false
        );

    $canResetDevices =
        $canViewDevices
        &&
        (
            $currentAdmin?->hasPermission(
                'users.devices.reset'
            ) ?? false
        );

    $canRevokeSessions =
        $canViewDevices
        &&
        (
            $currentAdmin?->hasPermission(
                'users.sessions.revoke'
            ) ?? false
        );

    $showActivateAction =
        $user->status !== 'ACTIVE'
        &&
        $canActivateUser;

    $showSuspendAction =
        $user->status === 'ACTIVE'
        &&
        $canSuspendUser;

    $showBlockAction =
        $user->status !== 'BLOCKED'
        &&
        $canBlockUser;

    $hasAccountAction =
        $showActivateAction
        ||
        $showSuspendAction
        ||
        $showBlockAction;
@endphp


@section('content')

{{-- ============================================================
     FLASH / VALIDATION FEEDBACK
============================================================ --}}

@if(session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif


@if(session('error'))

    <div class="alert alert-danger">
        {{ session('error') }}
    </div>

@endif


@if($errors->any())

    <div class="alert alert-danger">

        @foreach($errors->all() as $error)

            <div>
                {{ $error }}
            </div>

        @endforeach

    </div>

@endif


{{-- ============================================================
     PAGE HEADER
============================================================ --}}

<div class="page-header">

    <div>

        <div class="page-eyebrow">
            USER MANAGEMENT
        </div>

        <h1>
            User
            <span>
                Details
            </span>
        </h1>

        <p>
            Review account, verification, referral and security information.
        </p>

    </div>

    <a
        href="{{ route('admin.users.index') }}"
        class="secondary-btn user-back-btn"
    >
        ← Back to Users
    </a>

</div>


{{-- ============================================================
     PROFILE HERO
============================================================ --}}

<section class="panel user-profile-hero">

    <div class="user-profile-main">

        <div class="user-profile-avatar">

            {{ strtoupper(
                substr(
                    $user->name,
                    0,
                    1
                )
            ) }}

        </div>


        <div class="user-profile-name">

            <div class="user-profile-id">
                USER #{{ $user->id }}
            </div>

            <h2>
                {{ $user->name }}
            </h2>

            <div class="user-profile-email">
                {{ $user->email }}
            </div>

        </div>

    </div>


    <div class="user-profile-badges">

        <span
            class="account-badge
            {{ $user->account_type === 'PREMIUM'
                ? 'premium'
                : 'free'
            }}"
        >
            {{ $user->account_type }}
        </span>


        <span
            class="user-status
            status-{{ strtolower($user->status) }}"
        >

            <span class="user-status-dot"></span>

            {{ $user->status }}

        </span>

    </div>

</section>


{{-- ============================================================
     SUMMARY CARDS
============================================================ --}}

<div class="user-detail-stat-grid">

    <div class="user-detail-stat">

        <span>
            Account Type
        </span>

        <strong
            class="{{ $user->account_type === 'PREMIUM'
                ? 'text-gold'
                : ''
            }}"
        >
            {{ $user->account_type }}
        </strong>

    </div>


    <div class="user-detail-stat">

        <span>
            Account Status
        </span>

        <strong>
            {{ $user->status }}
        </strong>

    </div>


    <div class="user-detail-stat">

        <span>
            Direct Referrals
        </span>

        <strong>
            {{ number_format(
                $user->referrals_count
            ) }}
        </strong>

    </div>


    <div class="user-detail-stat">

        <span>
            Country
        </span>

        <strong>
            {{ $user->country_code ?? '—' }}
        </strong>

    </div>

</div>


{{-- ============================================================
     MAIN DETAILS
============================================================ --}}

<div class="user-detail-layout">


    {{-- ========================================================
         LEFT COLUMN
    ========================================================= --}}

    <div class="user-detail-column">


        {{-- ACCOUNT INFORMATION --}}

        <section class="panel user-detail-panel">

            <div class="panel-header">

                <div>

                    <div class="panel-eyebrow">
                        ACCOUNT
                    </div>

                    <h2>
                        Account Information
                    </h2>

                </div>

            </div>


            <div class="user-info-list">

                <div class="user-info-row">

                    <span>
                        Internal User ID
                    </span>

                    <strong>
                        #{{ $user->id }}
                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        UUID
                    </span>

                    <strong class="user-mono">
                        {{ $user->uuid ?? 'Not Assigned' }}
                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Full Name
                    </span>

                    <strong>
                        {{ $user->name }}
                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Language
                    </span>

                    <strong>
                        {{ strtoupper(
                            $user->language_code
                            ?? 'EN'
                        ) }}
                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Country
                    </span>

                    <strong>
                        {{ $user->country_code ?? '—' }}
                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Joined
                    </span>

                    <strong>

                        {{ $user->created_at
                            ? $user->created_at
                                ->format('d M Y H:i')
                            : '—'
                        }}

                    </strong>

                </div>

            </div>


        </section>


        {{-- CONTACT INFORMATION --}}

        <section class="panel user-detail-panel">

            <div class="panel-header">

                <div>

                    <div class="panel-eyebrow">
                        VERIFICATION
                    </div>

                    <h2>
                        Contact Information
                    </h2>

                </div>

            </div>


            <div class="verification-card">

                <div>

                    <span class="verification-label">
                        Email Address
                    </span>

                    <strong>
                        {{ $user->email }}
                    </strong>

                </div>


                @if($user->email_verified_at)

                    <span class="verification-badge verified">
                        ✓ VERIFIED
                    </span>

                @else

                    <span class="verification-badge pending">
                        NOT VERIFIED
                    </span>

                @endif

            </div>


            <div class="verification-card">

                <div>

                    <span class="verification-label">
                        WhatsApp Number
                    </span>

                    <strong>
                        {{ $user->whatsapp_number
                            ?? 'Not Added'
                        }}
                    </strong>

                </div>


                @if($user->whatsapp_verified_at)

                    <span class="verification-badge verified">
                        ✓ VERIFIED
                    </span>

                @else

                    <span class="verification-badge pending">
                        NOT VERIFIED
                    </span>

                @endif

            </div>

        </section>


        {{-- REFERRAL INFORMATION --}}

        <section class="panel user-detail-panel">

            <div class="panel-header">

                <div>

                    <div class="panel-eyebrow">
                        AFFILIATE
                    </div>

                    <h2>
                        Referral Information
                    </h2>

                </div>

            </div>


            <div class="user-info-list">

                <div class="user-info-row">

                    <span>
                        Referral Code
                    </span>

                    <strong>

                        <code class="referral-code">
                            {{ $user->referral_code
                                ?? 'Not Assigned'
                            }}
                        </code>

                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Direct Referrals
                    </span>

                    <strong>
                        {{ number_format(
                            $user->referrals_count
                        ) }}
                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Referred By
                    </span>

                    <strong>

                        @if($user->referrer)

                            {{ $user->referrer->name }}

                        @else

                            Direct / No Referrer

                        @endif

                    </strong>

                </div>


                @if($user->referrer)

                    <div class="user-info-row">

                        <span>
                            Referrer's Code
                        </span>

                        <strong>

                            <code class="referral-code">
                                {{ $user->referrer
                                    ->referral_code
                                    ?? '—'
                                }}
                            </code>

                        </strong>

                    </div>

                @endif

            </div>

        </section>

    </div>


    {{-- ========================================================
         RIGHT COLUMN
    ========================================================= --}}

    <div class="user-detail-column">


        {{-- PREMIUM ACCESS --}}

        <section class="panel user-detail-panel">

            <div class="panel-header">

                <div>

                    <div class="panel-eyebrow">
                        MEMBERSHIP
                    </div>

                    <h2>
                        Premium Access
                    </h2>

                </div>

            </div>


            <div
                class="premium-status-card
                {{ $user->isPremium()
                    ? 'enabled'
                    : ''
                }}"
            >

                <div>

                    <span>
                        Current Plan
                    </span>

                    <strong>
                        {{ $user->account_type }}
                    </strong>

                </div>


                <div class="premium-indicator">

                    {{ $user->isPremium()
                        ? 'PREMIUM'
                        : 'FREE'
                    }}

                </div>

            </div>


            <div class="user-info-list">

                <div class="user-info-row">

                    <span>
                        Premium Activated
                    </span>

                    <strong>

                        {{ $user->premium_activated_at
                            ? $user->premium_activated_at
                                ->format('d M Y H:i')
                            : '—'
                        }}

                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Premium Access
                    </span>

                    <strong
                        class="{{ $user->canAccessPremium()
                            ? 'text-green'
                            : ''
                        }}"
                    >

                        {{ $user->canAccessPremium()
                            ? 'ALLOWED'
                            : 'NOT ACTIVE'
                        }}

                    </strong>

                </div>

            </div>


            @if($canManageUserLifecycle)

                <form
                    method="POST"
                    action="{{ route('admin.users.account-type.update', $user) }}"
                    class="super-admin-user-form"
                    data-secure-mutation-form
                >

                    @csrf
                    @method('PATCH')

                    <div class="panel-eyebrow super-admin-form-eyebrow">
                        SUPER ADMIN CONTROL
                    </div>

                    <label for="accountType">
                        Membership plan
                    </label>

                    <select
                        id="accountType"
                        name="account_type"
                        required
                    >
                        <option
                            value="FREE"
                            @selected($user->account_type === 'FREE')
                        >
                            Free
                        </option>
                        <option
                            value="PREMIUM"
                            @selected($user->account_type === 'PREMIUM')
                        >
                            Premium
                        </option>
                    </select>

                    <label for="accountTypeReason">
                        Reason for membership change
                    </label>

                    <textarea
                        id="accountTypeReason"
                        name="reason"
                        rows="3"
                        minlength="10"
                        maxlength="500"
                        required
                        placeholder="Explain why this membership access is changing..."
                    >{{ old('reason') }}</textarea>

                    <p class="super-admin-form-hint">
                        Premium selection grants the education entitlement; Free selection revokes it. This is fully audited.
                    </p>

                    <button
                        type="submit"
                        class="user-action-btn activate"
                    >
                        Update Membership
                    </button>

                </form>

            @endif

        </section>


        {{-- ACCOUNT LIFECYCLE --}}

        <section class="panel user-detail-panel">

            <div class="panel-header">

                <div>

                    <div class="panel-eyebrow">
                        ACCESS CONTROL
                    </div>

                    <h2>
                        Account Lifecycle
                    </h2>

                </div>

            </div>


            <div class="user-info-list">

                <div class="user-info-row">

                    <span>
                        Status
                    </span>

                    <strong>
                        {{ $user->status }}
                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Suspended At
                    </span>

                    <strong>

                        {{ $user->suspended_at
                            ? $user->suspended_at
                                ->format('d M Y H:i')
                            : '—'
                        }}

                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Blocked At
                    </span>

                    <strong>

                        {{ $user->blocked_at
                            ? $user->blocked_at
                                ->format('d M Y H:i')
                            : '—'
                        }}

                    </strong>

                </div>

            </div>


            {{-- ACCOUNT ACTION BUTTONS --}}

            @if($hasAccountAction)

                <div class="user-action-grid">

                    @if($showActivateAction)

                        <button
                            type="button"
                            class="user-action-btn activate"
                            data-user-action="ACTIVATE"
                        >
                            ✓ Activate Account
                        </button>

                    @endif


                    @if($showSuspendAction)

                        <button
                            type="button"
                            class="user-action-btn suspend"
                            data-user-action="SUSPEND"
                        >
                            ◷ Suspend Account
                        </button>

                    @endif


                    @if($showBlockAction)

                        <button
                            type="button"
                            class="user-action-btn block"
                            data-user-action="BLOCK"
                        >
                            ⊘ Block Account
                        </button>

                    @endif

                </div>

            @endif


            {{-- STATUS HISTORY --}}

            <div class="status-history-box">

                <div class="user-info-row">

                    <span>
                        Last Status Change
                    </span>

                    <strong>

                        {{ $user->status_changed_at
                            ? $user->status_changed_at
                                ->format('d M Y H:i')
                            : '—'
                        }}

                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Changed By
                    </span>

                    <strong>

                        {{ $user->statusChangedByAdmin?->name
                            ?? '—'
                        }}

                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Reason
                    </span>

                    <strong>

                        {{ $user->status_reason
                            ?? '—'
                        }}

                    </strong>

                </div>

            </div>

        </section>


        @if($canManageUserLifecycle)

            <section class="panel user-detail-panel super-admin-danger-zone">

                <div class="panel-header">

                    <div>

                        <div class="panel-eyebrow">
                            SUPER ADMIN ONLY
                        </div>

                        <h2>
                            Delete User Account
                        </h2>

                    </div>

                </div>


                <p class="super-admin-danger-copy">
                    This immediately blocks the account, revokes its active devices and sessions, and soft-deletes the customer record. Financial and audit records are retained.
                </p>


                <form
                    method="POST"
                    action="{{ route('admin.users.destroy', $user) }}"
                    class="super-admin-user-form"
                    data-secure-mutation-form
                >

                    @csrf
                    @method('DELETE')

                    <label for="deleteUserReason">
                        Deletion reason
                    </label>

                    <textarea
                        id="deleteUserReason"
                        name="reason"
                        rows="3"
                        minlength="10"
                        maxlength="500"
                        required
                        placeholder="Explain why this customer account must be deleted..."
                    ></textarea>

                    <label for="deleteUserConfirmation">
                        Type <strong>DELETE USER</strong> to confirm
                    </label>

                    <input
                        id="deleteUserConfirmation"
                        type="text"
                        name="confirmation"
                        autocomplete="off"
                        required
                    >

                    <button
                        type="submit"
                        class="user-action-btn block"
                    >
                        Delete User Account
                    </button>

                </form>

            </section>

        @endif


        {{-- LOGIN SECURITY --}}

        <section class="panel user-detail-panel">

            <div class="panel-header">

                <div>

                    <div class="panel-eyebrow">
                        SECURITY
                    </div>

                    <h2>
                        Login Information
                    </h2>

                </div>

            </div>


            <div class="user-info-list">

                <div class="user-info-row">

                    <span>
                        Last Login
                    </span>

                    <strong>

                        {{ $user->last_login_at
                            ? $user->last_login_at
                                ->format('d M Y H:i')
                            : 'No Login Recorded'
                        }}

                    </strong>

                </div>


                <div class="user-info-row">

                    <span>
                        Last Login IP
                    </span>

                    <strong class="user-mono">

                        {{ $user->last_login_ip
                            ?? '—'
                        }}

                    </strong>

                </div>

            </div>

        </section>


        {{-- FINANCIAL / WITHDRAWAL DETAILS --}}

        <section class="panel user-detail-panel">

            <div class="panel-header">

                <div>

                    <div class="panel-eyebrow">
                        FINANCIAL PROFILE
                    </div>

                    <h2>
                        Withdrawal & Bank Details
                    </h2>

                </div>

            </div>


            <div class="user-info-list">

                <div class="user-info-row">
                    <span>PayPal Email</span>
                    <strong>{{ $user->profile?->paypal_email ?? 'Not configured' }}</strong>
                </div>

                <div class="user-info-row">
                    <span>Binance Email</span>
                    <strong>{{ $user->profile?->binance_email ?? 'Not configured' }}</strong>
                </div>

                <div class="user-info-row">
                    <span>USDT TRC20 Address</span>
                    <strong class="user-mono" style="overflow-wrap:anywhere;">
                        {{ $user->profile?->usdt_trc20_address ?? 'Not configured' }}
                    </strong>
                </div>

                <div class="user-info-row">
                    <span>Bank Name</span>
                    <strong>{{ $user->profile?->bank_name ?? 'Not configured' }}</strong>
                </div>

                <div class="user-info-row">
                    <span>Bank Branch</span>
                    <strong>{{ $user->profile?->bank_branch ?? 'Not configured' }}</strong>
                </div>

                <div class="user-info-row">
                    <span>Account Holder</span>
                    <strong>{{ $user->profile?->bank_account_holder ?? 'Not configured' }}</strong>
                </div>

                <div class="user-info-row">
                    <span>Bank Account Number</span>
                    <strong class="user-mono">
                        {{ $user->profile?->bank_account_number ?? 'Not configured' }}
                    </strong>
                </div>

                <div class="user-info-row">
                    <span>Telegram</span>
                    <strong>{{ $user->profile?->telegram_username ?? 'Not configured' }}</strong>
                </div>

                <div class="user-info-row">
                    <span>Financial Details Updated</span>
                    <strong>
                        {{ $user->profile?->financial_updated_at
                            ? $user->profile->financial_updated_at->format('d M Y H:i')
                            : 'Never' }}
                    </strong>
                </div>

            </div>

        </section>


        {{-- ====================================================
             DEVICE SECURITY
        ===================================================== --}}

        @if($canViewDevices)

            @php
                $activeMobileDevices =
                    $devices->filter(
                        fn ($device) =>
                            $device->device_slot === 'MOBILE'
                            &&
                            $device->is_active
                            &&
                            $device->revoked_at === null
                    )->values();

                $activeMobileDevice =
                    $activeMobileDevices->first();

                $activeDesktopDevice =
                    $devices->first(
                        fn ($device) =>
                            $device->device_slot === 'DESKTOP'
                            &&
                            $device->is_active
                            &&
                            $device->revoked_at === null
                    );

                $hasActiveDevice =
                    $activeMobileDevices->isNotEmpty()
                    ||
                    $activeDesktopDevice;
            @endphp


            <section class="panel user-detail-panel">

                <div class="panel-header">

                    <div>

                        <div class="panel-eyebrow">
                            TRUSTED DEVICES
                        </div>

                        <h2>
                            Device Security
                        </h2>

                    </div>

                    <span class="device-limit-badge">
                        2 MOBILE + 1 DESKTOP
                    </span>

                </div>


                <div class="live-device-grid">


                    {{-- MOBILE SLOT --}}

                    <div class="live-device-card">

                        <div class="device-card-top">

                            <div class="device-icon">
                                ▯
                            </div>

                            <div>

                                <span class="device-slot-label">
                                    MOBILE SLOT
                                </span>

                                <strong>
                                    {{ $activeMobileDevices->count() }} / 2 Active
                                </strong>

                            </div>

                            <span
                                class="device-slot-state
                                {{ $activeMobileDevice
                                    ? 'occupied'
                                    : 'available'
                                }}"
                            >
                                {{ $activeMobileDevice
                                    ? 'ACTIVE'
                                    : 'FREE'
                                }}
                            </span>

                        </div>


                        @if($activeMobileDevice)

                            <div class="device-card-info">

                                <div>

                                    <span>
                                        Device
                                    </span>

                                    <strong>
                                        {{ $activeMobileDevice->device_name
                                            ?? $activeMobileDevice->device_model
                                            ?? 'Mobile Device'
                                        }}
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Platform
                                    </span>

                                    <strong>
                                        {{ $activeMobileDevice->platform }}
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Trusted
                                    </span>

                                    <strong
                                        class="{{ $activeMobileDevice->is_trusted
                                            ? 'text-green'
                                            : 'text-orange'
                                        }}"
                                    >
                                        {{ $activeMobileDevice->is_trusted
                                            ? 'YES'
                                            : 'PENDING'
                                        }}
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Sessions
                                    </span>

                                    <strong>
                                        {{ number_format(
                                            $activeMobileDevice
                                                ->active_refresh_tokens_count
                                        ) }}
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Last Seen
                                    </span>

                                    <strong>
                                        {{ $activeMobileDevice->last_seen_at
                                            ? $activeMobileDevice->last_seen_at
                                                ->format('d M Y H:i')
                                            : '—'
                                        }}
                                    </strong>

                                </div>

                            </div>


                            @if($canRevokeDevices)

                                <button
                                    type="button"
                                    class="device-danger-button"
                                    data-device-revoke
                                    data-action-url="{{
                                        route(
                                            'admin.users.devices.revoke',
                                            [
                                                $user,
                                                $activeMobileDevice
                                            ]
                                        )
                                    }}"
                                    data-device-name="{{
                                        $activeMobileDevice->device_name
                                        ?? 'Mobile Device'
                                    }}"
                                >
                                    Revoke Mobile Device
                                </button>

                            @endif

                        @else

                            <div class="empty-device-slot">
                                No active mobile device registered.
                            </div>

                        @endif

                    </div>


                    {{-- DESKTOP SLOT --}}

                    <div class="live-device-card">

                        <div class="device-card-top">

                            <div class="device-icon">
                                ▰
                            </div>

                            <div>

                                <span class="device-slot-label">
                                    DESKTOP SLOT
                                </span>

                                <strong>
                                    {{ $activeDesktopDevice
                                        ? 'Occupied'
                                        : 'Available'
                                    }}
                                </strong>

                            </div>

                            <span
                                class="device-slot-state
                                {{ $activeDesktopDevice
                                    ? 'occupied'
                                    : 'available'
                                }}"
                            >
                                {{ $activeDesktopDevice
                                    ? 'ACTIVE'
                                    : 'FREE'
                                }}
                            </span>

                        </div>


                        @if($activeDesktopDevice)

                            <div class="device-card-info">

                                <div>

                                    <span>
                                        Device
                                    </span>

                                    <strong>
                                        {{ $activeDesktopDevice->device_name
                                            ?? $activeDesktopDevice->device_model
                                            ?? 'Desktop Device'
                                        }}
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Platform
                                    </span>

                                    <strong>
                                        {{ $activeDesktopDevice->platform }}
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Trusted
                                    </span>

                                    <strong
                                        class="{{ $activeDesktopDevice->is_trusted
                                            ? 'text-green'
                                            : 'text-orange'
                                        }}"
                                    >
                                        {{ $activeDesktopDevice->is_trusted
                                            ? 'YES'
                                            : 'PENDING'
                                        }}
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Sessions
                                    </span>

                                    <strong>
                                        {{ number_format(
                                            $activeDesktopDevice
                                                ->active_refresh_tokens_count
                                        ) }}
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Last Seen
                                    </span>

                                    <strong>
                                        {{ $activeDesktopDevice->last_seen_at
                                            ? $activeDesktopDevice->last_seen_at
                                                ->format('d M Y H:i')
                                            : '—'
                                        }}
                                    </strong>

                                </div>

                            </div>


                            @if($canRevokeDevices)

                                <button
                                    type="button"
                                    class="device-danger-button"
                                    data-device-revoke
                                    data-action-url="{{
                                        route(
                                            'admin.users.devices.revoke',
                                            [
                                                $user,
                                                $activeDesktopDevice
                                            ]
                                        )
                                    }}"
                                    data-device-name="{{
                                        $activeDesktopDevice->device_name
                                        ?? 'Desktop Device'
                                    }}"
                                >
                                    Revoke Desktop Device
                                </button>

                            @endif

                        @else

                            <div class="empty-device-slot">
                                No active desktop device registered.
                            </div>

                        @endif

                    </div>

                </div>


                {{-- SECURITY ACTIONS --}}

                <div class="device-security-actions">

                    <div>

                        <span>
                            Active Refresh Sessions
                        </span>

                        <strong>
                            {{ number_format(
                                $activeSessionCount
                            ) }}
                        </strong>

                    </div>


                    <div class="device-security-buttons">

                        @if(
                            $activeSessionCount > 0
                            &&
                            $canRevokeSessions
                        )

                            <button
                                type="button"
                                class="security-outline-button"
                                id="openSessionRevokeModal"
                            >
                                Sign Out All Sessions
                            </button>

                        @endif


                        @if(
                            $hasActiveDevice
                            &&
                            $canResetDevices
                        )

                            <button
                                type="button"
                                class="security-reset-button"
                                id="openDeviceResetModal"
                            >
                                Reset Device Slots
                            </button>

                        @endif

                    </div>

                </div>

            </section>

        @endif

    </div>

</div>


{{-- ============================================================
     CUSTOMER TRADE JOURNAL
============================================================ --}}

@include('admin.users.partials.trade-journal')


{{-- ============================================================
     DIRECT REFERRAL CUSTOMERS
============================================================ --}}

<section class="panel users-panel" style="margin-bottom:18px;">

    <div class="panel-header">

        <div>
            <div class="panel-eyebrow">AFFILIATE NETWORK</div>
            <h2>Direct Referral Customers</h2>
        </div>

        <div class="panel-count">
            {{ number_format($referralCustomers->count()) }} customers
        </div>

    </div>

    <div class="users-table-wrap">
        <table class="users-table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Mobile Number</th>
                    <th>Referral Code</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($referralCustomers as $referralCustomer)
                <tr>
                    <td>
                        <div class="user-contact">
                            <strong>{{ $referralCustomer->name }}</strong>
                            <span>{{ $referralCustomer->email }}</span>
                        </div>
                    </td>
                    <td>{{ $referralCustomer->whatsapp_number ?? '—' }}</td>
                    <td>
                        <code class="referral-code">
                            {{ $referralCustomer->referral_code ?? '—' }}
                        </code>
                    </td>
                    <td>{{ $referralCustomer->account_type }}</td>
                    <td>{{ $referralCustomer->status }}</td>
                    <td>{{ $referralCustomer->created_at?->format('d M Y H:i') ?? '—' }}</td>
                    <td>
                        <a
                            class="table-action"
                            href="{{ route('admin.users.show', $referralCustomer) }}"
                            title="View referred customer"
                        >→</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="users-empty">
                        <strong>No direct referrals</strong>
                        <span>This customer has not referred another registered customer yet.</span>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

</section>


{{-- ============================================================
     ADMIN-02E-3/4D — DEVICE HISTORY
============================================================ --}}

@if($canViewDevices)

    <section class="panel device-history-panel">

        <div class="panel-header">

            <div>

                <div class="panel-eyebrow">
                    SECURITY HISTORY
                </div>

                <h2>
                    Registered Devices
                </h2>

            </div>


            <div class="panel-count">

                {{ number_format(
                    $devices->count()
                ) }}

                records

            </div>

        </div>


        <div class="device-history-wrap">

            <table class="device-history-table">

                <thead>

                    <tr>
                        <th>Device</th>
                        <th>Slot</th>
                        <th>Platform</th>
                        <th>Trust</th>
                        <th>Sessions</th>
                        <th>Registered</th>
                        <th>Last Seen</th>
                        <th>Status</th>
                    </tr>

                </thead>


                <tbody>

                    @forelse($devices as $device)

                        <tr>

                            <td>

                                <div class="history-device">

                                    <strong>
                                        {{ $device->device_name
                                            ?? $device->device_model
                                            ?? 'Unnamed Device'
                                        }}
                                    </strong>

                                    <span>
                                        {{ $device->device_model
                                            ?? '—'
                                        }}
                                    </span>

                                </div>

                            </td>


                            <td>
                                {{ $device->device_slot }}
                            </td>


                            <td>
                                {{ $device->platform }}
                            </td>


                            <td>

                                @if($device->is_trusted)

                                    <span class="history-status trusted">
                                        TRUSTED
                                    </span>

                                @else

                                    <span class="history-status untrusted">
                                        NOT TRUSTED
                                    </span>

                                @endif

                            </td>


                            <td>
                                {{ number_format(
                                    $device
                                        ->active_refresh_tokens_count
                                ) }}
                            </td>


                            <td>

                                {{ $device->registered_at
                                    ? $device->registered_at
                                        ->format('d M Y H:i')
                                    : '—'
                                }}

                            </td>


                            <td>

                                {{ $device->last_seen_at
                                    ? $device->last_seen_at
                                        ->format('d M Y H:i')
                                    : '—'
                                }}

                            </td>


                            <td>

                                @if(
                                    $device->is_active
                                    &&
                                    $device->revoked_at === null
                                )

                                    <span class="history-status active">
                                        ACTIVE
                                    </span>

                                @else

                                    <span class="history-status revoked">
                                        REVOKED
                                    </span>

                                @endif

                            </td>

                        </tr>


                        @if($device->revoked_at)

                            <tr class="device-revoke-detail">

                                <td colspan="8">

                                    Revoked:

                                    {{ $device->revoked_at
                                        ->format('d M Y H:i')
                                    }}

                                    · Reason:

                                    {{ $device->revoke_reason
                                        ?? 'No reason recorded'
                                    }}


                                    @if($device->revokedByAdmin)

                                        · By:

                                        {{ $device
                                            ->revokedByAdmin
                                            ->name
                                        }}

                                    @endif

                                </td>

                            </tr>

                        @endif


                    @empty

                        <tr>

                            <td
                                colspan="8"
                                class="users-empty"
                            >
                                No device registrations found.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

@endif


{{-- ============================================================
     ACCOUNT STATUS ACTION MODAL
============================================================ --}}

@if($hasAccountAction)

    <div
        class="status-modal-overlay"
        id="statusActionModal"
        hidden
    >

        <div class="status-modal">

            <div class="status-modal-header">

                <div>

                    <div class="panel-eyebrow">
                        ACCOUNT SECURITY
                    </div>

                    <h3 id="statusModalTitle">
                        Confirm Account Action
                    </h3>

                </div>


                <button
                    type="button"
                    class="status-modal-close"
                    id="statusModalClose"
                >
                    ×
                </button>

            </div>


            <p
                class="status-modal-message"
                id="statusModalMessage"
            ></p>


            <form
                method="POST"
                action="{{ route(
                    'admin.users.status.update',
                    $user
                ) }}"
                id="statusActionForm"
                data-secure-mutation-form
            >

                @csrf
                @method('PATCH')


                <input
                    type="hidden"
                    name="action"
                    id="statusActionInput"
                >


                <label
                    class="status-reason-label"
                    for="statusReason"
                >
                    Reason for this action
                </label>


                <textarea
                    id="statusReason"
                    name="reason"
                    rows="5"
                    maxlength="500"
                    required
                    placeholder="Enter a clear administrative reason..."
                >{{ old('reason') }}</textarea>


                <div class="status-modal-hint">

                    Minimum 10 characters.
                    This reason will be stored in the audit trail.

                </div>


                <div class="status-modal-actions">

                    <button
                        type="button"
                        class="secondary-btn"
                        id="statusModalCancel"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="status-confirm-btn"
                        id="statusConfirmButton"
                    >
                        Confirm
                    </button>

                </div>

            </form>

        </div>

    </div>

@endif


{{-- ============================================================
     DEVICE SECURITY MODALS
============================================================ --}}


{{-- REVOKE SINGLE DEVICE --}}

@if($canRevokeDevices)

    <div
        class="status-modal-overlay"
        id="deviceRevokeModal"
        hidden
    >

        <div class="status-modal">

            <div class="status-modal-header">

                <div>

                    <div class="panel-eyebrow">
                        DEVICE SECURITY
                    </div>

                    <h3>
                        Revoke Device
                    </h3>

                </div>


                <button
                    type="button"
                    class="status-modal-close"
                    id="deviceRevokeClose"
                >
                    ×
                </button>

            </div>


            <p class="status-modal-message">

                Revoke

                <strong id="deviceRevokeName">
                    this device
                </strong>

                and invalidate all active refresh sessions
                associated with it.

            </p>


            <form
                method="POST"
                id="deviceRevokeForm"
                data-secure-mutation-form
            >

                @csrf
                @method('PATCH')


                <label
                    class="status-reason-label"
                    for="deviceRevokeReason"
                >
                    Revocation Reason
                </label>


                <textarea
                    id="deviceRevokeReason"
                    name="reason"
                    rows="5"
                    minlength="10"
                    maxlength="500"
                    required
                    placeholder="Example: Customer lost this device and requested access removal."
                ></textarea>


                <div class="status-modal-hint">
                    Minimum 10 characters. This action is audited.
                </div>


                <div class="status-modal-actions">

                    <button
                        type="button"
                        class="secondary-btn"
                        id="deviceRevokeCancel"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="danger-confirm-button"
                    >
                        Revoke Device
                    </button>

                </div>

            </form>

        </div>

    </div>

@endif


{{-- RESET ALL DEVICE SLOTS --}}

@if($canResetDevices)

    <div
        class="status-modal-overlay"
        id="deviceResetModal"
        hidden
    >

        <div class="status-modal">

            <div class="status-modal-header">

                <div>

                    <div class="panel-eyebrow">
                        SECURITY RESET
                    </div>

                    <h3>
                        Reset All Device Slots
                    </h3>

                </div>


                <button
                    type="button"
                    class="status-modal-close"
                    id="deviceResetClose"
                >
                    ×
                </button>

            </div>


            <p class="status-modal-message">

                All active mobile and desktop devices will
                be revoked. Their refresh sessions will also
                become invalid.

            </p>


            <form
                method="POST"
                action="{{ route(
                    'admin.users.devices.reset',
                    $user
                ) }}"
                id="deviceResetForm"
                data-secure-mutation-form
            >

                @csrf


                <label
                    class="status-reason-label"
                    for="deviceResetReason"
                >
                    Reset Reason
                </label>


                <textarea
                    id="deviceResetReason"
                    name="reason"
                    rows="5"
                    maxlength="500"
                    required
                    placeholder="Enter why all trusted device slots are being reset."
                ></textarea>


                <div class="status-modal-actions">

                    <button
                        type="button"
                        class="secondary-btn"
                        id="deviceResetCancel"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="danger-confirm-button"
                    >
                        Reset All Devices
                    </button>

                </div>

            </form>

        </div>

    </div>

@endif


{{-- SIGN OUT ALL REFRESH SESSIONS --}}

@if($canRevokeSessions)

    <div
        class="status-modal-overlay"
        id="sessionRevokeModal"
        hidden
    >

        <div class="status-modal">

            <div class="status-modal-header">

                <div>

                    <div class="panel-eyebrow">
                        SESSION SECURITY
                    </div>

                    <h3>
                        Sign Out All Sessions
                    </h3>

                </div>


                <button
                    type="button"
                    class="status-modal-close"
                    id="sessionRevokeClose"
                >
                    ×
                </button>

            </div>


            <p class="status-modal-message">

                Active refresh sessions will be revoked.
                Trusted devices themselves will remain registered.

            </p>


            <form
                method="POST"
                action="{{ route(
                    'admin.users.sessions.revoke',
                    $user
                ) }}"
                id="sessionRevokeForm"
                data-secure-mutation-form
            >

                @csrf


                <label
                    class="status-reason-label"
                    for="sessionRevokeReason"
                >
                    Reason
                </label>


                <textarea
                    id="sessionRevokeReason"
                    name="reason"
                    rows="5"
                    maxlength="500"
                    required
                    placeholder="Enter why all user sessions must be terminated."
                ></textarea>


                <div class="status-modal-actions">

                    <button
                        type="button"
                        class="secondary-btn"
                        id="sessionRevokeCancel"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="danger-confirm-button"
                    >
                        Sign Out All Sessions
                    </button>

                </div>

            </form>

        </div>

    </div>

@endif

@endsection


{{-- ============================================================
     ADMIN-02E-4D — CONSOLIDATED PAGE SCRIPTS
============================================================ --}}

@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Generic Modal Helpers
        |--------------------------------------------------------------------------
        */

        function openSecurityModal(
            modal,
            focusElement = null
        ) {

            if (!modal) {
                return;
            }


            modal.hidden = false;

            document.body.classList.add(
                'modal-open'
            );


            if (focusElement) {

                setTimeout(
                    function () {

                        focusElement.focus();

                    },
                    100
                );
            }
        }


        function closeSecurityModal(
            modal
        ) {

            if (!modal) {
                return;
            }


            modal.hidden = true;


            const anyModalOpen =
                Array
                    .from(
                        document.querySelectorAll(
                            '.status-modal-overlay'
                        )
                    )
                    .some(
                        function (candidate) {

                            return !candidate.hidden;

                        }
                    );


            if (!anyModalOpen) {

                document.body.classList.remove(
                    'modal-open'
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Account Status Modal
        |--------------------------------------------------------------------------
        */

        const statusModal =
            document.getElementById(
                'statusActionModal'
            );

        const statusActionInput =
            document.getElementById(
                'statusActionInput'
            );

        const statusTitle =
            document.getElementById(
                'statusModalTitle'
            );

        const statusMessage =
            document.getElementById(
                'statusModalMessage'
            );

        const statusConfirmButton =
            document.getElementById(
                'statusConfirmButton'
            );

        const statusReason =
            document.getElementById(
                'statusReason'
            );


        const statusActions = {

            ACTIVATE: {

                title:
                    'Activate User Account',

                message:
                    'This user will regain normal account access.',

                button:
                    'Activate User'
            },

            SUSPEND: {

                title:
                    'Suspend User Account',

                message:
                    'This user will be temporarily restricted from account access.',

                button:
                    'Suspend User'
            },

            BLOCK: {

                title:
                    'Block User Account',

                message:
                    'This is a strong account restriction. The user will remain blocked until an authorized admin reactivates the account.',

                button:
                    'Block User'
            }

        };


        document
            .querySelectorAll(
                '[data-user-action]'
            )
            .forEach(
                function (button) {

                    button.addEventListener(
                        'click',
                        function () {

                            const action =
                                button.dataset.userAction;

                            const config =
                                statusActions[action];


                            if (
                                !config
                                ||
                                !statusModal
                                ||
                                !statusActionInput
                                ||
                                !statusTitle
                                ||
                                !statusMessage
                                ||
                                !statusConfirmButton
                            ) {
                                return;
                            }


                            statusActionInput.value =
                                action;

                            statusTitle.textContent =
                                config.title;

                            statusMessage.textContent =
                                config.message;

                            statusConfirmButton.textContent =
                                config.button;


                            openSecurityModal(
                                statusModal,
                                statusReason
                            );
                        }
                    );

                }
            );


        document
            .getElementById(
                'statusModalClose'
            )
            ?.addEventListener(
                'click',
                function () {

                    closeSecurityModal(
                        statusModal
                    );
                }
            );


        document
            .getElementById(
                'statusModalCancel'
            )
            ?.addEventListener(
                'click',
                function () {

                    closeSecurityModal(
                        statusModal
                    );
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Reset Device Slots
        |--------------------------------------------------------------------------
        */

        const deviceResetModal =
            document.getElementById(
                'deviceResetModal'
            );

        const deviceResetReason =
            document.getElementById(
                'deviceResetReason'
            );


        document
            .getElementById(
                'openDeviceResetModal'
            )
            ?.addEventListener(
                'click',
                function () {

                    openSecurityModal(
                        deviceResetModal,
                        deviceResetReason
                    );
                }
            );


        document
            .getElementById(
                'deviceResetClose'
            )
            ?.addEventListener(
                'click',
                function () {

                    closeSecurityModal(
                        deviceResetModal
                    );
                }
            );


        document
            .getElementById(
                'deviceResetCancel'
            )
            ?.addEventListener(
                'click',
                function () {

                    closeSecurityModal(
                        deviceResetModal
                    );
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Sign Out All Sessions
        |--------------------------------------------------------------------------
        */

        const sessionRevokeModal =
            document.getElementById(
                'sessionRevokeModal'
            );

        const sessionRevokeReason =
            document.getElementById(
                'sessionRevokeReason'
            );


        document
            .getElementById(
                'openSessionRevokeModal'
            )
            ?.addEventListener(
                'click',
                function () {

                    openSecurityModal(
                        sessionRevokeModal,
                        sessionRevokeReason
                    );
                }
            );


        document
            .getElementById(
                'sessionRevokeClose'
            )
            ?.addEventListener(
                'click',
                function () {

                    closeSecurityModal(
                        sessionRevokeModal
                    );
                }
            );


        document
            .getElementById(
                'sessionRevokeCancel'
            )
            ?.addEventListener(
                'click',
                function () {

                    closeSecurityModal(
                        sessionRevokeModal
                    );
                }
            );


        /*
        |--------------------------------------------------------------------------
        | Overlay Click Close
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '.status-modal-overlay'
            )
            .forEach(
                function (modal) {

                    modal.addEventListener(
                        'click',
                        function (event) {

                            if (
                                event.target === modal
                            ) {

                                closeSecurityModal(
                                    modal
                                );
                            }
                        }
                    );

                }
            );


        /*
        |--------------------------------------------------------------------------
        | Escape Key Close
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key !== 'Escape'
                ) {
                    return;
                }


                document
                    .querySelectorAll(
                        '.status-modal-overlay'
                    )
                    .forEach(
                        function (modal) {

                            if (!modal.hidden) {

                                closeSecurityModal(
                                    modal
                                );
                            }
                        }
                    );
            }
        );


        /*
        |--------------------------------------------------------------------------
        | ADMIN-02E-4D — Mutation Double-Submit Protection
        |--------------------------------------------------------------------------
        |
        | Prevent an admin from accidentally submitting a destructive form
        | more than once due to repeated clicks.
        |
        | Server-side authorization, validation, transactions and audit remain
        | authoritative.
        |
        */

        document
            .querySelectorAll(
                '[data-secure-mutation-form]'
            )
            .forEach(
                function (form) {

                    form.addEventListener(
                        'submit',
                        function (event) {

                            if (
                                form.dataset.submitting
                                === 'true'
                            ) {

                                event.preventDefault();

                                return;
                            }


                            form.dataset.submitting =
                                'true';


                            const submitButton =
                                form.querySelector(
                                    'button[type="submit"]'
                                );


                            if (!submitButton) {
                                return;
                            }


                            submitButton.disabled =
                                true;

                            submitButton.setAttribute(
                                'aria-disabled',
                                'true'
                            );


                            if (
                                !submitButton.dataset
                                    .originalText
                            ) {

                                submitButton.dataset
                                    .originalText =
                                    submitButton.textContent
                                        .trim();
                            }


                            submitButton.textContent =
                                'Processing...';
                        }
                    );

                }
            );

    }
);

</script>

@endpush
