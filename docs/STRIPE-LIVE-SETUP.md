# Stripe live setup

TradeX uses Stripe Checkout for card payments and treats only signed webhooks as settlement proof.

## Required production values

Set these values only in the production `.env`; never commit keys or webhook secrets:

```dotenv
TRADEX_STRIPE_ENABLED=true
TRADEX_STRIPE_COUNTRIES=*
TRADEX_STRIPE_ENVIRONMENT=LIVE
TRADEX_STRIPE_PUBLISHABLE_KEY=pk_live_...
TRADEX_STRIPE_SECRET_KEY=sk_live_...
TRADEX_STRIPE_WEBHOOK_SECRET=whsec_...
TRADEX_STRIPE_BASE_URL=https://api.stripe.com
TRADEX_STRIPE_SUCCESS_URL=https://tradex.10x.cloudvideo.online/payments/stripe/success
TRADEX_STRIPE_CANCEL_URL=https://tradex.10x.cloudvideo.online/payments/stripe/cancel
```

Create a Stripe webhook endpoint at:

`https://tradex.10x.cloudvideo.online/api/v1/payments/stripe/webhook`

Subscribe it to these events:

- `checkout.session.completed`
- `checkout.session.async_payment_succeeded`
- `checkout.session.expired`
- `charge.refunded`
- `charge.dispute.created`
- `charge.dispute.closed`

After updating production configuration, run `php artisan config:cache`, then `php artisan tradex:stripe-check --probe`. The probe reads Stripe API status without printing balances or creating a payment. Finally, enable and approve Stripe from the Super Admin Integrations page.
