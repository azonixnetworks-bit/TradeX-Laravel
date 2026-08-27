# PAYMENT-LIVE-01 — Stripe + NOWPayments Real Integration

## Purpose

TradeX API-21 already contains real server-to-provider transaction adapters for Stripe Checkout and NOWPayments invoices, plus signed provider callback processing. PAYMENT-LIVE-01 hardens the operational path from sandbox credentials to production activation without storing payment secrets in source control or the admin UI.

## Frozen payment authority

- Stripe customer checkout is created server-side by TradeX and redirected to Stripe-hosted Checkout.
- Stripe settlement authority is a cryptographically verified Stripe webhook. An admin cannot manually clear a Stripe payment.
- NOWPayments invoices are created server-side by TradeX.
- NOWPayments settlement authority is a cryptographically verified IPN callback. An admin cannot manually clear a NOWPayments payment.
- NOWPayments `finished` is the only status that clears the order and activates premium.
- NOWPayments `partially_paid` must never activate premium.
- Existing PayPal, Local Bank and manual Binance / USDT TRC20 boundaries remain unchanged.
- Stripe/NOWPayments affiliate processing remains `NOT_APPLICABLE` until an explicit processor-fee/net-revenue policy is approved.

## Secret-handling rule

Never place any of the following in Git, committed `.env` examples, issue trackers, chat, screenshots, application logs, or browser-rendered admin pages:

- Stripe secret key
- Stripe webhook signing secret
- NOWPayments API key
- NOWPayments IPN secret

Real values belong only in the developer's ignored local `.env` for sandbox testing and in the production hosting secret/environment store for deployment.

## Public HTTPS requirement

Provider callbacks cannot use `tradex.local`, `localhost`, plain HTTP, or a private LAN IP.

Use a public HTTPS staging hostname before sandbox end-to-end testing, for example:

- Stripe webhook: `https://<staging-host>/api/v1/payments/stripe/webhook`
- NOWPayments IPN: `https://<staging-host>/api/v1/payments/nowpayments/webhook`

Stripe and NOWPayments success/cancel URLs must also be public HTTPS URLs.

## Stripe sandbox/test setup

1. Open the Stripe Dashboard in test mode.
2. Obtain the test publishable key and test secret key.
3. Create a webhook endpoint pointing to the TradeX Stripe webhook URL.
4. Subscribe the endpoint to the events consumed by the current TradeX runtime:
   - `checkout.session.completed`
   - `checkout.session.async_payment_succeeded`
5. Copy the endpoint signing secret into the private deployment/local environment.
6. Configure public HTTPS success and cancel pages.
7. Keep `TRADEX_STRIPE_BASE_URL=https://api.stripe.com`.
8. Run the TradeX readiness command before attempting an end-to-end payment.

Sandbox environment contract:

```dotenv
TRADEX_STRIPE_ENABLED=true
TRADEX_STRIPE_COUNTRIES=LK,IN
TRADEX_STRIPE_ENVIRONMENT=SANDBOX
TRADEX_STRIPE_PUBLISHABLE_KEY=<test publishable key>
TRADEX_STRIPE_SECRET_KEY=<test secret key>
TRADEX_STRIPE_WEBHOOK_SECRET=<test endpoint signing secret>
TRADEX_STRIPE_BASE_URL=https://api.stripe.com
TRADEX_STRIPE_SUCCESS_URL=https://<staging-host>/payments/success
TRADEX_STRIPE_CANCEL_URL=https://<staging-host>/payments/cancel
TRADEX_STRIPE_TIMEOUT_SECONDS=15
TRADEX_STRIPE_WEBHOOK_TOLERANCE_SECONDS=300
```

## NOWPayments sandbox setup

