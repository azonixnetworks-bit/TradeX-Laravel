<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#03070d">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow,noarchive">

    <title>Activate TradeX OS Account</title>

    <link
        rel="icon"
        type="image/png"
        href="{{ asset('admin/brand/tradex-favicon.png') }}"
    >

    <style>
        :root {
            --bg: #03070d;
            --surface: #07111e;
            --surface-2: #0a1829;
            --surface-3: #0d2036;
            --gold: #dcae45;
            --gold-light: #f1ce72;
            --blue: #1477ff;
            --text: #ffffff;
            --muted: #8798ad;
            --line: rgba(255,255,255,.09);
            --danger: #ff6978;
            --success: #39d98a;
            --warning: #f4bf4f;
        }

        * { box-sizing: border-box; }

        html { color-scheme: dark; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 12% 14%, rgba(20,119,255,.16), transparent 30%),
                radial-gradient(circle at 88% 84%, rgba(220,174,69,.10), transparent 28%),
                var(--bg);
            padding: 28px;
        }

        button, input { font: inherit; }

        .shell {
            width: min(1160px, 100%);
            min-height: calc(100vh - 56px);
            margin: 0 auto;
            display: grid;
            grid-template-columns: .82fr 1.18fr;
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 30px;
            overflow: hidden;
            background: rgba(5,14,25,.94);
            box-shadow: 0 45px 110px rgba(0,0,0,.58);
        }

        .brand-panel {
            position: relative;
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: linear-gradient(145deg, rgba(10,37,68,.88), rgba(3,9,16,.97));
            overflow: hidden;
        }

        .brand-panel::before,
        .brand-panel::after {
            content: "";
            position: absolute;
            border-radius: 999px;
            pointer-events: none;
        }

        .brand-panel::before {
            width: 360px;
            height: 360px;
            right: -170px;
            top: -150px;
            background: radial-gradient(circle, rgba(20,119,255,.17), transparent 70%);
        }

        .brand-panel::after {
            width: 430px;
            height: 430px;
            left: -220px;
            bottom: -230px;
            background: radial-gradient(circle, rgba(220,174,69,.12), transparent 68%);
        }

        .brand-content,
        .brand-foot { position: relative; z-index: 1; }

        .brand-logo {
            width: min(245px, 80%);
            max-height: 210px;
            object-fit: contain;
            object-position: left center;
            margin-bottom: 38px;
        }

        .eyebrow {
            color: var(--gold);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: 2.7px;
            text-transform: uppercase;
        }

        .brand-panel h1 {
            margin: 18px 0 18px;
            max-width: 460px;
            font-size: clamp(38px, 4.3vw, 60px);
            line-height: 1.03;
            letter-spacing: -1.8px;
        }

        .brand-panel h1 span { color: var(--gold-light); }

        .brand-copy {
            max-width: 490px;
            color: #92a4b9;
            font-size: 15px;
            line-height: 1.8;
        }

        .trust-list {
            display: grid;
            gap: 12px;
            margin-top: 34px;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 11px;
            color: #b6c3d1;
            font-size: 13px;
        }

        .trust-dot {
            width: 9px;
            height: 9px;
            flex: 0 0 auto;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 16px rgba(220,174,69,.38);
        }

        .brand-foot {
            margin-top: 42px;
            color: #64778c;
            font-size: 10px;
            font-weight: 750;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .flow-panel {
            padding: 46px 52px;
            display: flex;
            flex-direction: column;
        }

        .mobile-brand { display: none; }

        .progress-wrap { margin-bottom: 34px; }

        .progress-meta {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 10px;
            color: var(--muted);
            font-size: 12px;
        }

        .progress-track {
            height: 7px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(255,255,255,.07);
        }

        .progress-bar {
            width: 5%;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #b8862a, var(--gold-light));
            transition: width .35s ease;
        }

        .card {
            width: 100%;
            max-width: 700px;
            margin: auto;
        }

        .step-kicker {
            color: var(--gold);
            font-size: 11px;
            font-weight: 850;
            letter-spacing: 2.2px;
            text-transform: uppercase;
        }

        h2 {
            margin: 11px 0 9px;
            font-size: clamp(30px, 4vw, 43px);
            letter-spacing: -1px;
        }

        .subtitle {
            margin: 0 0 28px;
            color: var(--muted);
            line-height: 1.7;
        }

        .notice {
            margin: 18px 0;
            padding: 14px 16px;
            border: 1px solid rgba(220,174,69,.18);
            border-radius: 14px;
            color: #c9d3df;
            background: rgba(220,174,69,.06);
            font-size: 13px;
            line-height: 1.6;
        }

        .notice.success {
            border-color: rgba(57,217,138,.22);
            background: rgba(57,217,138,.07);
        }

        .notice.danger {
            border-color: rgba(255,105,120,.24);
            background: rgba(255,105,120,.07);
            color: #ffd7dc;
        }

        label {
            display: block;
            margin: 17px 0 8px;
            color: #b7c4d2;
            font-size: 13px;
            font-weight: 650;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            height: 56px;
            padding: 0 17px;
            color: #fff;
            outline: 0;
            border: 1px solid var(--line);
            border-radius: 13px;
            background: #091727;
            transition: .2s ease;
        }

        input:focus {
            border-color: rgba(20,119,255,.8);
            box-shadow: 0 0 0 4px rgba(20,119,255,.09);
        }

        .otp-input {
            letter-spacing: 8px;
            font-size: 22px;
            font-weight: 800;
            text-align: center;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }

        .btn {
            min-height: 50px;
            padding: 0 20px;
            border: 0;
            border-radius: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: transform .16s ease, opacity .16s ease;
        }

        .btn:hover:not(:disabled) { transform: translateY(-1px); }
        .btn:disabled { opacity: .45; cursor: not-allowed; }

        .btn-primary {
            color: #101316;
            background: linear-gradient(135deg, #c79535, var(--gold-light));
            box-shadow: 0 12px 28px rgba(220,174,69,.14);
        }

        .btn-secondary {
            color: #d3deea;
            border: 1px solid var(--line);
            background: #0a1829;
        }

        .btn-danger {
            color: #ffd9dd;
            border: 1px solid rgba(255,105,120,.22);
            background: rgba(255,105,120,.07);
        }

        .spinner {
            width: 42px;
            height: 42px;
            margin: 18px 0;
            border: 4px solid rgba(255,255,255,.08);
            border-top-color: var(--gold);
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .qr-wrap {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 24px;
            align-items: center;
            margin: 24px 0;
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 18px;
            background: rgba(255,255,255,.025);
        }

        .qr-box {
            width: 220px;
            height: 220px;
            display: grid;
            place-items: center;
            padding: 14px;
            border-radius: 16px;
            background: #fff;
        }

        .qr-box img { width: 100%; height: 100%; object-fit: contain; }

        .secret {
            margin-top: 10px;
            padding: 12px;
            word-break: break-all;
            border-radius: 11px;
            color: var(--gold-light);
            background: #06101c;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 13px;
        }

        .policy-list { display: grid; gap: 14px; }

        .policy {
            padding: 18px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: rgba(255,255,255,.025);
        }

        .policy-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
        }

        .policy-title { margin: 0; font-size: 16px; }
        .policy-version { color: #758aa0; font-size: 11px; white-space: nowrap; }

        .policy-summary {
            margin: 9px 0 12px;
            color: #91a3b7;
            font-size: 13px;
            line-height: 1.6;
        }

        details {
            margin: 10px 0 14px;
            color: #aebdcd;
            font-size: 13px;
        }

        details summary { cursor: pointer; color: var(--gold-light); }

        .policy-content {
            max-height: 220px;
            margin-top: 12px;
            overflow: auto;
            white-space: pre-wrap;
            padding: 13px;
            border-radius: 11px;
            color: #a8b8c8;
            background: #06101c;
            line-height: 1.6;
        }

        .check-row {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-top: 12px;
            color: #c6d1dc;
            font-size: 13px;
            line-height: 1.5;
        }

        .check-row input { margin-top: 3px; accent-color: var(--gold); }

        .codes {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 9px;
            margin: 20px 0;
        }

        .code {
            padding: 12px;
            border: 1px solid rgba(220,174,69,.16);
            border-radius: 11px;
            color: var(--gold-light);
            background: #06101c;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px;
            user-select: all;
        }

        .security-note {
            display: flex;
            gap: 12px;
            margin-top: 18px;
            padding: 15px;
            border-radius: 14px;
            color: #b8c5d3;
            background: rgba(20,119,255,.07);
            border: 1px solid rgba(20,119,255,.15);
            font-size: 13px;
            line-height: 1.55;
        }

        .status-icon {
            width: 66px;
            height: 66px;
            display: grid;
            place-items: center;
            margin-bottom: 20px;
            border-radius: 20px;
            color: #07120c;
            background: var(--success);
            font-size: 30px;
            font-weight: 900;
            box-shadow: 0 18px 40px rgba(57,217,138,.15);
        }

        .error-code {
            margin-top: 9px;
            color: #8397ac;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
        }

        .hidden { display: none !important; }

        @media (max-width: 900px) {
            body { padding: 0; }
            .shell { min-height: 100vh; border: 0; border-radius: 0; grid-template-columns: 1fr; }
            .brand-panel { display: none; }
            .flow-panel { padding: 28px 22px 36px; }
            .mobile-brand { display: block; margin-bottom: 24px; }
            .mobile-brand img { width: 145px; max-height: 125px; object-fit: contain; object-position: left center; }
            .qr-wrap { grid-template-columns: 1fr; }
            .qr-box { width: min(220px, 100%); }
        }

        @media (max-width: 560px) {
            .row, .codes { grid-template-columns: 1fr; }
            .flow-panel { padding: 24px 18px 30px; }
        }
    </style>
</head>
<body data-tradex-customer-activation-ui="v1">
<div class="shell">
    <aside class="brand-panel">
        <div class="brand-content">
            <img
                class="brand-logo"
                src="{{ asset('admin/brand/tradex-logo.png') }}"
                alt="TradeX OS"
            >
            <div class="eyebrow">Secure Customer Activation</div>
            <h1>Build your account with <span>security first.</span></h1>
            <p class="brand-copy">
                Verify your contact details, create your own password, protect the account with authenticator-based MFA, and review the current TradeX policies before activation.
            </p>
            <div class="trust-list">
                <div class="trust-item"><span class="trust-dot"></span>Admin never receives your password or authenticator secret.</div>
                <div class="trust-item"><span class="trust-dot"></span>Activation links are single-use and expire automatically.</div>
                <div class="trust-item"><span class="trust-dot"></span>Recovery codes are shown once and stored only as secure hashes.</div>
            </div>
        </div>
        <div class="brand-foot">TradeX OS · Learn. Trade. Grow.</div>
    </aside>

    <main class="flow-panel">
        <div class="mobile-brand">
            <img src="{{ asset('admin/brand/tradex-logo.png') }}" alt="TradeX OS">
        </div>

        <div class="progress-wrap">
            <div class="progress-meta">
                <span id="progressLabel">Preparing secure activation</span>
                <span id="progressValue">5%</span>
            </div>
            <div class="progress-track" aria-hidden="true">
                <div id="progressBar" class="progress-bar"></div>
            </div>
        </div>

        <section id="app" class="card" aria-live="polite"></section>
    </main>
</div>

<script>
(() => {
    'use strict';

    const STORAGE_KEY = 'tradex.customerActivation.v1';
    const app = document.getElementById('app');
    const progressBar = document.getElementById('progressBar');
    const progressLabel = document.getElementById('progressLabel');
    const progressValue = document.getElementById('progressValue');

    const progress = {
        VERIFY_EMAIL_OTP: [15, 'Verify email'],
        RESEND_EMAIL_OTP: [15, 'Verify email'],
        VERIFY_WHATSAPP_OTP: [28, 'Verify WhatsApp'],
        RESEND_WHATSAPP_OTP: [28, 'Verify WhatsApp'],
        SET_PASSWORD: [43, 'Create password'],
        SETUP_TOTP: [58, 'Set up authenticator'],
        CONFIRM_TOTP: [66, 'Confirm authenticator'],
        ACCEPT_LEGAL_TERMS: [80, 'Review policies'],
        COMPLETE_ONBOARDING: [91, 'Activate account'],
        LOGIN: [100, 'Account activated'],
    };

    let state = readState();
    let invitationBootstrap = null;
    let policies = [];

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function readState() {
        try {
            const parsed = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (_) {
            return {};
        }
    }

    function saveState(next) {
        state = { ...state, ...next };
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    }

    function clearState() {
        state = {};
        sessionStorage.removeItem(STORAGE_KEY);
    }

    function sessionPayload(extra = {}) {
        return {
            registration_id: state.registrationId,
            registration_token: state.registrationToken,
            ...extra,
        };
    }

    function setProgress(action) {
        const [value, label] = progress[action] || [8, 'Secure activation'];
        progressBar.style.width = `${value}%`;
        progressLabel.textContent = label;
        progressValue.textContent = `${value}%`;
    }

    function loading(title, message) {
        app.innerHTML = `
            <div class="step-kicker">TradeX Secure Onboarding</div>
            <h2>${escapeHtml(title)}</h2>
            <p class="subtitle">${escapeHtml(message)}</p>
            <div class="spinner" aria-label="Loading"></div>
        `;
    }

    function showError(message, code = '', canRetry = false) {
        app.innerHTML = `
            <div class="step-kicker">Activation Attention</div>
            <h2>We could not continue</h2>
            <div class="notice danger">${escapeHtml(message || 'An unexpected error occurred.')}</div>
            ${code ? `<div class="error-code">${escapeHtml(code)}</div>` : ''}
            <div class="actions">
                ${canRetry ? '<button id="retryButton" class="btn btn-primary" type="button">Try Again</button>' : ''}
                <button id="clearButton" class="btn btn-secondary" type="button">Start From Email Link</button>
            </div>
        `;

        document.getElementById('clearButton')?.addEventListener('click', () => {
            clearState();
            window.location.assign('/onboarding/activate');
        });
    }

    async function api(path, payload) {
        const response = await fetch(path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        let body = null;
        try {
            body = await response.json();
        } catch (_) {
            body = null;
        }

        if (! response.ok) {
            const error = new Error(
                body?.error?.message || body?.message || 'The secure request could not be completed.'
            );
            error.code = body?.error?.code || `HTTP_${response.status}`;
            error.status = response.status;
            throw error;
        }

        return body || { success: true };
    }

    function captureInvitationFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const invitationId = params.get('invitation_id');
        const token = params.get('token');

        if (invitationId && token) {
            invitationBootstrap = { invitationId, token };
            history.replaceState(null, document.title, window.location.pathname);
            return true;
        }

        return false;
    }

    async function beginFromInvitation() {
        loading('Securing your invitation', 'Validating the one-time link and preparing email verification.');
        setProgress('VERIFY_EMAIL_OTP');

        try {
            const body = await api('/api/v1/auth/invite/activate', {
                invitation_id: invitationBootstrap.invitationId,
                token: invitationBootstrap.token,
            });

            invitationBootstrap = null;

            saveState({
                registrationId: body.data.registration_id,
                registrationToken: body.data.registration_token,
                expiresAt: body.data.expires_at,
            });

            await routeAction(body.data.next_action);
        } catch (error) {
            showError(error.message, error.code);
        }
    }

    async function resume() {
        if (! state.registrationId || ! state.registrationToken) {
            showError(
                'Open the latest TradeX activation email and use its secure activation link.',
                'ACTIVATION_LINK_REQUIRED'
            );
            return;
        }

        loading('Resuming secure activation', 'Checking your latest completed onboarding step.');

        try {
            const body = await api(
                '/api/v1/auth/register/status',
                sessionPayload()
            );

            await routeAction(body.data.next_action);
        } catch (error) {
            showError(error.message, error.code);
        }
    }

    async function routeAction(action) {
        setProgress(action);

        switch (action) {
            case 'VERIFY_EMAIL_OTP':
            case 'RESEND_EMAIL_OTP':
                renderEmailOtp();
                break;

            case 'VERIFY_WHATSAPP_OTP':
            case 'RESEND_WHATSAPP_OTP':
                renderWhatsAppOtp();
                break;

            case 'SET_PASSWORD':
                renderPassword();
                break;

            case 'SETUP_TOTP':
            case 'CONFIRM_TOTP':
                await renderTotp();
                break;

            case 'ACCEPT_LEGAL_TERMS':
                await renderLegalPolicies();
                break;

            case 'COMPLETE_ONBOARDING':
                renderComplete();
                break;

            case 'LOGIN':
                await bootstrapRecoveryCodesAfterCompletedSession();
                break;

            default:
                showError(
                    'The activation session returned an unsupported next step.',
                    'UNSUPPORTED_ONBOARDING_STEP'
                );
        }
    }

    function renderEmailOtp() {
        app.innerHTML = `
            <div class="step-kicker">Step 1 · Identity</div>
            <h2>Verify your email</h2>
            <p class="subtitle">Enter the six-digit verification code sent to the email address on your TradeX invitation.</p>
            <form id="emailForm">
                <label for="emailOtp">Email verification code</label>
                <input id="emailOtp" class="otp-input" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required>
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Verify Email</button>
                    <button id="resendEmail" class="btn btn-secondary" type="button">Resend Code</button>
                </div>
            </form>
            <div id="inlineNotice"></div>
        `;

        document.getElementById('emailForm').addEventListener('submit', async event => {
            event.preventDefault();
            const code = document.getElementById('emailOtp').value.trim();
            await submitStep(
                '/api/v1/auth/register/email/verify',
                sessionPayload({ otp_code: code })
            );
        });

        document.getElementById('resendEmail').addEventListener('click', async () => {
            await resend('/api/v1/auth/register/email/resend', 'A fresh email code was requested.');
        });
    }

    function renderWhatsAppOtp() {
        app.innerHTML = `
            <div class="step-kicker">Step 2 · Contact</div>
            <h2>Verify WhatsApp</h2>
            <p class="subtitle">Enter the six-digit code sent through the configured TradeX WhatsApp verification channel.</p>
            <form id="whatsappForm">
                <label for="whatsappOtp">WhatsApp verification code</label>
                <input id="whatsappOtp" class="otp-input" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required>
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Verify WhatsApp</button>
                    <button id="resendWhatsapp" class="btn btn-secondary" type="button">Resend Code</button>
                </div>
            </form>
            <div id="inlineNotice"></div>
        `;

        document.getElementById('whatsappForm').addEventListener('submit', async event => {
            event.preventDefault();
            const code = document.getElementById('whatsappOtp').value.trim();
            await submitStep(
                '/api/v1/auth/register/whatsapp/verify',
                sessionPayload({ otp_code: code })
            );
        });

        document.getElementById('resendWhatsapp').addEventListener('click', async () => {
            await resend('/api/v1/auth/register/whatsapp/resend', 'A fresh WhatsApp code was requested.');
        });
    }

    function renderPassword() {
        app.innerHTML = `
            <div class="step-kicker">Step 3 · Credentials</div>
            <h2>Create your password</h2>
            <p class="subtitle">Only you create and know this password. TradeX administrators cannot view it.</p>
            <div class="notice">Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</div>
            <form id="passwordForm">
                <div class="row">
                    <div>
                        <label for="password">Password</label>
                        <input id="password" type="password" autocomplete="new-password" minlength="12" required>
                    </div>
                    <div>
                        <label for="passwordConfirmation">Confirm password</label>
                        <input id="passwordConfirmation" type="password" autocomplete="new-password" minlength="12" required>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Save Password Securely</button>
                </div>
            </form>
            <div id="inlineNotice"></div>
        `;

        document.getElementById('passwordForm').addEventListener('submit', async event => {
            event.preventDefault();
            const password = document.getElementById('password').value;
            const confirmation = document.getElementById('passwordConfirmation').value;

            if (password !== confirmation) {
                inlineError('The password confirmation does not match.');
                return;
            }

            await submitStep(
                '/api/v1/auth/invite/password',
                sessionPayload({
                    password,
                    password_confirmation: confirmation,
                })
            );
        });
    }

    async function renderTotp() {
        loading('Preparing authenticator protection', 'Generating your private TOTP setup information.');

        try {
            const body = await api(
                '/api/v1/auth/register/totp/setup',
                sessionPayload()
            );
            const data = body.data;

            app.innerHTML = `
                <div class="step-kicker">Step 4 · Multi-Factor Security</div>
                <h2>Protect with an authenticator</h2>
                <p class="subtitle">Scan the QR code with Google Authenticator, Microsoft Authenticator, 1Password, or another compatible TOTP app.</p>
                <div class="qr-wrap">
                    <div class="qr-box">
                        <img src="${escapeHtml(data.qr_code_data_uri)}" alt="TradeX authenticator QR code">
                    </div>
                    <div>
                        <div class="eyebrow">Manual setup key</div>
                        <div class="secret">${escapeHtml(data.secret)}</div>
                        <div class="security-note">
                            <span>🔒</span>
                            <span>Do not send this secret to anyone, including TradeX staff. After setup, enter the current six-digit authenticator code below.</span>
                        </div>
                    </div>
                </div>
                <form id="totpForm">
                    <label for="totpCode">Authenticator code</label>
                    <input id="totpCode" class="otp-input" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required>
                    <div class="actions">
                        <button class="btn btn-primary" type="submit">Confirm Authenticator</button>
                    </div>
                </form>
                <div id="inlineNotice"></div>
            `;

            document.getElementById('totpForm').addEventListener('submit', async event => {
                event.preventDefault();
                const code = document.getElementById('totpCode').value.trim();
                await submitStep(
                    '/api/v1/auth/invite/totp/confirm',
                    sessionPayload({ totp_code: code })
                );
            });
        } catch (error) {
            showError(error.message, error.code);
        }
    }

    async function renderLegalPolicies() {
        loading('Loading current policies', 'Fetching the exact published policy versions required for activation.');

        try {
            const body = await api(
                '/api/v1/auth/invite/legal/policies',
                sessionPayload()
            );

            policies = Array.isArray(body.data.items) ? body.data.items : [];

            const policyHtml = policies.map((policy, index) => `
                <article class="policy">
                    <div class="policy-head">
                        <h3 class="policy-title">${escapeHtml(policy.display_name || policy.title)}</h3>
                        <span class="policy-version">Version ${escapeHtml(policy.version_number)}</span>
                    </div>
                    <p class="policy-summary">${escapeHtml(policy.summary || 'Review the full policy before accepting.')}</p>
                    <details>
                        <summary>Read full policy</summary>
                        <div class="policy-content">${escapeHtml(policy.content)}</div>
                    </details>
                    <label class="check-row">
                        <input class="policy-check" type="checkbox" data-index="${index}">
                        <span>I have reviewed and accept this policy version.</span>
                    </label>
                </article>
            `).join('');

            app.innerHTML = `
                <div class="step-kicker">Step 5 · Legal Review</div>
                <h2>Review before activation</h2>
                <p class="subtitle">TradeX records the exact policy version and content hash you accept. Payment-specific no-refund terms are not silently added to this account-activation step.</p>
                <div class="policy-list">${policyHtml}</div>
                <div class="actions">
                    <button id="acceptPolicies" class="btn btn-primary" type="button">Accept & Continue</button>
                </div>
                <div id="inlineNotice"></div>
            `;

            document.getElementById('acceptPolicies').addEventListener('click', async () => {
                const checks = [...document.querySelectorAll('.policy-check')];
                if (checks.some(check => ! check.checked)) {
                    inlineError('Please review and accept every required policy before continuing.');
                    return;
                }

                const acceptances = policies.map(policy => ({
                    policy_uuid: policy.uuid,
                    content_sha256: policy.content_sha256,
                    accepted: true,
                }));

                await submitStep(
                    '/api/v1/auth/invite/legal/accept',
                    sessionPayload({ acceptances })
                );
            });
        } catch (error) {
            showError(error.message, error.code);
        }
    }

    function renderComplete() {
        app.innerHTML = `
            <div class="step-kicker">Final Step · Activation</div>
            <h2>Ready to activate</h2>
            <p class="subtitle">Your email, WhatsApp, password, authenticator, and required legal acceptance are complete.</p>
            <div class="notice success">Your account will be created as an ACTIVE FREE account. Premium entitlements remain separate from account activation.</div>
            <div class="actions">
                <button id="completeButton" class="btn btn-primary" type="button">Activate My TradeX Account</button>
            </div>
            <div id="inlineNotice"></div>
        `;

        document.getElementById('completeButton').addEventListener('click', async () => {
            loading('Activating your account', 'Creating your secure customer account and finalizing the invitation.');

            try {
                await api(
                    '/api/v1/auth/invite/complete',
                    sessionPayload()
                );
                setProgress('LOGIN');
                await bootstrapRecoveryCodesAfterCompletedSession();
            } catch (error) {
                showError(error.message, error.code, true);
                document.getElementById('retryButton')?.addEventListener('click', renderComplete);
            }
        });
    }

    async function bootstrapRecoveryCodesAfterCompletedSession() {
        loading('Creating recovery codes', 'Preparing one-time backup codes for emergency MFA access.');
        setProgress('LOGIN');

        try {
            const body = await api(
                '/api/v1/auth/invite/recovery-codes',
                sessionPayload()
            );

            renderRecoveryCodes(body.data.recovery_codes || []);
        } catch (error) {
            if (error.code === 'MFA_RECOVERY_CODES_ALREADY_ISSUED') {
                renderCompletedWithoutCodes();
                return;
            }

            showError(error.message, error.code, true);
            document.getElementById('retryButton')?.addEventListener('click', bootstrapRecoveryCodesAfterCompletedSession);
        }
    }

    function renderRecoveryCodes(codes) {
        const codeHtml = codes.map(code => `<div class="code">${escapeHtml(code)}</div>`).join('');

        app.innerHTML = `
            <div class="status-icon">✓</div>
            <div class="step-kicker">Account Activated</div>
            <h2>Save your recovery codes</h2>
            <p class="subtitle">These codes can replace your authenticator code during login if you lose access to your authenticator. Each code works once.</p>
            <div class="notice danger"><strong>Shown once:</strong> TradeX stores only secure hashes. After leaving this screen, these exact codes cannot be displayed again.</div>
            <div class="codes">${codeHtml}</div>
            <div class="actions">
                <button id="copyCodes" class="btn btn-secondary" type="button">Copy Codes</button>
                <button id="downloadCodes" class="btn btn-secondary" type="button">Download .txt</button>
            </div>
            <label class="check-row">
                <input id="savedCodes" type="checkbox">
                <span>I saved these recovery codes in a secure place.</span>
            </label>
            <div class="actions">
                <button id="finishButton" class="btn btn-primary" type="button" disabled>Finish Activation</button>
            </div>
            <div id="inlineNotice"></div>
        `;

        document.getElementById('copyCodes').addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(codes.join('\n'));
                inlineSuccess('Recovery codes copied. Keep them in a secure private location.');
            } catch (_) {
                inlineError('Clipboard access was blocked by the browser. Use Download instead.');
            }
        });

        document.getElementById('downloadCodes').addEventListener('click', () => {
            const text = [
                'TradeX OS MFA Recovery Codes',
                'Each code can be used once.',
                '',
                ...codes,
                '',
                'Keep this file private and offline where possible.',
            ].join('\n');

            const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = 'tradex-mfa-recovery-codes.txt';
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            URL.revokeObjectURL(url);
        });

        document.getElementById('savedCodes').addEventListener('change', event => {
            document.getElementById('finishButton').disabled = ! event.target.checked;
        });

        document.getElementById('finishButton').addEventListener('click', () => {
            clearState();
            renderFinished();
        });
    }

    function renderCompletedWithoutCodes() {
        app.innerHTML = `
            <div class="status-icon">✓</div>
            <div class="step-kicker">Account Activated</div>
            <h2>Your TradeX account is active</h2>
            <p class="subtitle">Recovery codes were already issued earlier and cannot be displayed again from this activation link.</p>
            <div class="notice">Use your authenticator to sign in. From an authenticated TradeX session, recovery codes can be regenerated after password and fresh TOTP verification.</div>
            <div class="actions">
                <button id="finishButton" class="btn btn-primary" type="button">Finish</button>
            </div>
        `;

        document.getElementById('finishButton').addEventListener('click', () => {
            clearState();
            renderFinished();
        });
    }

    function renderFinished() {
        setProgress('LOGIN');
        app.innerHTML = `
            <div class="status-icon">✓</div>
            <div class="step-kicker">Secure Activation Complete</div>
            <h2>Welcome to TradeX OS</h2>
            <p class="subtitle">Your customer account is ACTIVE. You can now open the TradeX app and sign in with your email, password, and authenticator code — or one unused recovery code if required.</p>
            <div class="notice success">Account activation and premium access are separate. Your current subscription or entitlement determines premium features.</div>
        `;
    }

    async function submitStep(path, payload) {
        disableButtons(true);
        clearInlineNotice();

        try {
            const body = await api(path, payload);
            const action = body?.data?.next_action;

            if (! action) {
                await resume();
                return;
            }

            await routeAction(action);
        } catch (error) {
            inlineError(error.message, error.code);
            disableButtons(false);
        }
    }

    async function resend(path, successMessage) {
        disableButtons(true);
        clearInlineNotice();

        try {
            await api(path, sessionPayload());
            inlineSuccess(successMessage);
        } catch (error) {
            inlineError(error.message, error.code);
        } finally {
            disableButtons(false);
        }
    }

    function disableButtons(disabled) {
        app.querySelectorAll('button').forEach(button => {
            button.disabled = disabled;
        });
    }

    function clearInlineNotice() {
        const target = document.getElementById('inlineNotice');
        if (target) target.innerHTML = '';
    }

    function inlineError(message, code = '') {
        const target = document.getElementById('inlineNotice');
        if (! target) return;
        target.innerHTML = `
            <div class="notice danger">${escapeHtml(message)}</div>
            ${code ? `<div class="error-code">${escapeHtml(code)}</div>` : ''}
        `;
    }

    function inlineSuccess(message) {
        const target = document.getElementById('inlineNotice');
        if (! target) return;
        target.innerHTML = `<div class="notice success">${escapeHtml(message)}</div>`;
    }

    if (captureInvitationFromUrl()) {
        clearState();
        beginFromInvitation();
    } else {
        resume();
    }
})();
</script>
</body>
</html>
