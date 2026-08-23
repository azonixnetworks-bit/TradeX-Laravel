TradeX Laravel — API-08 Mobile Dashboard API
================================================

BASELINE
--------
API-08 is built on frozen main commit:
84b4b43 — Merge AFF-01 Affiliate Commercial Foundation

Target branch:
feature/api-08-dashboard-mobile

PURPOSE
-------
Expose one mobile dashboard read endpoint backed only by real implemented
TradeX data. API-08 intentionally does not invent payout data, fake affiliate
balances, fake stages, or unsupported financial state.

ENDPOINT
--------
GET /api/v1/dashboard
Route name: api.v1.dashboard.show

SECURITY
--------
- api middleware
- auth:sanctum
- tradex.mobile trusted-device/session middleware
- throttle:api-dashboard-read
- default 120 reads/minute per authenticated user
- no internal numeric database IDs in the response

DASHBOARD SECTIONS
------------------
1. Account
   - public UUID
   - name
   - FREE/PREMIUM account type
   - account status
   - language/country
   - premium access flag

2. Referral
   - permanent referral code
   - general referral URL using TRADEX_AFFILIATE_BASE_URL / existing
     tradex_trade_ideas share configuration
   - direct referral count

3. Affiliate
   - real AFF-01 ledger state
   - qualified sales count
   - current commercial stage
   - progressive next-sale commission rate
   - special approved rate indication without leaking approval internals
   - next one-time milestone gift for normal affiliates
   - PayPal Net Eligible Revenue
   - lifetime commission earned
   - externally forced payment exception adjustments
   - net commission
   - milestone gifts earned
   - total affiliate rewards

   IMPORTANT NO-REFUND RULE:
   API-08 has no voluntary refund button, field, workflow or status.
   "processor_payment_adjustments" represents only adjustments already recorded
   in AFF-01 for externally forced payment-system/legal/duplicate/unauthorized/
   confirmed billing exceptions.

   API-08 does not expose payout balance/status because no payout ledger has
   been implemented yet.

4. Learning
   - accessible published lesson count
   - completed count
   - in-progress count
   - completion percentage
   - latest in-progress continue-learning pointer
   - FREE users count FREE-course lessons plus PREMIUM preview lessons only
   - PREMIUM users count all published accessible lessons

5. Trade Ideas
   - PREMIUM users: latest API-06-available published/closed Trade Idea summary
   - FREE users: no Trade Idea content leakage; premium-required metadata only

6. Our Channel
   - latest published API-07-compatible YouTube item
   - user language is preferred when a published match exists
   - otherwise latest published item is used
   - privacy-enhanced YouTube embed metadata is reused

FILES
-----
Existing file modified:
- bootstrap/providers.php

New runtime files:
- app/Providers/DashboardServiceProvider.php
- routes/api_dashboard.php
- app/Http/Controllers/Api/Dashboard/DashboardController.php
- app/Services/Dashboard/DashboardApiService.php
- config/tradex_dashboard.php

Tests:
- tests/Feature/Api/Dashboard/DashboardApiTest.php
- tests/Feature/Api/Dashboard/DashboardAuthenticationTest.php
- tests/Feature/Api/Dashboard/DashboardRateLimitTest.php
- tests/Feature/Api/Dashboard/DashboardRouteSecurityTest.php

MIGRATION
---------
No migration is required by API-08.
AFF-01 is used as the real affiliate commercial data foundation.

OPTIONAL ENV
------------
TRADEX_DASHBOARD_READ_PER_MINUTE=120

Existing TRADEX_AFFILIATE_BASE_URL is reused for the permanent general
referral link.

LOCAL TEST ORDER
----------------
php artisan optimize:clear
php artisan route:list --name=api.v1.dashboard.show
php artisan test --filter=Dashboard
php artisan test
git diff --check
git status
