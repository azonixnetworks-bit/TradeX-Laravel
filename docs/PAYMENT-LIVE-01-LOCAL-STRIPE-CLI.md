# PAYMENT-LIVE-01 — Local Stripe CLI Sandbox Check

This local-only workflow exists to prove Stripe Checkout and the signed webhook flow before a public staging deployment is available.

## Local browser return URLs

For local Stripe sandbox checkout only, configure the ignored local `.env` with:

```dotenv
TRADEX_STRIPE_SUCCESS_URL=http://127.0.0.1:8000/payments/stripe/success
TRADEX_STRIPE_CANCEL_URL=http://127.0.0.1:8000/payments/stripe/cancel
```

These browser return pages are UX only. They never clear a payment or activate premium access. The Stripe-signed webhook remains the settlement authority.

## Local webhook forwarding

Keep Laravel running on `127.0.0.1:8000`, then run:

```powershell
stripe listen --forward-to http://127.0.0.1:8000/api/v1/payments/stripe/webhook
```

Copy the CLI-generated signing secret only into the ignored local `.env` as `TRADEX_STRIPE_WEBHOOK_SECRET`. Never commit or paste it into chat, issues, screenshots, logs, or admin pages.

## Readiness command note

`php artisan tradex:payment-live-check` is a deployment-readiness gate. It intentionally rejects localhost/private callback URLs, so it can fail during this local CLI workflow even while the actual sandbox integration is working correctly.

Public staging and production deployments must use HTTPS return URLs and a publicly reachable provider webhook endpoint.
