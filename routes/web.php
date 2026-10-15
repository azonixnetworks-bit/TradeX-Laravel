<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Angular frontend
|--------------------------------------------------------------------------
|
| Keep Laravel as the application entry point so its admin, API, payment and
| authenticated routes are not affected. The compiled public site lives next
| to Laravel's existing public assets; these explicit public routes simply
| return its entry document.
|
*/
$publicFrontend = static function () {
    $index = public_path('tradex-index.html');

    abort_unless(is_file($index), 503, 'The public website is temporarily unavailable.');

    return response()->file($index, [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
};

Route::get('/', $publicFrontend)->name('public.home');

/*
|--------------------------------------------------------------------------
| TradeX client portal
|--------------------------------------------------------------------------
|
| The public website stays a compiled Angular application.  The client portal
| is deliberately served by Laravel so it can share the same trusted origin as
| the authenticated API without changing the admin surface or API contracts.
|
*/
Route::view('/portal', 'client.portal')->name('client.portal');

$publicPages = [
    'about' => [
        'title' => 'A more deliberate way to work with markets.',
        'eyebrow' => 'About TradeX OS',
        'lead' => 'TradeX OS is built around a simple belief: useful trading tools should reduce noise, support learning and make risk a visible part of the process.',
        'blocks' => [
            ['title' => 'Our purpose', 'text' => 'Billionaire Trading Institute brings education, market intelligence and practical planning tools into a connected workspace so people can build a process they understand.'],
            ['title' => 'Our approach', 'text' => 'We value clarity over complexity, process over prediction and risk awareness before any reward-focused thinking.'],
            ['title' => 'What we are not', 'text' => 'TradeX OS does not provide personal investment advice, execute trades for you or promise financial outcomes.'],
        ],
    ],
    'platform' => [
        'title' => 'A connected platform for a stronger routine.',
        'eyebrow' => 'The TradeX platform',
        'lead' => 'Every TradeX OS feature is designed to support a single practical loop: learn, observe, plan and review.',
        'blocks' => [
            ['title' => 'Education workspace', 'text' => 'Structured course content helps you progress through concepts in a clear sequence and return to the lessons that matter.'],
            ['title' => 'Market intelligence', 'text' => 'Focused screeners and market views help you explore instruments without a default indicator overload.'],
            ['title' => 'Planning tools', 'text' => 'Use risk and routine tools to think through a setup before taking action and retain context for future review.'],
        ],
    ],
    'education' => [
        'title' => 'Learn with intention, not information overload.',
        'eyebrow' => 'TradeX education',
        'lead' => 'Education in TradeX OS is organised into practical learning paths that make it easier to connect knowledge with a repeatable routine.',
        'blocks' => [
            ['title' => 'Structured paths', 'text' => 'Move through clearly arranged material instead of trying to piece together a process from disconnected sources.'],
            ['title' => 'Practical context', 'text' => 'Learning is paired with the market, planning and reflection views that help put concepts into context.'],
            ['title' => 'Your pace', 'text' => 'Return to material when you need it and build understanding over time.'],
        ],
    ],
    'market-intelligence' => [
        'title' => 'See the market without the noise.',
        'eyebrow' => 'Market intelligence',
        'lead' => 'TradeX OS market views are designed to help you focus your research and organise your attention.',
        'blocks' => [
            ['title' => 'Focused screeners', 'text' => 'Explore supported markets through clear, responsive screeners and organised instrument views.'],
            ['title' => 'Context first', 'text' => 'Keep research, watchlists and the questions you are asking close to the decision process.'],
            ['title' => 'No performance promises', 'text' => 'Market information is educational and analytical in nature. It is not a recommendation, signal or guarantee.'],
        ],
    ],
    'tools' => [
        'title' => 'Tools that make risk visible before action.',
        'eyebrow' => 'Planning tools',
        'lead' => 'The best time to think about a decision is before you make it. TradeX OS tools are built to support that discipline.',
        'blocks' => [
            ['title' => 'Risk planning', 'text' => 'Use practical calculators and workflows to define the constraints of a setup before you act.'],
            ['title' => 'Routine building', 'text' => 'Turn learning and research into a sequence that is easier to repeat day after day.'],
            ['title' => 'Review context', 'text' => 'Look beyond a single result and reflect on the reasoning and process behind it.'],
        ],
    ],
    'terms' => [
        'title' => 'Terms of use',
        'eyebrow' => 'Public policy centre',
        'lead' => 'These public terms explain the baseline rules for using the TradeX OS website and platform.',
        'blocks' => [
            ['title' => 'Educational purpose', 'text' => 'TradeX OS, operated by Billionaire Trading Institute (PV00296953), provides educational content, market information and workflow tools. Nothing on this website is personal investment, legal or tax advice.'],
            ['title' => 'Responsible use', 'text' => 'You must use the platform lawfully, keep account access secure and not attempt to disrupt, misuse or reverse engineer the service.'],
            ['title' => 'No outcome guarantee', 'text' => 'Markets involve risk. Past activity, educational examples or tools do not guarantee a result or future performance.'],
        ],
    ],
    'privacy' => [
        'title' => 'Privacy notice',
        'eyebrow' => 'Public policy centre',
        'lead' => 'We aim to use only the information needed to operate, secure and improve TradeX OS.',
        'blocks' => [
            ['title' => 'Information we process', 'text' => 'This can include account, authentication, platform activity and support information necessary to provide and protect the service.'],
            ['title' => 'Why we use it', 'text' => 'Information is used for account access, product operations, fraud prevention, support and legal obligations where applicable.'],
            ['title' => 'Your choices', 'text' => 'Account holders can use the authenticated platform and support channels to manage relevant account information.'],
        ],
    ],
    'risk-disclosure' => [
        'title' => 'Risk disclosure',
        'eyebrow' => 'Public policy centre',
        'lead' => 'Before using market information or planning tools, it is important to understand the limits of what a platform can provide.',
        'blocks' => [
            ['title' => 'Market risk', 'text' => 'Financial markets can move rapidly. A loss may occur and may be substantial depending on the instrument, strategy and risk controls used.'],
            ['title' => 'Independent decisions', 'text' => 'You remain responsible for your own decisions. TradeX OS does not place trades or make decisions on your behalf.'],
            ['title' => 'Seek appropriate advice', 'text' => 'Consider independent professional advice where appropriate for your circumstances before risking capital.'],
        ],
    ],
];

foreach ($publicPages as $slug => $page) {
    Route::get('/'.$slug, $publicFrontend)->name('public.'.$slug);
}

foreach (['forex', 'crypto', 'deriv', 'stocks', 'trade-ideas', 'copy-trading', 'affiliate'] as $slug) {
    Route::get('/'.$slug, $publicFrontend)->name('public.'.$slug);
}

Route::get('/faq', $publicFrontend)->name('public.faq');
Route::get('/contact', $publicFrontend)->name('public.contact');

Route::get('/sitemap.xml', function () {
    $paths = ['', 'about', 'platform', 'education', 'market-intelligence', 'tools', 'faq', 'contact', 'terms', 'privacy', 'risk-disclosure'];

    return response()->view('public.sitemap', compact('paths'), 200, ['Content-Type' => 'application/xml']);
})->name('public.sitemap');

Route::view(
    '/payments/paypal/return',
    'payments.paypal-result',
    ['result' => 'return']
)->name('payments.paypal.return');

Route::view(
    '/payments/paypal/cancel',
    'payments.paypal-result',
    ['result' => 'cancel']
)->name('payments.paypal.cancel');

Route::view(
    '/payments/nowpayments/return',
    'payments.nowpayments-result',
    ['result' => 'return']
)->name('payments.nowpayments.return');

Route::view(
    '/payments/nowpayments/cancel',
    'payments.nowpayments-result',
    ['result' => 'cancel']
)->name('payments.nowpayments.cancel');

Route::view(
    '/payments/stripe/success',
    'payments.stripe-result',
    ['result' => 'success']
)->name('payments.stripe.success');

Route::view(
    '/payments/stripe/cancel',
    'payments.stripe-result',
    ['result' => 'cancel']
)->name('payments.stripe.cancel');

require __DIR__ . '/customer_onboarding.php';
require __DIR__ . '/admin.php';
require __DIR__ . '/staff_management.php';
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
| Global Market Clock
|--------------------------------------------------------------------------
|
| Read-only UTC-based, IANA-timezone and DST-aware global trading-centre
| session visualisation for the administration dashboard.
|
*/

require __DIR__ . '/admin_market_clock.php';

/*
|--------------------------------------------------------------------------
| SCREENER-05 — Unified Screener Monitor
|--------------------------------------------------------------------------
|
| Read-only provider, cache and market-data readiness for Forex, Crypto,
| Deriv and Stocks. The monitor never exposes provider credentials.
|
*/

require __DIR__ . '/admin_screener.php';

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
| Product Catalog
|--------------------------------------------------------------------------
|
| Registers the existing permission-gated Product Catalog controller and
| views without changing payment-order snapshots or entitlement semantics.
|
*/

require __DIR__ . '/admin_products.php';

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
| Customer dashboard, secure admin-created invitations and customer segment
| navigation. Credentials, TOTP and final customer activation remain
| customer-owned through the public onboarding flow.
|
*/

require __DIR__ . '/admin_customers.php';

/*
|--------------------------------------------------------------------------
| ADMIN-26 — TradeX Risk Reward Calculator
|--------------------------------------------------------------------------
|
| Permission-gated position sizing for Forex, Commodity, Crypto and Deriv
| reference profiles. This module performs calculations only and never
| places, modifies or transmits live trades.
|
*/

require __DIR__ . '/admin_risk_reward.php';

/*
|--------------------------------------------------------------------------
| ADMIN-27 — Funded Account Advisor
|--------------------------------------------------------------------------
|
| Firm-neutral funded-account risk planning, customer report snapshots,
| partner/referral management and downloadable PDF risk plans. This module
| does not execute trades or hardcode changing third-party challenge rules.
|
*/

require __DIR__ . '/admin_funded_account_advisor.php';

/*
|--------------------------------------------------------------------------
| ADMIN-28 — Daily Trading Routine Planner
|--------------------------------------------------------------------------
|
| Customer-owned routine preferences, timezone-aware daily PDF generation
| and monitored WhatsApp delivery. Admins monitor outcomes but do not fill
| customer trading-routine data on the customer's behalf.
|
*/

require __DIR__ . '/admin_trading_routine.php';

/*
|--------------------------------------------------------------------------
| ADMIN-29 — Economic News Intelligence
|--------------------------------------------------------------------------
|
| Permission-gated economic calendar, impact feeds, live intelligence and
| affected-pair direction. Mutating actions are separated behind dedicated
| permissions and audited services; mobile consumption remains read-only.
|
*/

require __DIR__ . '/admin_economic_news.php';

/*
|--------------------------------------------------------------------------
| ADMIN-30 — Trading Floor & Client Management
|--------------------------------------------------------------------------
|
| Customer registration, NFC/fingerprint reference management, floor access
| logs and commission collection. Biometric templates remain outside TradeX;
| only external device references are stored by this module.
|
*/

require __DIR__ . '/admin_trading_floor.php';

/*
|--------------------------------------------------------------------------
| AI Trading Second Brain — Knowledge Administration
|--------------------------------------------------------------------------
|
| Founder-controlled knowledge capture and review shell. Ingestion,
| embeddings, RAG, MCP synchronization and decision logic are introduced
| only in later phases after the knowledge workflow is approved.
|
*/

require __DIR__ . '/admin_second_brain_knowledge.php';
