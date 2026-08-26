# API-21 — Stripe + NOWPayments Transaction Extension

## Status

API-21 extends the existing API-12 payment order/history/entitlement model with two online transaction adapters:

- Stripe hosted Checkout Sessions
- NOWPayments hosted Invoices + signed IPN callbacks

API-21 does not replace or duplicate the existing PayPal, Local Bank, or manual USDT TRC20 flows.

## Frozen boundaries

The following existing contracts remain authoritative and are not rewritten by API-21:

- PayPal order/capture/webhook settlement
- Local Bank manual deposit/reconciliation
- Manual USDT TRC20 deposit/reconciliation
- API-09 PayPal-based affiliate commercial settlement
- Existing payment reversal/no-refund exception policy

API-21 uses a separate `OnlineGatewaySettlementService` for Stripe/NOWPayments so the frozen PayPal settlement and affiliate runtime remain unchanged.

## Runtime approval gate

Stripe and NOWPayments are available to a user only when all of the following are true:

1. The source integration is enabled in server configuration.
2. The user's country is in the server-side country allow-list.
3. Required provider credentials/runtime URLs are configured.
4. ADMIN-16 has an enabled `IntegrationProviderState` for that provider.
5. ADMIN-16 eligibility confirmation is true.
6. The user's country is in the ADMIN-16 approved country list.
7. The ADMIN-16 environment matches the source runtime environment.

This is intentionally fail-closed.

## Endpoints

### Trusted mobile user endpoints

- `POST /api/v1/payments/stripe/checkout-sessions`
- `POST /api/v1/payments/nowpayments/invoices`

Both require:

- Sanctum authentication
- trusted TradeX mobile session
- existing API payment create rate limit
- `Idempotency-Key` header

### Provider-to-server endpoints

- `POST /api/v1/payments/stripe/webhook`
- `POST /api/v1/payments/nowpayments/webhook`

These endpoints do not use user authentication. Provider authenticity is verified cryptographically before settlement state is changed.

## Stripe flow

1. TradeX creates a local `PaymentOrder`.
2. TradeX creates a Stripe Checkout Session server-side.
3. The Checkout Session ID is stored as the provider order reference.
4. The hosted Checkout URL is stored encrypted using the existing `approval_url` model cast.
5. The client opens the hosted Stripe page.
6. A success redirect is informational only and never activates premium access.
7. Stripe sends a signed webhook.
8. TradeX verifies the raw request body using `Stripe-Signature`, the configured webhook secret, and a bounded timestamp tolerance.
9. Only a paid `checkout.session.completed` or `checkout.session.async_payment_succeeded` event is eligible for settlement.
10. Checkout session reference, TradeX order UUID, currency, and exact gross amount must match the local order.
11. The provider payment reference is the Stripe PaymentIntent ID.
12. The payment is cleared idempotently and premium entitlement is activated.

## NOWPayments flow

1. TradeX creates a local `PaymentOrder`.
2. TradeX creates a hosted NOWPayments Invoice using `/v1/invoice`.
3. The invoice ID is stored as the provider order reference.
4. The hosted invoice URL is stored encrypted using the existing `approval_url` field.
5. NOWPayments sends IPN callbacks to the API-21 webhook.
6. TradeX recursively sorts IPN keys, JSON-encodes the canonical structure, computes HMAC-SHA512 using the configured IPN secret, and compares it with `x-nowpayments-sig`.
7. Only `finished` is treated as a cleared payment.
8. `partially_paid` remains `PROCESSING`, is flagged for review, and never activates premium access.
9. Waiting/confirming/confirmed/sending/wrong-asset-confirmed states remain processing states.
10. Failed/expired/cancelled terminal states mark an uncleared order failed.
11. Price currency and exact price amount must match the TradeX order before settlement.

## Webhook idempotency

Stripe uses the provider event ID in the existing `payment_webhook_events` ledger.

NOWPayments can send multiple status transitions for one payment ID, so API-21 derives an event key from:

- payment ID
- payment status
- payload SHA-256 prefix

Exact duplicate callbacks are idempotent while distinct status transitions remain auditable.

