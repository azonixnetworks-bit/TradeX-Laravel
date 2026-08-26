# ADMIN-14 — System Settings Management

Base main:

`763c50fdee77b9a3f6be72ba1bad2722ddbd319f`

Feature branch:

`feature/admin-14-system-settings-management`

## Purpose

ADMIN-14 activates the existing Settings skeleton without turning the admin website into an `.env` editor.

It adds:

- a narrowly allowlisted DB-backed General Settings registry;
- read-only Affiliate ownership/status;
- read-only Payments readiness;
- read-only Security runtime posture;
- read-only Video/HLS processing summary.

## RBAC

Existing permissions are reused:

- `settings.view`
- `settings.manage`

No permission or role seeder change is required.

`settings.view` can read the Settings pages.

Updating General Settings requires both:

- `settings.view`
- `settings.manage`

## New system_settings table

The new table stores only safe allowlisted values:

- setting key;
- group;
- normalized value;
- value type;
- optimistic-concurrency version;
- last updating admin ID;
- timestamps.

It is not a generic secret vault.

## General allowlist

ADMIN-14 permits only:

- `general.platform_name`
- `general.support_email`
- `general.support_whatsapp`
- `general.maintenance_notice_enabled`
- `general.maintenance_notice`

The registry is centrally available, but another frontend/API module consumes a value only after that owning module is explicitly wired to it.

ADMIN-14 does not silently override frozen business modules.

## Optimistic concurrency

Every General field includes a version number.

The update transaction:

1. creates missing allowlisted rows at default version zero;
2. locks all General rows;
3. compares submitted versions with current versions;
4. rejects stale updates with HTTP 409;
5. changes only allowlisted rows;
6. increments versions only for changed rows;
7. records the updating admin;
8. writes a safe audit event.

This prevents one admin browser session from silently overwriting a newer settings edit from another session.

## Audit

Action:

`SYSTEM_SETTINGS_UPDATED`

Audit payload contains only:

- changed setting keys;
- resulting versions.

It deliberately does not copy:

- support email;
- support WhatsApp;
- maintenance notice content;
- any secret or credential.

## Explicitly forbidden from the generic Settings UI

ADMIN-14 does not render or edit:

- `.env`;
- `APP_KEY`;
- database credentials;
- mail/SMTP credentials;
- PayPal client ID/secret/webhook secret data;
- local-bank account number;
- USDT wallet address;
- KYC identity hash/HMAC key;
- TOTP/OTP secrets;
- session/device keys;
- private storage paths;
- FFmpeg/FFprobe binary paths;
- private media working directory;
- Finance owner-report email;
- payment reversal/refund policy;
- affiliate commission/stage business rules.

## Ownership tabs

### Affiliate

Read-only. Commercial configuration remains owned by frozen ADMIN-06.

### Payments

Read-only provider enablement/countries/readiness booleans only.

Credentials, bank destination details and wallet addresses never render.

Payment operations remain owned by frozen ADMIN-07.

### Security

Read-only safe runtime posture.

Security operations remain owned by frozen ADMIN-11.

### Video

Read-only queue/upload/HLS rendition summary.

Binary paths/private directories do not render.

Video operations remain owned by Education/Video modules.

## Routes

- `GET /admin/settings`
- `GET /admin/settings/general`
- `PATCH /admin/settings/general`
- `GET /admin/settings/affiliate`
- `GET /admin/settings/payments`
- `GET /admin/settings/security`
- `GET /admin/settings/video`

Only one mutation route exists: General Settings PATCH.

## Frozen boundaries

ADMIN-14 does not modify:

- Affiliate models/services/config;
- Payment/Payout/Finance models/services/config;
- Security/auth/device/KYC models/services/config;
- Education/Video models/services/config;
- existing admin permission/role seeders;
- ADMIN-12 Audit implementation;
- ADMIN-13 Administration implementation.

SystemSetting was an unused skeleton before ADMIN-14 and is intentionally implemented in this module.

## Validation

```bash
php artisan optimize:clear
php artisan migrate
php artisan route:list --name=admin.settings
php artisan test tests/Feature/Admin/Settings/SystemSettingsManagementTest.php
php artisan test
git diff --check
git status --short
```
