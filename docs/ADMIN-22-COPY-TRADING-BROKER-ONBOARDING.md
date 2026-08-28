# ADMIN-22 — Copy Trading Broker Onboarding

## Baseline

- Frozen base: `e93e303311d86d51dccff67619cc5d07590ab8b1`
- Feature branch: `feature/admin-22-copy-trading-broker-onboarding`

## Product flow

Customer flow:

`Copy Trade -> Connect Broker -> Supported Broker -> Open Account via TradeX -> Partner/IB URL -> Return to TradeX -> Add Broker Account -> PENDING -> Admin/Broker Partner Verification -> VERIFIED -> Separate Activation Gates -> ACTIVE`

The customer must create an onboarding intent by using the TradeX partner-link action before a new broker account can be submitted.

## Admin navigation

`COPY TRADING`

- Broker Management
- Broker Account Verification
- Copy Trade Accounts

## Broker configuration

Broker and market configuration is database-backed. Partner/IB URLs are not hard-coded in mobile source code.

Each enabled broker market configures:

- Broker name/code/logo
- Market category (`FOREX`, `DERIV`, `CRYPTO`)
- HTTPS TradeX IB/referral URL
- Required eligible currency
- LIVE-account-only rule
- Active/inactive onboarding state

## Customer broker-account data model

Broker accounts are separate records and are not single-value profile columns. A customer can therefore hold multiple broker accounts across supported market categories.

Account-number protection:

- digits only
- maximum 15 digits
- encrypted at rest
- deterministic HMAC fingerprint for global Broker + Account duplicate detection
- last four digits stored separately for masking/search
- normal customer/admin list views expose only masked account numbers
- full account number is exposed only on the authorized audited verification-review screen

Production should set `TRADEX_COPY_TRADE_FINGERPRINT_KEY` to a separate stable high-entropy secret. Rotating this value without a migration plan changes duplicate fingerprints and must not be done casually.

## Verification lifecycle

Verification states supported by the account aggregate:

- `PENDING`
- `VERIFIED`
- `ACTIVE`
- `SUSPENDED`
- `DISCONNECTED`
- `REJECTED`
- `NEEDS_RESUBMISSION`
- `ESCALATED`

Normal partner verification requires all of the following:

- TradeX IB/partner attribution confirmed
- LIVE account confirmed
- verified currency matches configured eligible currency
- partner-portal reference or private evidence file

Evidence files are stored on the private disk and evidence access is permission-gated and audit logged.

Exceptional cases can be escalated. Once a case is `ESCALATED`, delegated verification admins cannot resolve it; Super Admin review is required.

## Verification is not activation

`VERIFIED` MUST NOT automatically become `ACTIVE`.

ADMIN-22 establishes broker onboarding and partner-account verification only. Copy Trade execution activation remains owned by the later activation layer and must enforce the frozen eligibility/commercial/security gates such as account eligibility, balance, billing/free entitlement, terms acceptance, risk controls and other required checks.

The customer API therefore returns:

- `VERIFIED_PENDING_GATES` for a verified but not active account
- `copy_trade_ready = false` until status is truly `ACTIVE`

## Customer privacy boundary

The normal broker list does not expose the raw referral URL. The URL is returned only from the explicit `Open Account via TradeX` action, which creates an audited onboarding intent.

Internal admin verification/escalation notes are not returned to the customer API. A customer-visible status reason is limited to `REJECTED` and `NEEDS_RESUBMISSION` cases.

## RBAC

ADMIN-22 adds Copy Trading permissions and a dedicated `COPY_TRADING_ADMIN` system role for normal broker configuration/verification operations. Super Admin retains all permissions and is the exceptional-case authority.

## Routes

Customer API routes are isolated in `routes/api_copy_trading.php` and registered by `CopyTradingServiceProvider` without rewriting the frozen main API route file.

Admin routes are isolated in `routes/admin_copy_trading.php`.

Important API routes:

- `GET /api/v1/copy-trade/bootstrap`
- `GET /api/v1/copy-trade/brokers`
- `POST /api/v1/copy-trade/broker-markets/{brokerMarket}/open-account-action`
- `POST /api/v1/copy-trade/broker-accounts`
- `POST /api/v1/copy-trade/broker-accounts/{brokerAccount}/resubmit`
- `GET /api/v1/profile/broker-accounts`

## Local validation

```powershell
git checkout feature/admin-22-copy-trading-broker-onboarding
git pull origin feature/admin-22-copy-trading-broker-onboarding

php artisan migrate
php artisan db:seed --class=AdminPermissionSeeder
php artisan db:seed --class=AdminRoleSeeder
php artisan db:seed --class=AdminRolePermissionSeeder
php artisan optimize:clear

php artisan test tests/Feature/Admin/CopyTrading/CopyTradingNavigationTest.php
php artisan test tests/Feature/Admin/CopyTrading/CopyTradingBrokerOnboardingTest.php
php artisan test tests/Feature/Admin/AdminRouteHardeningTest.php
```

After targeted tests and visual review pass, run the complete regression suite before PR/merge/freeze.
