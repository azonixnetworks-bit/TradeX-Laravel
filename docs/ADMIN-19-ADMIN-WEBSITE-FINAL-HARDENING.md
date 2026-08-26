# ADMIN-19 — Admin Website Final Hardening & Release Freeze

Base main:

`0c1f916fb9fa4e371ce37b1a9154bf35357b631d`

Feature branch:

`feature/admin-19-admin-website-final-hardening`

## Purpose

ADMIN-19 closes cross-cutting security gaps before the TradeX Admin Website is
eligible for a final release freeze.

The repository inspection found that password login already had:

- per-email/IP rate limiting;
- failed-attempt account locking;
- disabled/locked-account checks;
- session ID regeneration after password login;
- logout session invalidation;
- audit events.

However, the existing `ForceAdminTotp` middleware was a pass-through and the
existing `TotpController` was an empty skeleton.

ADMIN-19 therefore adds rollout-safe administrator TOTP instead of falsely
freezing the admin site while the planned second factor is absent.

## Locked ADMIN-19 scope

ADMIN-19 adds:

1. administrator TOTP enrollment and challenge;
2. encrypted-at-rest admin TOTP seeds;
3. TOTP replay protection;
4. rollout-safe production enforcement flag;
5. row-lock serialized failed-password account-lock updates;
6. admin-wide CSP and security headers;
7. explicit admin Host allowlist;
8. optional HTTPS hard enforcement;
9. optional HSTS after HTTPS is verified;
10. idle admin session expiration;
11. absolute admin session expiration;
12. production release-readiness command;
13. release/security regression tests.

ADMIN-19 does not reopen business behavior in ADMIN-01 through ADMIN-18.

## TOTP rollout model

The default value is intentionally:

`TRADEX_ADMIN_TOTP_REQUIRED=false`

This is not the production target. It is a safe migration/enrollment state.

### Required rollout order

1. deploy ADMIN-19 code;
2. run migrations;
3. keep `TRADEX_ADMIN_TOTP_REQUIRED=false`;
4. every active admin signs in with the existing password flow;
5. every active admin opens `/admin/account/totp`;
6. re-enter current password;
7. add the generated secret to a standard authenticator;
8. confirm a fresh TOTP code;
9. verify every active admin is enrolled;
10. set `TRADEX_ADMIN_TOTP_REQUIRED=true` in production;
11. clear/configure Laravel caches;
12. run `php artisan tradex:admin-release-check --production`;
13. freeze only when all critical gates pass.

This staged approach is still the preferred release process. If an active
admin is missed, or a new admin is created after enforcement is enabled,
ADMIN-19 does **not** require global 2FA to be switched off: a valid password
creates an enrollment-only session that is confined to `/admin/account/totp`
and logout until a fresh TOTP is confirmed.

## TOTP login contract

When TOTP enforcement is disabled:

`PASSWORD -> ADMIN SESSION`

This preserves local/test compatibility while enrollment is being completed.

When TOTP enforcement is enabled for an already-enrolled admin:

`PASSWORD -> SHORT-LIVED PENDING CHALLENGE -> TOTP -> ADMIN SESSION`

A real authenticated `admin` guard session is not created after the password
stage for an already-enrolled administrator.

When TOTP enforcement is enabled for an **unenrolled** admin:

`PASSWORD -> ENROLLMENT-ONLY ADMIN SESSION -> TOTP SETUP + CONFIRM -> FULL ADMIN SESSION`

The enrollment-only session is permitted to reach only the TOTP enrollment
surface and logout. Any attempt to open another admin route is redirected back
to enrollment. Remember-me is disabled for this restricted session.

The login challenge lifetime defaults to 5 minutes.

Failed password attempt counters are updated under an `admin_users` row lock,
so parallel invalid password requests cannot under-count the account-lock
threshold.

## TOTP enrollment

Enrollment routes require an already authenticated admin session.

Starting setup requires the current admin password.

Confirming setup requires:

- the current admin password again;
- a valid fresh TOTP code;
- setup to still be inside the setup window.

There is deliberately no self-service TOTP disable/reset endpoint.

## TOTP secret storage

New nullable `admin_users` columns:

- `totp_secret`
- `totp_setup_at`
- `totp_confirmed_at`
- `totp_last_used_step`

`AdminUser::totp_secret` uses Laravel's `encrypted` cast.

The secret is:

- encrypted before database storage;
- hidden from array/JSON serialization;
- shown only during unconfirmed protected setup;
- not rendered after confirmation;
- never copied into audit payloads.

## Replay protection

ADMIN-19 reuses the existing frozen TOTP algorithm implementation in
`App\Services\Auth\TotpService` for code matching.

