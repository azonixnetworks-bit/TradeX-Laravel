# ADMIN-15 — Notifications & Broadcast Management

Base main:

`27efb6c8987ba1ade46b86019e5207cc915f25e9`

Feature branch:

`feature/admin-15-notifications-broadcast-management`

## Purpose

ADMIN-15 activates the existing Admin Notifications skeleton and reuses the
already-frozen API notification foundation.

It does not create a competing notification model.

## Existing frozen foundation reused

- `notification_messages`
- `user_notification_receipts`
- `NotificationMessage`
- `UserNotificationReceipt`
- `NotificationApiService`
- API notification list/show/summary/read routes
- `notifications.view`
- `notifications.create`
- `notifications.send`

ADMIN-15 does not modify those frozen API models, migrations or API services.

## Delivery meaning

In ADMIN-15, "send" means:

> create TradeX in-app `user_notification_receipts`.

It does **not** mean:

- FCM/APNs device push;
- email;
- SMS;
- Telegram;
- WhatsApp.

No external push-provider delivery contract exists in the current notification
foundation, so ADMIN-15 does not pretend one exists.

## Workflow

### Draft

A new `notification_messages` row is created with:

- `published_at = null`
- zero receipts

Drafts can be edited by admins with:

- `notifications.view`
- `notifications.create`

### Publish / Schedule

An admin with:

- `notifications.view`
- `notifications.send`

may dispatch the draft.

Dispatch:

1. locks the message;
2. verifies it is still an undispatched draft;
3. validates the approved action/resource contract;
4. resolves immediate or future `published_at`;
5. verifies expiry is later than publication;
6. selects currently ACTIVE users matching the audience;
7. enforces the configured synchronous fan-out ceiling;
8. inserts receipt rows in chunks;
9. verifies the inserted snapshot count;
10. sets `published_at`;
11. writes a safe audit event.

The transaction rolls back if fan-out cannot complete.

## Audiences

ADMIN-15 deliberately preserves the API-10 contract:

- `ALL`
- `FREE`
- `PREMIUM`

No arbitrary individual-user targeting is added in ADMIN-15.

Recipient receipt candidates are ACTIVE, non-deleted users.

For FREE/PREMIUM messages, the frozen mobile API continues to re-check the
user's **current** access level when the inbox is read.

## Scheduling

Future scheduling does not need a scheduler process.

Receipts are created during the admin dispatch transaction and the frozen
mobile API already hides messages until:

`published_at <= now()`

This keeps scheduling deterministic and consistent with API-10.

## Approved actions

ADMIN-15 reuses existing `NotificationMessage::ACTION_TYPES`.

No arbitrary URL is accepted.

Resource UUID is required only for:

- `EDUCATION_COURSE`
- `EDUCATION_LESSON`
- `TRADE_IDEA`
- `OUR_CHANNEL_VIDEO`

The referenced resource must exist.

Non-resource actions reject a supplied resource UUID.

## Immutability

Once a message has `published_at` or receipt rows:

- it cannot be edited;
- it cannot be dispatched again;
- it cannot be deleted;
- it cannot be recalled from ADMIN-15.

No DELETE route exists.

This prevents silent alteration of content after user receipt creation.

## Recipient scale boundary

Default synchronous ceiling:

`50,000` recipients per broadcast.

Config:

`config/tradex_admin_notifications.php`

Environment override:

`TRADEX_ADMIN_NOTIFICATION_MAX_RECIPIENTS`

This is a safety ceiling, not a business maximum. Increase it only after
production database/load testing. A future external push fan-out subsystem can
be queue-backed without changing the frozen in-app API contract.

## Audit

Actions:

- `ADMIN_NOTIFICATION_DRAFT_CREATED`
- `ADMIN_NOTIFICATION_DRAFT_UPDATED`
- `ADMIN_NOTIFICATION_BROADCAST_SENT`
- `ADMIN_NOTIFICATION_BROADCAST_SCHEDULED`

Audit payload deliberately excludes:

- notification title;
- notification body;
- user IDs;
- action resource UUID.

Only safe metadata, recipient count and publication state/timestamps are
recorded.

## Routes

- `GET /admin/notifications`
- `GET /admin/notifications/create`
- `POST /admin/notifications`
- `GET /admin/notifications/{notificationMessage}/edit`
- `PATCH /admin/notifications/{notificationMessage}`
- `POST /admin/notifications/{notificationMessage}/send`
- `GET /admin/notifications/{notificationMessage}`

Seven routes. No DELETE route.

## Database changes

None.

The existing API-10 schema is sufficient.

## Frozen boundaries

ADMIN-15 does not modify:

- `NotificationMessage`;
- `UserNotificationReceipt`;
- `NotificationApiService`;
- API notification controllers/requests/routes;
- notification API migrations;
- User model;
- auth/device/security modules;
- Affiliate/Payments/Payouts/Finance/KYC;
- ADMIN-12 Audit implementation;
- ADMIN-13 Administration;
- ADMIN-14 Settings.

## Validation

```bash
php artisan optimize:clear
php artisan route:list --name=admin.notifications
php artisan test tests/Feature/Admin/Notifications/NotificationBroadcastManagementTest.php
php artisan test
git diff --check
git status --short
```
