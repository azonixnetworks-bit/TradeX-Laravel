# ADMIN-16 — Integrations Management

Base main:

`bec01b378d28ec4d63077a1c3064a25d4d3a605d`

Feature branch:

`feature/admin-16-integrations-management`

## Locked provider list

ADMIN-16 manages exactly four payment integrations:

- PayPal
- Stripe
- NOWPayments
- Local Bank Deposit

## Architecture boundary

ADMIN-16 is the secure **admin integration control plane**.

Existing transaction adapters remain owned by the frozen Payments API.

Already wired in the current Payments API:

- PayPal
- Local Bank Deposit

Registered and readiness-managed by ADMIN-16, but not yet transaction-wired:

- Stripe
- NOWPayments

ADMIN-16 deliberately does not pretend that a provider is live when a checkout,
capture, webhook and settlement adapter has not been implemented.

A later Payments API provider extension must add Stripe/NOWPayments transaction
execution with its own idempotency, webhook verification, settlement mapping,
reversal handling and regression tests.

## Secret policy

Secrets remain environment/config owned.

ADMIN-16 never stores or renders:

- PayPal client secret
- Stripe secret key
- Stripe webhook secret
- NOWPayments API key
- NOWPayments IPN secret
- Local Bank account number
- private payment destinations
- `.env` contents

The UI displays boolean readiness checks only.

## Stripe environment keys

- `TRADEX_STRIPE_ENABLED`
- `TRADEX_STRIPE_COUNTRIES`
- `TRADEX_STRIPE_ENVIRONMENT`
- `TRADEX_STRIPE_PUBLISHABLE_KEY`
- `TRADEX_STRIPE_SECRET_KEY`
- `TRADEX_STRIPE_WEBHOOK_SECRET`

Secure defaults:

- enabled: false
- countries: empty
- environment: SANDBOX

## NOWPayments environment keys

- `TRADEX_NOWPAYMENTS_ENABLED`
- `TRADEX_NOWPAYMENTS_COUNTRIES`
- `TRADEX_NOWPAYMENTS_ENVIRONMENT`
- `TRADEX_NOWPAYMENTS_API_KEY`
- `TRADEX_NOWPAYMENTS_IPN_SECRET`
- `TRADEX_NOWPAYMENTS_SANDBOX_BASE_URL`
- `TRADEX_NOWPAYMENTS_LIVE_BASE_URL`

Secure defaults:

- enabled: false
- countries: empty
- environment: SANDBOX

No `.env` file is created or modified by this module.

## Existing configuration reused

PayPal and Local Bank configuration are read from:

`config/tradex_payments.php`

ADMIN-16 does not change the existing PayPal gateway, bank-deposit service,
payment order model, customer payment-method resolver or payment API routes.

## Country eligibility

Provider and merchant jurisdiction eligibility can change over time.

Therefore ADMIN-16 does not hard-code a permanent legal promise that a provider
is always available in a country.

Before an authorized admin enables a control-plane state:

1. required server configuration must be complete;
2. at least one explicit ISO-2 country must be selected;
3. merchant/provider eligibility must be explicitly confirmed.

## Database

New table:

`integration_provider_states`

Safe fields only:

- provider_code
- is_enabled
- environment
- countries
- eligibility_confirmed
- version
- updated_by_admin_id
- timestamps

No credential or bank account value is stored.

## Concurrency

Provider updates use:

- database transaction
- row lock
- optimistic `version`

Stale update returns HTTP `409`.

## Permissions

Existing permissions reused:

- `integrations.view`
- `integrations.manage`

No permission or role seeder change.

## Audit

Audit action:

`INTEGRATION_PROVIDER_STATE_UPDATED`

Safe audit payload contains:

- provider code
- enabled state
- environment
- approved country codes
- eligibility confirmation flag
- version
- boolean that a reason was provided

It does not copy credential values or free-text change reason.

## Routes

Exactly three routes:

- `GET /admin/integrations`
- `GET /admin/integrations/{providerCode}`
- `PATCH /admin/integrations/{providerCode}`

Allowed provider codes:

- `PAYPAL`
- `STRIPE`
- `NOWPAYMENTS`
- `LOCAL_BANK`

No DELETE route.

## Frozen boundaries

ADMIN-16 does not modify:

- `PaymentOrder`
- `PaymentMethodService`
- PayPal gateway/order/webhook services
- `ManualDepositService`
- payment API controllers/routes
- payment/payout/finance transaction models
- Admin permission/role seeders
- auth/device/security/KYC
- ADMIN-12 Audit implementation
- ADMIN-13 Administration
- ADMIN-14 Settings
- ADMIN-15 Notifications

## Validation

```powershell
php artisan optimize:clear
php artisan migrate
php artisan route:list --name=admin.integrations

php artisan test tests/Feature/Admin/Integrations/IntegrationManagementTest.php
php artisan test

git diff --check
git status --short
```