`totp_last_used_step` is updated under a database row lock.

A code at the same or an older time step is rejected.

This prevents replay of a recently accepted TOTP value.

## Admin-wide security headers

`AdminSecurityHeaders` is prepended to the Laravel `web` middleware group and
is internally path-scoped to:

- `/admin`
- `/admin/*`

Non-admin web pages pass through unchanged.

Admin responses receive:

- `Cache-Control: private, no-store, no-cache, max-age=0, must-revalidate`
- `Pragma: no-cache`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: no-referrer`
- `X-Frame-Options: DENY`
- `X-Robots-Tag: noindex, nofollow, noarchive`
- restrictive `Permissions-Policy`
- `Cross-Origin-Opener-Policy: same-origin`
- `Cross-Origin-Resource-Policy: same-origin`
- Content-Security-Policy

The default CSP is:

- same-origin by default;
- no objects;
- no framing;
- same-origin forms;
- same-origin scripts;
- exact `https://i.ytimg.com` image allowance for the existing Our Channel
  YouTube thumbnails;
- inline styles allowed for compatibility with the existing admin Blade UI;
- a temporary `script-src 'unsafe-inline'` compatibility exception because
  frozen legacy admin views still contain inline `onsubmit` confirmation
  handlers.

The inline-script exception is deliberately reported as a **release warning**,
not hidden. It should be removed in a future UI-only hardening pass by moving
inline event handlers into same-origin static JavaScript. ADMIN-19 does not
silently refactor frozen business-module views just to satisfy CSP.

## Host allowlist

`TRADEX_ADMIN_ALLOWED_HOSTS` is a comma-separated exact host allowlist for the
admin surface.

When the list is non-empty, `/admin` requests using any other Host header fail
closed with HTTP 400. The rejected Host value is not reflected in the response.

The production release check requires an explicit allowlist and requires the
hostname from `APP_URL` to appear in that allowlist.

## HTTPS and HSTS

Local-safe defaults:

- `TRADEX_ADMIN_REQUIRE_HTTPS=false`
- `TRADEX_ADMIN_HSTS_ENABLED=false`

Production release requires both to be true.

HTTPS enforcement returns a fail-closed 403 for an insecure admin request.
It does not construct a redirect from an untrusted Host header.

HSTS is only emitted on requests Laravel identifies as secure. Production
release readiness requires a max-age of at least 180 days.

Before enabling HTTPS/HSTS behind a reverse proxy or CDN, proxy trust must be
correctly configured so Laravel can reliably detect the original HTTPS scheme.

## Admin session hardening

Default ADMIN-19 admin limits:

- idle timeout: 30 minutes;
- absolute session timeout: 480 minutes / 8 hours.

Both are independent of the broader Laravel session lifetime.

On expiry ADMIN-19:

1. writes a safe `ADMIN_SESSION_EXPIRED` audit event;
2. logs out the admin guard;
3. invalidates the session;
4. regenerates the CSRF token;
5. returns the operator to admin login.

## Existing-session TOTP enforcement

When `TRADEX_ADMIN_TOTP_REQUIRED=true`, an already authenticated admin session
must also contain the second-factor session marker produced by a successful
TOTP login.

If an old password-only session exists when enforcement is turned on, it is
logged out and must authenticate again.

This avoids grandfathering old sessions past the new second factor.

## Release readiness command

Baseline code/config check:

```powershell
php artisan tradex:admin-release-check
```

Production gate:

```powershell
php artisan tradex:admin-release-check --production
```

The command never prints APP_KEY, TOTP seeds, credentials or other secrets.

### Baseline critical gates

- ADMIN-19 TOTP schema exists;
- idle timeout is 5-30 minutes;
- absolute timeout is valid and <= 12 hours;
- CSP enabled with required same-origin/form/frame/object/script controls;
- HTTP-only session cookies;
- SameSite `lax` or `strict`;
- JSON session serialization;
- non-array session driver;
- APP_KEY configured.

### Additional production critical gates

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `APP_URL=https://...`;
- secure session cookie enabled;
- encrypted session storage enabled;
- explicit admin Host allowlist configured;
- admin HTTPS enforcement enabled;
- admin HSTS enabled;
- admin TOTP enforcement enabled;
- at least one active admin exists;
- zero active admins are missing confirmed TOTP;
- every active admin TOTP secret is decryptable with the current APP_KEY and has valid Base32 shape.

Warning-level items include:

- a persistent/shared production cache backend for scaled rate limiting;
- removal of the temporary CSP inline-script compatibility exception.

Warnings never override a critical-gate failure and are always surfaced by the
release command.

