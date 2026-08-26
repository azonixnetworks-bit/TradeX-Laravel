# ADMIN-11 — Security Management

Base main:

`531698da966ac8f090c6cf2149c306df6489e1ff`

Feature branch:

`feature/admin-11-security-management`

## Purpose

ADMIN-11 creates a centralized read-only security operations surface over security controls that already exist in TradeX.

It deliberately does not duplicate the frozen User Management device/session mutation workflow.

## Existing frozen security foundations reused

- `user_devices`
- `user_refresh_tokens`
- device trust and 1 Mobile + 1 Desktop slot policy
- per-user device revoke
- per-user device-slot reset
- per-user refresh-session revoke
- TOTP fields and authentication flows
- `audit_logs`
- private TradeX filesystem disks
- KYC private storage
- Laravel session security configuration

No database migration is required.

## Pages

### Security Events

Permission:

`security.events`

Shows a curated security-relevant subset of `audit_logs`.

Examples include:

- device/session security;
- auth/login/logout;
- password/OTP/TOTP;
- quick unlock/social-auth security;
- KYC security/compliance events;
- sensitive-access events.

It never renders `old_values` or `new_values`.

The full generic audit-log explorer remains a separate future Audit module.

### Sessions & Devices

Permission:

`security.view`

Shows a global read-only inventory of refresh sessions joined to users and trusted devices.

Session states:

- `ACTIVE`
- `EXPIRED`
- `REVOKED`

The page never displays:

- refresh token hashes;
- token family UUIDs;
- installation ID hashes;
- device public keys;
- session/device revoke reasons.

Where the admin also has `users.view_details`, the page links to the existing User Security detail where the already-frozen revoke/reset/sign-out actions live.

ADMIN-11 adds no session/device mutation endpoint.

### Protection Posture

Permission:

`security.view`

Read-only checks include:

- protected private filesystem posture;
- KYC private-storage posture;
- session HttpOnly;
- session SameSite;
- secure-cookie runtime state;
- JSON session serialization;
- server-side session driver;
- APP_DEBUG exposure;
- centralized content-protection config presence.

`PASS` means only that the Laravel runtime configuration inspected by the check is aligned with that named control.

It is not a certification of:

- reverse proxy / TLS termination;
- CDN;
- OS;
- database;
- cloud IAM;
- network firewall;
- container/runtime hardening.

## RBAC

Existing permissions are reused:

- `security.view`
- `security.events`
- `security.manage`

`security.manage` is intentionally not used for writes in ADMIN-11.

It remains reserved for a future explicitly designed security-settings or emergency-control workflow.

No role or permission seeder changes are required.

## Routes

- `GET /admin/security`
- `GET /admin/security/events`
- `GET /admin/security/sessions`
- `GET /admin/security/content-protection`

All are protected by `admin.auth`.

Read endpoints are rate-limited.

No POST/PATCH/PUT/DELETE route is introduced.

## Frozen ownership boundaries

ADMIN-11 does not change:

- `UserDevice`
- `UserRefreshToken`
- user device/session services/controllers;
- login/TOTP/OTP/quick-unlock/social-auth flows;
- KYC models/services;
- payment/payout/finance modules;
- security migrations;
- audit schema.

## Validation

```bash
php artisan optimize:clear
php artisan route:list --name=admin.security
php artisan test tests/Feature/Admin/Security/SecurityManagementTest.php
php artisan test
git diff --check
git status --short
```
