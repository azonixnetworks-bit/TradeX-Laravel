# API-19 — Mobile App Bootstrap + Safe Runtime Settings

Base main:

`bf49bd51308a0cbb7aead4dfe5ea2eb15fe5c593`

Feature branch:

`feature/api-19-mobile-bootstrap-settings`

## Purpose

API-19 gives the mobile client one safe public startup endpoint without turning
`system_settings` or application configuration into a generic public API.

Endpoint:

- `GET /api/v1/bootstrap`

The endpoint is public because startup configuration, language selection and
current legal-policy metadata are needed before registration/login.

## Safe settings boundary

API-19 exposes exactly these ADMIN-14 values:

- `general.platform_name`
- `general.support_email`
- `general.support_whatsapp`
- `general.maintenance_notice_enabled`
- `general.maintenance_notice`

The API has its own explicit exposure allowlist. A future ADMIN-14 setting does
not automatically become public.

Missing DB rows use the frozen ADMIN-14 defaults.

If maintenance notice is disabled, API-19 returns the notice text as `null`
even if stale text exists in the DB.

Malformed allowlisted rows fail closed with HTTP 503.

## Explicitly not exposed

API-19 never exposes arbitrary `system_settings` rows, `.env` values,
credentials, payment secrets/destinations, bank details, wallet addresses,
cryptographic material, storage paths, security internals, affiliate rules,
private media configuration or other admin-only runtime snapshots.

## Language composition

The bootstrap response reuses API-18 `RuntimeTranslationService::languages()`.

This preserves:

- the seven frozen registration languages;
- English as source/fallback;
- Urdu RTL metadata;
- API-18 runtime state;
- ADMIN-18 translation-program semantics.

Translation bundles remain on the dedicated API-18 endpoints.

## Legal composition

The bootstrap response reuses API-17 `LegalPolicyApiService::currentPolicies()`.

Only current published/effective policy metadata is included. Full legal policy
content is not embedded in bootstrap.

User-specific legal acceptance status is not public and remains on the existing
authenticated API-17 acceptance-status endpoint.

## Frozen runtime markers

API-19 does not mutate:

- `tradex_languages.runtime_translation_api = NOT_WIRED`
- `tradex_legal.acceptance_runtime = NOT_WIRED`

Those historical ADMIN markers remain frozen. API-18 and API-17 expose their own
runtime states through their own API config/contracts.

## Cache and integrity

The bootstrap payload has a deterministic SHA-256 fingerprint and quoted HTTP
ETag. `If-None-Match` can return HTTP 304.

Public reads are rate limited by source IP.

## Frozen boundaries

API-19 does not modify:

- ADMIN-14 settings model/service/config or admin routes;
- ADMIN-17 legal model/service/config or admin routes;
- ADMIN-18 language model/service/config or admin routes;
- registration/auth flows;
- payment/affiliate/KYC/education/security business logic;
- frozen `routes/api.php`.

No migration is required.

## Validation

```powershell
php artisan optimize:clear
php artisan migrate
php artisan route:list --name=api.v1.bootstrap

php artisan test tests/Feature/Admin/Settings/SystemSettingsManagementTest.php
php artisan test tests/Feature/Admin/Legal/LegalPolicyManagementTest.php
php artisan test tests/Feature/Admin/Languages/LanguageTranslationManagementTest.php
php artisan test tests/Feature/Api/Bootstrap/MobileBootstrapApiTest.php
php artisan test

git diff --check origin/main...HEAD
git status --short
```
