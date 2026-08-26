# API-18 — Runtime Languages + Reviewed Translation Bundle

Base main:

`532f5fe51f8acd5b60a0a3b1ffa6dc21787430f0`

Feature branch:

`feature/api-18-runtime-translations`

## Purpose

API-18 connects the customer/mobile runtime to the frozen ADMIN-18 language and
application-string translation tables without changing ADMIN-18 authoring,
review, registration or education behavior.

## Public runtime routes

These endpoints are public because language selection and translated login /
registration UI are needed before authentication:

- `GET /api/v1/languages`
- `GET /api/v1/translations/{languageCode}`
- `GET /api/v1/translations/{languageCode}/{group}`

All routes use a dedicated public-read rate limiter.

## Frozen language allowlist

Runtime language identity is restricted to the existing registration allowlist:

- `en`
- `ta`
- `si`
- `hi`
- `ml`
- `te`
- `ur`

API-18 does not add, remove or rewrite registration language codes.

## Source and fallback behavior

English (`en`) remains the application-string source language.

For English bundles, API-18 serves verified `translation_keys.source_text`.

For target languages, a translated value is eligible only when all of the
following are true:

1. persisted status is `REVIEWED`;
2. translated text is non-empty;
3. `reviewed_at` is present;
4. `source_sha256_at_translation` exactly matches the current source SHA-256;
5. stored translation SHA-256 exactly matches the canonical translated text.

If any target translation is missing, DRAFT, stale or integrity-invalid, API-18
does not expose that target text. It falls back to the verified English source
string for that key.

Draft/stale target content is therefore never leaked to the mobile runtime.

## Source integrity

Every source string is checked against `translation_keys.source_sha256` before
serving. Source fingerprint failure returns HTTP 503 and fails closed because
there is no trustworthy fallback above the source layer.

## Translation program flag boundary

`language_profiles.is_translation_enabled` remains the ADMIN-18 translation
program control flag only.

API-18 reports the flag as metadata but does not reinterpret it as a
registration allowlist or automatically invalidate already reviewed/current
runtime translations.

## Runtime state boundary

Frozen ADMIN-18 configuration remains untouched:

`tradex_languages.runtime_translation_api = NOT_WIRED`

API-18 does not mutate that value during provider boot. Its own later runtime
integration state is exposed from:

`tradex_translation_api.runtime_state = WIRED_REVIEWED_RUNTIME`

This preserves ADMIN-18 regression behavior while allowing the API-18 client
contract to report its actual runtime state.

## Bundle contract

A bundle returns:

- requested language metadata;
- optional group filter;
- key count;
- reviewed translation count;
- fallback count;
- completion flag;
- nested `values[group][translation_key]` map;
- deterministic SHA-256 bundle fingerprint.

The bundle SHA-256 is returned as an HTTP `ETag` and supports conditional GET
with HTTP 304.

## Translation groups

API-18 reuses the frozen ADMIN-18 groups:

- GENERAL
- APP_UI
- AUTH
- EDUCATION_UI
- PAYMENTS_UI
- AFFILIATE_UI
- NOTIFICATIONS_UI
- LEGAL_UI

Unknown groups return HTTP 404.

## Education boundary

API-18 handles application strings only.

It does not localize Course, Module, Lesson, Assignment, video or education
business content. `education_content_localization = NOT_WIRED` remains frozen
for the later explicit education-localization module.

## Frozen boundaries

API-18 does not modify:

- ADMIN-18 controllers/services/routes/models/config;
- `config/tradex_registration.php`;
- registration/auth behavior;
- `User.php` or `users.language_code` behavior;
- education models/content;
- legal/payment/affiliate/notification/KYC/security modules;
- frozen `routes/api.php`.

The only shared file change is provider registration in
`bootstrap/providers.php`, following the isolated API module pattern.

## Validation

```powershell
php artisan optimize:clear
php artisan migrate
php artisan route:list --name=api.v1.languages
php artisan route:list --name=api.v1.translations
php artisan test tests/Feature/Api/Languages/RuntimeTranslationApiTest.php
php artisan test
git diff --check origin/main...HEAD
git status --short
```