1. Create/sign in to the NOWPayments merchant account.
2. Configure the required outcome wallet in Store Settings.
3. Generate an API key in Store Settings.
4. Generate the IPN secret and store it securely; treat it as a one-time-view secret.
5. Use the sandbox API base URL supplied by the current NOWPayments sandbox/account documentation. Do not guess or copy a production base URL into sandbox configuration.
6. Configure public HTTPS TradeX success/cancel URLs.
7. TradeX sends its IPN callback URL when the invoice is created:
   `https://<staging-host>/api/v1/payments/nowpayments/webhook`
8. Run the TradeX readiness command before attempting an end-to-end invoice.

Sandbox environment contract:

```dotenv
TRADEX_NOWPAYMENTS_ENABLED=true
TRADEX_NOWPAYMENTS_COUNTRIES=LK,IN
TRADEX_NOWPAYMENTS_ENVIRONMENT=SANDBOX
TRADEX_NOWPAYMENTS_API_KEY=<sandbox API key>
TRADEX_NOWPAYMENTS_IPN_SECRET=<sandbox IPN secret>
TRADEX_NOWPAYMENTS_SANDBOX_BASE_URL=<official sandbox HTTPS base URL>
TRADEX_NOWPAYMENTS_LIVE_BASE_URL=https://api.nowpayments.io
TRADEX_NOWPAYMENTS_SUCCESS_URL=https://<staging-host>/payments/success
TRADEX_NOWPAYMENTS_CANCEL_URL=https://<staging-host>/payments/cancel
TRADEX_NOWPAYMENTS_TIMEOUT_SECONDS=15
```

## Readiness checks

Sandbox or configured environment:

```bash
php artisan tradex:payment-live-check
```

Production-only gate:

```bash
php artisan tradex:payment-live-check --production
```

The command intentionally prints only configuration status. It never prints provider credential values.

The production command must not pass on a normal local/test environment. Before live activation it requires, among other payment-specific checks:

- both providers enabled as intended
- both environments set to `LIVE`
- live Stripe key prefixes
- Stripe webhook signing secret configured
- live NOWPayments API key and IPN secret configured
- production provider endpoints
- public HTTPS callback/success/cancel URLs
- `APP_ENV=production`
- `APP_DEBUG=false`

## Sandbox end-to-end acceptance

### Stripe

1. Create a premium payment from an authenticated trusted TradeX mobile session.
2. Confirm TradeX creates exactly one Stripe Checkout Session for one idempotency key.
3. Complete the payment through Stripe test Checkout.
4. Confirm Stripe sends a signed webhook to TradeX.
5. Confirm TradeX verifies the signature, order UUID, checkout session reference, currency and exact gross amount.
6. Confirm exactly one Payment record is cleared and exactly one premium entitlement becomes active.
7. Replay the same webhook and confirm no duplicate payment or entitlement is created.

### NOWPayments

1. Create a premium NOWPayments invoice from an authenticated trusted TradeX mobile session.
2. Confirm one idempotency key creates exactly one TradeX order/invoice.
3. Complete a sandbox payment according to NOWPayments sandbox instructions.
4. Confirm signed IPN callbacks reach TradeX.
5. Confirm non-final statuses remain non-entitling.
6. Confirm `partially_paid` does not activate premium.
7. Confirm only a valid signed `finished` callback with exact order/currency/amount clears the payment and activates premium.
8. Replay callbacks and confirm settlement remains idempotent.

## Production switch

Do not switch to LIVE until sandbox end-to-end acceptance is complete and the public production domain/HTTPS deployment is ready.

Production changes are environment-only; real credentials are never committed:

```dotenv
TRADEX_STRIPE_ENVIRONMENT=LIVE
TRADEX_NOWPAYMENTS_ENVIRONMENT=LIVE
```

Replace sandbox credentials with the provider's live credentials in the production secret store, register the production Stripe webhook endpoint, verify the production NOWPayments IPN secret/configuration, and then run:

```bash
php artisan optimize:clear
php artisan tradex:payment-live-check --production
```

A live payment must not be accepted as release-ready until this command passes and one controlled production smoke transaction has been reconciled end-to-end.
