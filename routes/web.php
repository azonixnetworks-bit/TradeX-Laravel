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
| ADMIN-05 â€” Trade Ideas Management
|--------------------------------------------------------------------------
|
| Core Trade Idea setup, targets and audited lifecycle.
| Telegram / WhatsApp distribution is reserved for ADMIN-05A.
|
*/

require __DIR__ . '/admin_trade_ideas.php';

/*
|--------------------------------------------------------------------------
| ADMIN-06 â€” Affiliate Management
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
| ADMIN-07 â€” Payments Management
|--------------------------------------------------------------------------
|
| Provider-verified PayPal visibility plus authorized manual reconciliation
| for Local Bank and USDT TRC20 evidence. No discretionary refund actions.
|
*/

require __DIR__ . '/admin_payments.php';

/*
|--------------------------------------------------------------------------
| ADMIN-08 â€” Payout Management
|--------------------------------------------------------------------------
|
| Controlled affiliate payout review over the frozen payout ledger.
| Amount and destination snapshots remain immutable after user submission.
|
*/

require __DIR__ . '/admin_payouts.php';

/*
|--------------------------------------------------------------------------
| ADMIN-09 â€” Finance Management
|--------------------------------------------------------------------------
|
| Read-only finance reporting and ledger reconciliation across frozen
| payment, affiliate and payout sources. No cross-currency FX invention.
|
*/

require __DIR__ . '/admin_finance.php';

/*
|--------------------------------------------------------------------------
| ADMIN-10 â€” KYC Management
|--------------------------------------------------------------------------
|
| Controlled review of frozen API-13 KYC submissions. Sensitive identity
| and private document evidence access remain permission-gated and audited.
|
*/

require __DIR__ . '/admin_kyc.php';

/*
|--------------------------------------------------------------------------
| ADMIN-11 â€” Security Management
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
| ADMIN-12 â€” Audit Log Management
|--------------------------------------------------------------------------
|
| Immutable read-only audit explorer with render-time sensitive payload
| redaction. Existing audit writers and the audit schema remain untouched.
|
*/

require __DIR__ . '/admin_audit.php';

/*
|--------------------------------------------------------------------------
| ADMIN-13 â€” Administration Management
|--------------------------------------------------------------------------
|
| Delegated Admin User and custom Role/Permission administration.
| SUPER_ADMIN and source-controlled system roles remain protected.
|
*/

require __DIR__ . '/admin_administration.php';

/*
|--------------------------------------------------------------------------
| ADMIN-14 â€” System Settings Management
|--------------------------------------------------------------------------
|
| Safe DB-backed General settings plus read-only ownership/health views for
| Affiliate, Payments, Security and Video configuration. Secrets and frozen
| module business rules remain outside this generic settings surface.
|
*/

require __DIR__ . '/admin_settings.php';
