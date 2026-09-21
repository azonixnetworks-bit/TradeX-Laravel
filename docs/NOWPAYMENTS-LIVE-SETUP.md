# NOWPayments live setup

TradeX creates a hosted NOWPayments invoice and activates the purchased entitlement only after a signed `finished` IPN callback matches the original order amount and currency.

The NOWPayments dashboard may advertise zero-fee payouts to ChangeNOW Pro. That is a payout option within the same NOWPayments platform; it does not replace the NOWPayments Payments API used for customer invoices and does not change the official `https://api.nowpayments.io` payment endpoint.

## 1. Prepare the NOWPayments merchant account

1. Complete the merchant account setup and add the settlement wallet(s).
2. Generate a production API key.
3. Generate an IPN secret in the NOWPayments payment settings. Store it immediately because the dashboard may show it only once.
4. Keep both values only in the production server environment. Never commit them to Git or paste them into an admin form.

## 2. Configure production

Add the following values to the production `.env` file:

```env
TRADEX_NOWPAYMENTS_ENABLED=true
TRADEX_NOWPAYMENTS_COUNTRIES=*
TRADEX_NOWPAYMENTS_ENVIRONMENT=LIVE
TRADEX_NOWPAYMENTS_API_KEY=replace-on-server
TRADEX_NOWPAYMENTS_IPN_SECRET=replace-on-server
TRADEX_NOWPAYMENTS_SANDBOX_BASE_URL=https://api-sandbox.nowpayments.io
TRADEX_NOWPAYMENTS_LIVE_BASE_URL=https://api.nowpayments.io
TRADEX_NOWPAYMENTS_SUCCESS_URL=https://tradex.10x.cloudvideo.online/payments/success
TRADEX_NOWPAYMENTS_CANCEL_URL=https://tradex.10x.cloudvideo.online/payments/cancel
TRADEX_NOWPAYMENTS_TIMEOUT_SECONDS=15
```

`TRADEX_NOWPAYMENTS_COUNTRIES=*` enables the provider for all customer countries after the Super Admin approves the same All Countries scope. Provider eligibility and applicable restrictions still remain the merchant's responsibility.

The application sends each invoice callback URL as:

`https://tradex.10x.cloudvideo.online/api/v1/payments/nowpayments/webhook`

The webhook is public because NOWPayments calls it directly. Every payload must pass HMAC SHA-512 verification with `X-NOWPAYMENTS-SIG` before any payment state changes.

## 3. Validate without creating a payment

After editing `.env`:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan tradex:nowpayments-check
php artisan tradex:nowpayments-check --probe
```

The first check stays local. `--probe` performs one read-only request to NOWPayments `/v1/status`; it does not create an invoice or move funds.

## 4. Approve the runtime

In **Admin → Integrations → NOWPayments**, set:

- Control: enabled
- Environment: LIVE
- Countries: the same country list configured in `.env`
- Eligibility: confirmed

The customer payment method stays unavailable when the server configuration and Super Admin approval differ.

## 5. Live smoke test

Use a dedicated test customer and the smallest valid invoice supported by the selected asset. Confirm:

1. invoice creation returns a `nowpayments.io` hosted checkout URL;
2. pending statuses do not activate the entitlement;
3. only a signed `finished` callback with the exact order price and currency clears the order;
4. the admin NOWPayments ledger shows the provider reference without exposing secrets;
5. repeating the same callback does not create a second payment.

Sending crypto is a real financial transaction. Perform that final test only after reviewing the displayed asset, network, address and amount.

## Rollback

Set `TRADEX_NOWPAYMENTS_ENABLED=false`, run `php artisan config:cache`, and disable NOWPayments in Admin Integrations. Existing ledger records remain available for audit.
