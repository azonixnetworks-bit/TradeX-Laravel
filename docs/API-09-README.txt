TradeX Laravel — API-09 Affiliate Mobile API
============================================

Purpose
-------
Provides the mobile app's full affiliate screens on top of the already-frozen
AFF-01 commercial ledger.

Design decisions
----------------
1. API-09 is PREMIUM-only.
2. It is single-level/direct-referral only.
3. It uses real AFF-01 ledger data; no fake earnings.
4. It does not implement payout because payout ledger does not yet exist.
5. It does not implement a refund feature.
6. Forced payment exceptions remain accounting integrity state only.
7. PayPal transaction references and reversal reasons are not exposed.
8. Public UUIDs are used where entity identity is needed.
9. Existing frozen routes/api.php is not modified.
10. API-09 uses its own service provider and route file, matching API-08's
    isolation pattern.

Install
-------
Extract this package into the TradeX Laravel project root while on:

feature/api-09-affiliate-mobile

No migration is introduced.

Commands
--------
php artisan optimize:clear
php artisan route:list --name=api.v1.affiliate
php artisan test --filter=Affiliate
php artisan test
git diff --check
git status

Expected routes
---------------
GET  api/v1/affiliate/overview
GET  api/v1/affiliate/stages
GET  api/v1/affiliate/referrals
GET  api/v1/affiliate/sales
GET  api/v1/affiliate/commissions
GET  api/v1/affiliate/milestones
POST api/v1/affiliate/share-action

Files modified
--------------
bootstrap/providers.php

Files added
-----------
app/Providers/AffiliateApiServiceProvider.php
app/Services/Affiliates/AffiliateApiService.php

app/Http/Controllers/Api/Affiliate/*
app/Http/Requests/Api/Affiliate/*

config/tradex_affiliate_api.php
routes/api_affiliate.php

tests/Feature/Api/Affiliate/*

docs/API-09-*.txt
