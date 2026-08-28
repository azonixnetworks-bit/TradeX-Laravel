<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

require __DIR__ . '/admin.php';
require __DIR__ . '/admin_video_playback.php';
require __DIR__ . '/admin_our_channel.php';

/*
|--------------------------------------------------------------------------
| ADMIN-05 — Trade Ideas Management
|--------------------------------------------------------------------------
|
| Core Trade Idea setup, targets and audited lifecycle.
| Telegram / WhatsApp distribution is reserved for ADMIN-05A.
|
*/

require __DIR__ . '/admin_trade_ideas.php';

/*
|--------------------------------------------------------------------------
| ADMIN-06 — Affiliate Management
|--------------------------------------------------------------------------
|
| Secure affiliate operations over the frozen API-09 commercial ledger:
| direct referrals only, stage configuration, commission visibility and
| management-approved special commission exceptions.
|
*/

require __DIR__ . '/admin_affiliates.php';

/*
|--------------------------------------------------------------------------
| ADMIN-07 — Payments Management
|--------------------------------------------------------------------------
|
| Provider-verified PayPal visibility plus authorized manual reconciliation
| for Local Bank and USDT TRC20 evidence. No discretionary refund actions.
|
*/

require __DIR__ . '/admin_payments.php';

/*
|--------------------------------------------------------------------------
| ADMIN-08 — Payout Management
|--------------------------------------------------------------------------
|
| Controlled affiliate payout review over the frozen payout ledger.
| Amount and destination snapshots remain immutable after user submission.
|
*/

require __DIR__ . '/admin_payouts.php';

/*
|--------------------------------------------------------------------------
| ADMIN-09 — Finance Management
|--------------------------------------------------------------------------
|
| Read-only finance reporting and ledger reconciliation across frozen
| payment, affiliate and payout sources. No cross-currency FX invention.
|
*/

require __DIR__ . '/admin_finance.php';

/*
|--------------------------------------------------------------------------
| ADMIN-10 — KYC Management
|--------------------------------------------------------------------------
|
| Controlled review of frozen API-13 KYC submissions. Sensitive identity
| and private document evidence access remain permission-gated and audited.
|
*/

require __DIR__ . '/admin_kyc.php';

/*
|--------------------------------------------------------------------------
| ADMIN-11 — Security Management
|--------------------------------------------------------------------------
|
| Read-only security operations visibility over audit events, user refresh
| sessions, trusted devices and effective application protection posture.
| Existing user/device mutation flows remain owned by User Management.
|
*/

require __DIR__ . '/admin_security.php';

/*
|--------------------------------------------------------------------------
| ADMIN-12 — Audit Log Management
|--------------------------------------------------------------------------
|
| Immutable read-only audit explorer with render-time sensitive payload
| redaction. Existing audit writers and the audit schema remain untouched.
|
*/

require __DIR__ . '/admin_audit.php';

/*
|--------------------------------------------------------------------------
| ADMIN-13 — Administration Management
|--------------------------------------------------------------------------
|
| Delegated Admin User and custom Role/Permission administration.
| SUPER_ADMIN and source-controlled system roles remain protected.
|
*/

require __DIR__ . '/admin_administration.php';

/*
|--------------------------------------------------------------------------
| ADMIN-14 — System Settings Management
|--------------------------------------------------------------------------
|
| Safe DB-backed General settings plus read-only ownership/health views for
| Affiliate, Payments, Security and Video configuration. Secrets and frozen
| module business rules remain outside this generic settings surface.
|
*/

require __DIR__ . '/admin_settings.php';

/*
|--------------------------------------------------------------------------
| ADMIN-15 — Notifications & Broadcast Management
|--------------------------------------------------------------------------
|
| Admin orchestration for the existing API-10 in-app notification model.
| Drafts may be created/edited, then published or scheduled by creating
| user_notification_receipts for the current eligible recipient snapshot.
|
*/

require __DIR__ . '/admin_notifications.php';

/*
|--------------------------------------------------------------------------
| ADMIN-16 — Integrations Management
|--------------------------------------------------------------------------
|
| Secret-safe payment-provider control plane for PayPal, Stripe,
| NOWPayments and Local Bank Deposit. Existing transaction adapters remain
| owned by the frozen Payments API; ADMIN-16 never rewrites credentials.
|
*/

require __DIR__ . '/admin_integrations.php';

/*
|--------------------------------------------------------------------------
| ADMIN-17 — Legal & Policy Management
|--------------------------------------------------------------------------
|
| Versioned legal policy drafting and immutable publication history.
| Substantive legal wording remains subject to qualified legal review.
|
*/

require __DIR__ . '/admin_legal.php';

/*
|--------------------------------------------------------------------------
| ADMIN-18 — Languages & Translation Management
|--------------------------------------------------------------------------
|
| Seven-language human-review translation catalog. Runtime consumption by
| mobile/API remains an explicit future integration boundary.
|
*/

require __DIR__ . '/admin_languages.php';

/*
|--------------------------------------------------------------------------
| ADMIN-19 — Admin Website Final Hardening
|--------------------------------------------------------------------------
|
| Admin-wide security headers, session hardening and rollout-safe TOTP
| enrollment/challenge routes. Production release gates are verified by
| tradex:admin-release-check --production.
|
*/

require __DIR__ . '/admin_hardening.php';

/*
|--------------------------------------------------------------------------
| ADMIN-20 — Customer Management & Admin Onboarding
|--------------------------------------------------------------------------
|
| Customer dashboard, safe admin-created pre-onboarding invitations and
| customer segment navigation. Invitations never create credentials,
| TOTP secrets, premium entitlements or payment state.
|
*/

require __DIR__ . '/admin_customers.php';