## Settlement and entitlement

`OnlineGatewaySettlementService`:

- supports only Stripe and NOWPayments
- provider-scopes the provider payment reference fingerprint
- requires exact order/provider/currency/gross amount match
- prevents a payment reference from being attached to different TradeX payment data
- prevents one TradeX order from being settled by two different payment references
- marks the order `CLEARED`
- creates the generic `payments` ledger entry
- activates the user's premium account state
- upserts the existing `UserEntitlement`
- records safe payment and entitlement audit events

Provider network calls happen before/after local DB transactions as appropriate; no provider HTTP request is held inside a DB row-lock transaction.

## Affiliate boundary

API-09's frozen commercial engine currently records cleared affiliate sales through the PayPal-specific source contract and calculates commission from PayPal Net Eligible Revenue.

API-21 does not silently redefine that business rule for Stripe/NOWPayments because processor fee/net eligible revenue normalization has not been frozen for those providers.

Therefore API-21 Stripe/NOWPayments payments currently record:

- `processor_fee_minor = null`
- `net_eligible_revenue_minor = null`
- `affiliate_processing_status = NOT_APPLICABLE`

A future explicit multi-provider affiliate compatibility module may extend this after its fee/net-revenue policy is approved.

## Manual USDT boundary

NOWPayments is an online crypto payment gateway. It does not replace or duplicate the existing API-12 manual `USDT_TRC20` deposit flow.

The existing manual USDT endpoint remains:

- `POST /api/v1/payments/usdt-deposits`

## Required environment — Stripe

```env
TRADEX_STRIPE_ENABLED=false
TRADEX_STRIPE_COUNTRIES=LK,IN
TRADEX_STRIPE_ENVIRONMENT=SANDBOX
TRADEX_STRIPE_PUBLISHABLE_KEY=
TRADEX_STRIPE_SECRET_KEY=
TRADEX_STRIPE_WEBHOOK_SECRET=
TRADEX_STRIPE_BASE_URL=https://api.stripe.com
TRADEX_STRIPE_SUCCESS_URL=
TRADEX_STRIPE_CANCEL_URL=
TRADEX_STRIPE_TIMEOUT_SECONDS=15
TRADEX_STRIPE_WEBHOOK_TOLERANCE_SECONDS=300
```

The Stripe dashboard webhook endpoint must point to:

`/api/v1/payments/stripe/webhook`

## Required environment — NOWPayments

```env
TRADEX_NOWPAYMENTS_ENABLED=false
TRADEX_NOWPAYMENTS_COUNTRIES=LK,IN
TRADEX_NOWPAYMENTS_ENVIRONMENT=SANDBOX
TRADEX_NOWPAYMENTS_API_KEY=
TRADEX_NOWPAYMENTS_IPN_SECRET=
TRADEX_NOWPAYMENTS_SANDBOX_BASE_URL=
TRADEX_NOWPAYMENTS_LIVE_BASE_URL=https://api.nowpayments.io
TRADEX_NOWPAYMENTS_SUCCESS_URL=
TRADEX_NOWPAYMENTS_CANCEL_URL=
TRADEX_NOWPAYMENTS_TIMEOUT_SECONDS=15
```

Sandbox base URL is intentionally not guessed. It must be explicitly configured when SANDBOX is selected.

## Default safety state

Both new transaction adapters are disabled by default. Existing API-12 payment methods continue to behave according to their existing configuration.

## Test coverage

`tests/Feature/Api/Payments/StripeNowPaymentsTransactionApiTest.php` covers:

- authentication requirement for create endpoints
- runtime/admin approval gate
- Stripe hosted checkout creation and idempotency
- Stripe signed paid webhook settlement
- Stripe invalid signature rejection
- NOWPayments hosted invoice creation and idempotency
- NOWPayments signed `finished` settlement
- NOWPayments `partially_paid` no-entitlement rule
- NOWPayments invalid signature rejection

Real provider credentials are not required for this test file; provider creation calls use Laravel HTTP fakes and webhook signatures are generated locally in the test.