## Production environment target

At minimum:

```text
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-ADMIN-DOMAIN

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

TRADEX_ADMIN_IDLE_TIMEOUT_MINUTES=30
TRADEX_ADMIN_ABSOLUTE_TIMEOUT_MINUTES=480

TRADEX_ADMIN_CSP_ENABLED=true
TRADEX_ADMIN_ALLOWED_HOSTS=YOUR-ADMIN-DOMAIN
TRADEX_ADMIN_REQUIRE_HTTPS=true
TRADEX_ADMIN_HSTS_ENABLED=true
TRADEX_ADMIN_HSTS_MAX_AGE_SECONDS=31536000
TRADEX_ADMIN_HSTS_INCLUDE_SUBDOMAINS=true

TRADEX_ADMIN_TOTP_REQUIRED=true
TRADEX_ADMIN_TOTP_ISSUER="TradeX OS Admin"
TRADEX_ADMIN_TOTP_SETUP_WINDOW_MINUTES=15
TRADEX_ADMIN_TOTP_CHALLENGE_MINUTES=5
TRADEX_ADMIN_TOTP_ATTEMPTS_PER_MINUTE=5
```

Do not commit production `.env` values.

## Break-glass TOTP recovery

ADMIN-19 deliberately provides no web reset button because a convenient TOTP
reset surface weakens the factor.

If an administrator permanently loses the authenticator, recovery is an
owner/server-operator break-glass procedure:

1. verify the administrator's identity out-of-band;
2. take a database backup;
3. clear only that verified administrator's TOTP enrollment fields using a
   controlled server-side maintenance action;
4. preserve the break-glass action in the incident/change record;
5. the verified administrator signs in with the normal password and is
   confined automatically to the enrollment-only TOTP surface;
6. re-enroll immediately and confirm a fresh TOTP;
7. run the production release check again.

Global `TRADEX_ADMIN_TOTP_REQUIRED` does not need to be disabled for this
re-enrollment path.

Do not expose the encrypted TOTP seed in support/admin UI.

## New routes

Exactly five ADMIN-19 TOTP routes:

- `GET /admin/totp/challenge`
- `POST /admin/totp/challenge`
- `GET /admin/account/totp`
- `POST /admin/account/totp/setup`
- `POST /admin/account/totp/confirm`

No DELETE route.

## Audit events

ADMIN-19 adds safe events including:

- `ADMIN_TOTP_SETUP_STARTED`
- `ADMIN_TOTP_ENABLED`
- `ADMIN_LOGIN_PASSWORD_VERIFIED`
- `ADMIN_TOTP_LOGIN_FAILED`
- `ADMIN_LOGIN` with `PASSWORD_TOTP`
- `ADMIN_LOGIN` with `PASSWORD_TOTP_ENROLLMENT`
- `ADMIN_SESSION_EXPIRED`

No TOTP secret or TOTP code is written into audit payloads.

## Frozen boundaries

ADMIN-19 intentionally does not alter business logic in:

- Education;
- Our Channel;
- Trade Ideas;
- Affiliates;
- Payments;
- Payouts;
- Finance;
- KYC;
- Notifications;
- Integrations;
- Legal;
- Languages;
- mobile API auth/TOTP contract;
- payment/refund policies;
- affiliate commercial rules.

The existing user/mobile `TotpService` algorithm is reused, not changed.

## Validation

```powershell
php artisan optimize:clear
php artisan migrate

php artisan route:list --name=admin.totp
php artisan route:list --name=admin.account.totp

php artisan test tests/Feature/Admin/Hardening/AdminFinalHardeningTest.php
php artisan test

git diff --check
git status --short

php artisan tradex:admin-release-check
```

Do not expect the local machine to pass:

```powershell
php artisan tradex:admin-release-check --production
```

unless the local environment is intentionally configured like production and
all active admin accounts have confirmed TOTP.

## Freeze rule

ADMIN-19 may be code-frozen after local targeted/full regression and PR merge.

The Admin Website may be called **production release-ready** only after the
production deployment itself passes:

`php artisan tradex:admin-release-check --production`

## ADMIN-19 v2 local-test corrections

The first local targeted run exposed two implementation/test compatibility
issues. Both are corrected in v2:

1. Laravel's session store does not expose `Session::boolean()`. The
   enrollment-only marker is now read with `Session::get(..., false)` and cast
   to boolean.
2. The HSTS feature test now creates an absolute
   `https://localhost/admin/login` request so the Laravel/Symfony test kernel
   marks the request as secure. Production HSTS middleware behavior itself did
   not need to be weakened.

These corrections do not change frozen business-module behavior.
