# API-20 — Push Notification Device Registration + FCM/APNs Delivery

Base main:

`70cad5f2fa3ad2312ff1058145405b1d1bae2e2c`

Feature branch:

`feature/api-20-push-notification-devices`

## Purpose

API-20 adds an external mobile push-delivery layer without reopening the frozen
API-10 in-app notification contract or ADMIN-15 broadcast transaction.

ADMIN-15 remains authoritative for creating `user_notification_receipts`.
API-20 turns eligible current receipts into asynchronous push deliveries.

## Device registration API

Authenticated trusted-mobile routes:

- `GET /api/v1/push/device-token`
- `PUT /api/v1/push/device-token`
- `DELETE /api/v1/push/device-token`

All routes require:

- `auth:sanctum`
- existing `tradex.mobile` middleware
- a trusted, active MOBILE `UserDevice`

Provider is inferred from the frozen trusted-device platform:

- ANDROID -> FCM
- IOS -> APNS

The client cannot claim a different provider/platform in the request body.

## Token security

Raw provider tokens are never returned by API responses or audit events.

Storage uses:

- Laravel encrypted ciphertext for provider delivery;
- HMAC-SHA256 lookup fingerprint using `APP_KEY` material;
- global token-fingerprint uniqueness;
- active-device binding;
- explicit revoke/invalidate state.

Repeated registration of the same exact token is idempotent.

If the same provider token is later proven by another trusted user device, the
single token record is reassigned to the latest proving device. This prevents a
provider token from remaining active for two TradeX users at once.

## Privacy-safe push payload

API-20 deliberately does not send the ADMIN notification title/body to FCM or
APNs.

Lock-screen payload is generic:

- title: `TradeX OS` by default;
- body: `You have a new notification.` by default.

Provider data contains only routing metadata such as receipt UUID,
notification UUID, category and priority.

The mobile app uses the authenticated frozen API-10 notification endpoint to
retrieve actual notification content.

This avoids leaking payment/security/trading content to third-party push
providers or a locked device screen.

## Delivery ledger

New table:

`push_notification_deliveries`

Statuses:

- PENDING
- QUEUED
- SENDING
- RETRYING
- SENT
- INVALID_TOKEN
- FAILED
- SKIPPED

One delivery is unique by:

`user_notification_receipt_id + push_device_token_id`

Provider responses store only safe operational metadata:

- provider type;
- HTTP status;
- provider message ID;
- normalized error code;
- timestamps;
- attempt count.

Raw provider error bodies and credentials are not persisted.

## Fan-out rules

The sweep only considers receipts whose notification is currently:

- active;
- published;
- not expired.

The user must still be ACTIVE and still match the current FREE/PREMIUM audience
when applicable.

The push token must belong to a trusted active MOBILE device.

API-20 does not backfill old inbox notifications to a token registered after the
notification was already published:

`push_device_tokens.registered_at <= notification_messages.published_at`

The in-app inbox remains the historical source of truth.

## Queue and scheduling

Command:

`php artisan tradex:push:sweep`

When `TRADEX_PUSH_ENABLED=true`, API-20 registers the sweep every minute in the
Laravel scheduler.

Production must run the normal Laravel scheduler and an asynchronous queue
worker, for example:

```bash
php artisan queue:work --queue=push-notifications
```

The queue name can be changed with:

`TRADEX_PUSH_QUEUE`

Transient provider failures retry with bounded backoff. Invalid provider tokens
are deactivated automatically.

## FCM HTTP v1

API-20 uses FCM HTTP v1 with a short-lived OAuth 2 access token derived from a
Google service-account credential file.

Environment:

```text
TRADEX_PUSH_ENABLED=true
TRADEX_PUSH_FCM_ENABLED=true
TRADEX_PUSH_FCM_PROJECT_ID=your-firebase-project-id
GOOGLE_APPLICATION_CREDENTIALS=/secure/path/service-account.json
```

Do not place the service-account JSON inside the public web root or commit it to
Git.

Official reference:

https://firebase.google.com/docs/cloud-messaging/send/v1-api

## APNs token authentication

API-20 uses APNs HTTP/2 token-based provider authentication with an ES256 JWT.

Environment:

```text
TRADEX_PUSH_APNS_ENABLED=true
TRADEX_PUSH_APNS_ENVIRONMENT=production
TRADEX_PUSH_APNS_TEAM_ID=YOUR_TEAM_ID
TRADEX_PUSH_APNS_KEY_ID=YOUR_KEY_ID
TRADEX_PUSH_APNS_BUNDLE_ID=com.example.tradex
TRADEX_PUSH_APNS_PRIVATE_KEY_PATH=/secure/path/AuthKey_XXXX.p8
```

Use `sandbox` for development APNs tokens.

Do not commit the `.p8` key.

Official reference:

https://developer.apple.com/documentation/usernotifications/sending-notification-requests-to-apns

## Audit

User token lifecycle actions:

- `PUSH_DEVICE_TOKEN_REGISTERED`
- `PUSH_DEVICE_TOKEN_UNREGISTERED`

Audit excludes raw push tokens and token hashes.

High-volume provider delivery outcomes are recorded in the dedicated delivery
ledger instead of flooding the general audit log.

## Frozen boundaries

API-20 does not modify:

- `NotificationMessage`;
- `UserNotificationReceipt`;
- `NotificationApiService`;
- API-10 notification routes/controllers;
- ADMIN-15 `AdminNotificationService`;
- ADMIN-15 broadcast routes/controllers;
- `UserDevice` or mobile auth middleware;
- ADMIN-14/17/18/19 modules;
- payments, affiliate, KYC or education business logic;
- frozen `routes/api.php`.

The only shared bootstrap change is registration of
`PushNotificationApiServiceProvider`.

## Validation

```powershell
php artisan optimize:clear
php artisan migrate
php artisan route:list --name=api.v1.push
php artisan list --raw | Select-String "tradex:push:sweep"

php artisan test tests/Feature/Api/Push/PushNotificationApiTest.php
php artisan test tests/Feature/Admin/Notifications/NotificationBroadcastManagementTest.php
php artisan test tests/Feature/Api/Notifications
php artisan test

git diff --check origin/main...HEAD
git status --short
```
