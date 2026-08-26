# API-23 — Mobile API Final Hardening + Release Freeze

## Purpose

API-23 is the final cross-cutting hardening layer for the TradeX mobile API surface. It does not redesign frozen API-01 through API-22 business contracts. It adds release-level transport, request-isolation, response-header and deployment-readiness controls once at Laravel's `api` middleware group.

## Frozen boundaries

API-23 does not change payment settlement rules, affiliate rules, KYC decisions, payout rules, education entitlement/progress rules, assignment submission policy, notification delivery semantics, social onboarding rules, legal acceptance semantics, translation review semantics, education localization semantics, or push provider payload privacy rules.

Provider-loaded route files continue to own their existing authentication, trusted-device and dedicated throttle policies. Existing public callbacks remain public and keep their provider signature verification rules.

## Request hardening

`HardenMobileApiRequests` is prepended to Laravel's `api` middleware group.

For every API request it:

- clears resolved authentication guards so long-lived workers resolve the bearer principal from the current request;
- generates a server-owned UUID request ID and exposes it as `X-Request-Id`;
- never trusts or echoes a client-supplied request ID;
- can enforce an exact production hostname allow-list;
- can reject non-HTTPS API traffic;
- adds `nosniff`, no-referrer, anti-framing, no-index, permissions and cross-domain-policy response headers;
- adds HSTS only on secure requests when explicitly enabled;
- marks bearer-token responses `private, no-store` so protected API/material responses are not cached;
- preserves public ETag/304 behavior for API-18/API-19 runtime translations/bootstrap because public responses are not globally forced to `no-store`;
- routes downstream exceptions through Laravel's existing exception handler so frozen JSON error/status contracts remain authoritative while hardening headers are still added.

## Production environment knobs

```env
TRADEX_API_ALLOWED_HOSTS=api.example.com
TRADEX_API_REQUIRE_HTTPS=true
TRADEX_API_HSTS_ENABLED=true
TRADEX_API_HSTS_MAX_AGE_SECONDS=31536000
TRADEX_API_HSTS_INCLUDE_SUBDOMAINS=true
```

Host entries are exact hostnames only: no schemes, ports or wildcards.

## Release readiness command

Baseline:

```bash
php artisan tradex:mobile-api-release-check
```

Production gate:

```bash
php artisan tradex:mobile-api-release-check --production
```

The command fails closed and never prints secret values, credential contents, private keys or sensitive exception details.

Baseline critical checks include:

- API-23 release-contract marker;
- configured application key;
- bounded access/refresh-token lifetimes;
- frozen TOTP contract;
- trusted-device schema;
- Sanctum device/session binding schema;
- hashed refresh-token rotation schema.

Production critical checks additionally include:

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- HTTPS `APP_URL`;
- exact API host allow-list and APP_URL host membership;
- API HTTPS enforcement;
- HSTS with at least 180-day max-age;
- production server database backend;
- shared cache backend for distributed rate limiting;
- asynchronous queue backend for video/push jobs;
- real transactional mail transport for OTP/recovery;
- FCM/APNs configuration when global push is enabled.

DEBUG logging is surfaced as a production warning.

## Final release operator gate

Before a production release/freeze, execute on the deployment configuration:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan route:list --path=api
php artisan test
php artisan tradex:mobile-api-release-check --production
```

Also operationally verify:

1. HTTPS certificate/redirect/proxy trust configuration is correct.
2. Queue workers are running and supervised.
3. Laravel scheduler is running continuously.
4. Mail OTP/recovery delivery is verified end-to-end.
5. Enabled PayPal/Stripe/NOWPayments callback URLs and secrets are verified through their integration-specific readiness/admin controls.
6. Enabled FCM/APNs credentials are deployed with least privilege and real-device delivery is smoke-tested.
7. Private video/HLS storage and CDN access controls are verified.
8. Database backups, retention and restore procedures are verified.
9. Logs/alerts do not contain raw tokens, OTP secrets, payment credentials, KYC documents or push-provider tokens.

## Freeze condition

API-23 may be marked final-frozen only after:

- targeted API-23 tests pass;
- all API feature regressions pass;
- full Laravel regression passes;
- `git diff --check origin/main...HEAD` is clean;
- working tree is clean;
- PR changed-files audit confirms no unintended frozen-module modifications;
- expected-head SHA locked PR merge succeeds;
- local `main` syncs to the exact merge SHA and final regression remains green.

After this condition, API-01 through API-23 are the frozen TradeX mobile API release baseline. Any later API behavior change must begin as a new explicitly scoped module/change request rather than silently modifying the frozen baseline.
