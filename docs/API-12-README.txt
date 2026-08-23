TradeX Laravel — API-12 Payments & Billing Mobile API
====================================================

What API-12 does
----------------
Creates the first real TradeX payment ledger and mobile payment API.

Before API-12 the repository had placeholder payment model classes but no
payment tables.

API-12 provides:
- country-aware payment methods
- PayPal Checkout order creation
- PayPal capture settlement
- cryptographically verified PayPal webhook processing
- Local Bank proof submission
- USDT TRC20 transaction submission
- payment history
- premium activation status
- idempotency
- payment-integrity reversal ledger
- automatic AFF-01 PayPal-net affiliate integration

What API-12 intentionally does NOT do
-------------------------------------
- no voluntary refund workflow
- no user/admin refund endpoint
- no payout module
- no user self-approval of Bank/USDT payments
- no automatic manual-payment reconciliation
- no automatic entitlement revocation after forced processor reversal
- no storage of complete PayPal webhook payloads
- no public payment-proof storage

Manual Bank/USDT approval is a future authorized Finance/Admin module.

Install
-------
1. Current branch:
   feature/api-12-payments-billing

2. Extract this ZIP into:
   G:\TradeX-Laravel-Project\TradeX-Lrvl-V1.00.00

3. Configure test/dev payment values in .env if needed.

4. Run:
   php artisan optimize:clear
   php artisan migrate
   php artisan route:list --name=api.v1.payments
   php artisan test --filter=Payment
   php artisan test
   git diff --check
   git status

Important
---------
Do NOT add real PayPal client secrets or merchant credentials to Git.

PayPal sandbox must be used for development/testing.

New user routes
---------------
GET  /api/v1/payments/methods
GET  /api/v1/payments/activation-status
GET  /api/v1/payments/history
GET  /api/v1/payments/orders/{paymentOrder}
POST /api/v1/payments/paypal/orders
POST /api/v1/payments/paypal/orders/{paymentOrder}/capture
POST /api/v1/payments/bank-deposits
POST /api/v1/payments/usdt-deposits

Provider route
--------------
POST /api/v1/payments/paypal/webhook

New migrations
--------------
2026_08_23_220000_create_payment_orders_table.php
2026_08_23_220100_create_payments_table.php
2026_08_23_220200_create_payment_adjustments_table.php
2026_08_23_220300_create_bank_deposits_table.php
2026_08_23_220400_create_usdt_deposits_table.php
2026_08_23_220500_create_user_entitlements_table.php
2026_08_23_220600_create_payment_webhook_events_table.php

Recommended local test configuration
------------------------------------
PayPal sandbox:
TRADEX_PAYPAL_ENABLED=true
TRADEX_PAYPAL_ENVIRONMENT=sandbox
TRADEX_PAYPAL_CLIENT_ID=<sandbox-client-id>
TRADEX_PAYPAL_CLIENT_SECRET=<sandbox-secret>
TRADEX_PAYPAL_WEBHOOK_ID=<sandbox-webhook-id>

Sri Lanka Local Bank requires:
TRADEX_EDUCATION_PRICE_LKR_MINOR=<configured-LKR-minor-units>
TRADEX_LOCAL_BANK_NAME=<merchant-bank>
TRADEX_LOCAL_BANK_BRANCH=<merchant-branch>
TRADEX_LOCAL_BANK_ACCOUNT_HOLDER=<merchant-holder>
TRADEX_LOCAL_BANK_ACCOUNT_NUMBER=<merchant-account>

India USDT requires:
TRADEX_USDT_TRC20_WALLET=<TradeX-TRC20-wallet>

Automated package validation only checks PHP syntax/static contracts.
Full Laravel regression must be run in the complete local TradeX repository.
