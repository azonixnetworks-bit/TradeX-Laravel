# ADMIN-12 — Audit Log Management

Base main:

`47a2031737901db0e7ea29728553f3667c78719e`

Feature branch:

`feature/admin-12-audit-log-management`

## Purpose

ADMIN-12 provides an immutable read-only audit explorer over the existing `audit_logs` table.

It does not change existing audit writers, audit schema, business modules, authentication flows, payment/KYC/security logic, or stored historical rows.

## Existing foundation reused

The existing audit table already stores actor type/ID, action, module, target type/ID, old/new JSON values, IP address, user agent and created timestamp. Existing indexes cover actor, target, action, module and created time.

No migration is required.

## RBAC

Existing permission: `audit.view`.

No new permission or role-seeder change is introduced.

## Routes

- `GET /admin/audit`
- `GET /admin/audit/{auditLog}`

Both require `admin.auth`; the controller additionally requires `audit.view`.

## List behavior

- total and last-24-hour stats;
- ADMIN / USER / SYSTEM counts;
- metadata search;
- actor type / actor ID filters;
- action/module filters;
- target type / target ID filters;
- date range;
- pagination.

The list never renders `old_values` or `new_values`.

## Detail behavior

Detail resolves the actor where possible and shows sanitized old/new payloads. Opening a detail appends `AUDIT_LOG_DETAIL_ACCESSED` with only safe metadata; the inspected row is never modified.

## Render-time redaction

Raw JSON is never directly rendered. Sensitive-looking keys are recursively redacted, nesting depth and item counts are bounded, strings are length-limited, and unsupported values are replaced with a safe marker.

Sensitive-key families include passwords/passcodes, secrets/API keys, OTP/TOTP/recovery credentials, tokens, authorization/cookies, hashes/HMAC/signatures, device keys, identity/account/card numbers, wallet addresses, provider/capture references, destination snapshots, storage paths and original private filenames.

## Security headers

Audit pages send no-store/no-cache, nosniff, no-referrer, DENY framing and no-index headers.

## No mutation routes

ADMIN-12 adds no edit/delete/purge/restore/raw-export endpoint. Any future retention/purge policy must be a separately designed compliance subsystem.

## Frozen boundaries

ADMIN-12 does not modify the `AuditLog` model, audit migration, permission/role seeders, existing audit writers, ADMIN-11 Security, auth/device/KYC/payment/payout/finance modules.

## Validation

```bash
php artisan optimize:clear
php artisan route:list --name=admin.audit
php artisan test tests/Feature/Admin/Audit/AuditLogManagementTest.php
php artisan test
git diff --check
git status --short
```
