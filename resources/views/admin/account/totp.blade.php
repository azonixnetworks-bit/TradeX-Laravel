@extends('admin.layouts.app')

@inject('totpQrCodes', 'App\Services\Auth\Registration\TotpQrCodeService')

@section('title', 'Admin Two-Factor Authentication')
@section('page-title', 'Admin Two-Factor Authentication')

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">
            ADMIN-19 · ACCOUNT HARDENING
        </div>

        <h1>
            Two-Factor <span>Authentication</span>
        </h1>

        <p>
            Protect this admin account with a time-based authenticator code.
        </p>
    </div>
</div>

@if(session('success'))
    <section
        class="panel"
        style="padding:14px;margin-bottom:18px;"
    >
        {{ session('success') }}
    </section>
@endif

@if(session('error'))
    <section
        class="panel"
        style="padding:14px;margin-bottom:18px;"
    >
        {{ session('error') }}
    </section>
@endif

<div class="user-stat-grid">
    <div class="user-stat-card">
        <span>TOTP Status</span>
        <strong>
            {{ $admin->hasConfirmedTotp() ? 'ENABLED' : 'NOT ENABLED' }}
        </strong>
    </div>

    <div class="user-stat-card">
        <span>Production Enforcement</span>
        <strong>
            {{ $totpRequired ? 'REQUIRED' : 'ROLLOUT MODE' }}
        </strong>
    </div>

    <div class="user-stat-card">
        <span>Confirmed At</span>
        <strong>
            {{ $admin->totp_confirmed_at?->format('Y-m-d H:i') ?? '—' }}
        </strong>
    </div>
</div>

@if($admin->hasConfirmedTotp())
<section
    class="panel"
    style="padding:22px;"
>
    <h2>TOTP Enabled</h2>

    <p style="margin-top:12px;">
        This account has a confirmed authenticator factor. ADMIN-19 does not
        expose the enrolled secret after confirmation and provides no
        self-service disable/reset action.
    </p>

    @if(!$totpRequired)
        <p style="margin-top:12px;">
            Production enforcement is still disabled. Enroll every active admin
            first, then enable <code>TRADEX_ADMIN_TOTP_REQUIRED=true</code>.
        </p>
    @endif
</section>
@else
    @if(!$provisioning)
        <section
            class="panel"
            style="padding:22px;"
        >
            <h2>Step 1 — Start secure setup</h2>

            <p style="margin-top:12px;">
                Re-enter your current admin password. A new random TOTP secret
                will be generated and encrypted at rest.
            </p>

            <form
                method="POST"
                action="{{ route('admin.account.totp.setup') }}"
                style="margin-top:18px;"
            >
                @csrf

                <div class="form-group">
                    <label>Current Admin Password</label>

                    <input
                        type="password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >

                    @error('current_password')
                        <small>{{ $message }}</small>
                    @enderror
                </div>

                <div style="margin-top:18px;">
                    <button
                        class="primary-btn"
                        type="submit"
                    >
                        Generate TOTP Secret
                    </button>
                </div>
            </form>
        </section>
    @else
        <section
            class="panel"
            style="padding:22px;margin-bottom:18px;"
        >
            <h2>Step 2 — Scan QR or add manually</h2>

            <p style="margin-top:12px;">
                Scan this QR code with Google Authenticator, Microsoft
                Authenticator, 1Password or another standard TOTP app. If
                scanning is unavailable, use the secret below manually.
            </p>

            <div
                style="
                    margin-top:18px;
                    display:flex;
                    justify-content:center;
                "
            >
                <div
                    style="
                        display:inline-flex;
                        flex-direction:column;
                        align-items:center;
                        gap:10px;
                        padding:16px;
                        background:#ffffff;
                        border-radius:14px;
                    "
                >
                    <img
                        src="{{ $totpQrCodes->dataUri($provisioning['otpauth_uri']) }}"
                        alt="TradeX Admin TOTP QR code"
                        width="280"
                        height="280"
                        style="display:block;max-width:100%;height:auto;"
                    >
                </div>
            </div>

            <p style="margin-top:12px;text-align:center;">
                Scan the QR code, then enter the fresh 6-digit code below.
            </p>

            <div
                style="
                    margin-top:18px;
                    padding:16px;
                    border:1px solid rgba(255,255,255,.1);
                    border-radius:12px;
                    overflow-wrap:anywhere;
                "
            >
                <strong>Secret</strong>
                <br>
                <code>{{ $provisioning['secret'] }}</code>
            </div>

            <div
                style="
                    margin-top:12px;
                    padding:16px;
                    border:1px solid rgba(255,255,255,.1);
                    border-radius:12px;
                    overflow-wrap:anywhere;
                "
            >
                <strong>OTPAuth URI</strong>
                <br>
                <code>{{ $provisioning['otpauth_uri'] }}</code>
            </div>

            <p style="margin-top:12px;">
                Issuer: {{ $provisioning['issuer'] }}
                · Digits: {{ $provisioning['digits'] }}
                · Period: {{ $provisioning['period_seconds'] }} seconds
            </p>
        </section>

        <section
            class="panel"
            style="padding:22px;"
        >
            <h2>Step 3 — Confirm setup</h2>

            <form
                method="POST"
                action="{{ route('admin.account.totp.confirm') }}"
                style="margin-top:18px;"
            >
                @csrf

                <div class="form-grid">
                    <div class="form-group">
                        <label>Current Admin Password</label>

                        <input
                            type="password"
                            name="current_password"
                            autocomplete="current-password"
                            required
                        >

                        @error('current_password')
                            <small>{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Authenticator Code</label>

                        <input
                            type="text"
                            name="code"
                            inputmode="numeric"
                            maxlength="6"
                            autocomplete="one-time-code"
                            required
                        >

                        @error('code')
                            <small>{{ $message }}</small>
                        @enderror
                    </div>
                </div>

                <div style="margin-top:18px;">
                    <button
                        class="primary-btn"
                        type="submit"
                    >
                        Confirm & Enable TOTP
                    </button>
                </div>
            </form>
        </section>
    @endif
@endif
@endsection
