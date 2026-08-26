# ADMIN-18 — Languages & Translation Management

Base main:

`787ff2b57814965bcb8f6849ff19d2fb17cde505`

Feature branch:

`feature/admin-18-languages-translation-management`

## Existing repository contract

The frozen registration flow already accepts these seven language codes:

- `en` — English
- `ta` — Tamil
- `si` — Sinhala
- `hi` — Hindi
- `ml` — Malayalam
- `te` — Telugu
- `ur` — Urdu

The `users.language_code` field already exists.

ADMIN-18 mirrors that frozen allowlist but does not rewrite registration/auth.

## Purpose

ADMIN-18 provides an administrative localization control plane:

- language profiles
- source translation keys
- target translation drafts
- human review
- SHA-256 integrity fingerprints
- stale translation detection
- completion metrics
- safe audit history

## Source language

English (`en`) is the ADMIN-18 application-string source language.

This choice applies to application translation keys only.

It does **not** assert that TradeX course videos/content use English as their
original production language.

## Course / education content boundary

Existing Course, Module and Lesson schemas are frozen and are not duplicated
or changed by ADMIN-18.

Status:

`education_content_localization = NOT_WIRED`

A later explicit education-localization evolution can add multilingual
Course/Module/Lesson text/media contracts without silently changing frozen
education behavior.

## Runtime translation boundary

The current mobile/API runtime does not yet read ADMIN-18 database translation
tables.

Status:

`runtime_translation_api = NOT_WIRED`

ADMIN-18 therefore does not claim that saving a translation instantly changes
the mobile application.

A future API/runtime module can read only reviewed, current translations.

## Language profiles

Table:

`language_profiles`

Fields:

- code
- english_name
- native_name
- direction
- is_source
- is_translation_enabled
- version
- updated_by_admin_id
- timestamps

The migration creates exactly the seven existing registration languages.

Urdu is RTL. The others are LTR.

English is the source profile and cannot be disabled.

For target languages, `is_translation_enabled` controls the ADMIN-18
translation program only. It does not rewrite the frozen registration
allowlist.

## Translation keys

Table:

`translation_keys`

A source key contains:

- UUID
- group
- stable translation key
- English source text
- source SHA-256
- optimistic version
- creator/updater admin
- timestamps

Key identity is immutable after creation:

- group cannot change
- translation key cannot change

Source text may change.

Translation keys cannot be deleted through the model or ADMIN-18 routes.

## Translation groups

Approved groups:

- GENERAL
- APP_UI
- AUTH
- EDUCATION_UI
- PAYMENTS_UI
- AFFILIATE_UI
- NOTIFICATIONS_UI
- LEGAL_UI

These are application-string domains, not frozen business-content schemas.

## Translation values

Table:

`translation_values`

Each target translation is unique by:

`translation_key_id + language_code`

Fields include:

- UUID
- target language
- translated text
- source SHA-256 captured when translated
- translation SHA-256
- DRAFT / REVIEWED persisted status
- optimistic version
- updater/reviewer admin
- reviewed_at
- timestamps

## Human-review lifecycle

For target languages:

`MISSING → DRAFT → REVIEWED`

If the source text changes after a translation was drafted or reviewed:

`DRAFT / REVIEWED → STALE` (derived)

STALE is derived from fingerprints:

`source_sha256_at_translation != translation_keys.source_sha256`

No bulk mutation is needed when source text changes.

A stale translation must be updated against the new source before it can be
reviewed again.

## No fake automatic translation

ADMIN-18 does not call an AI/LLM or external translation provider.

Saving or reviewing translations is a human-admin workflow.

This is deliberate because translation accuracy requirements should not be
represented as guaranteed without a review process.

## Translation review

Review requires:

- `languages.view`
- `languages.manage`
- existing non-empty translation draft
- matching optimistic version
- explicit review confirmation
- translation captured against the current source SHA-256

On review:

- status becomes REVIEWED
- reviewer admin is recorded
- reviewed_at is recorded
- version increments

## Accuracy / integrity

Source text and target translation text are canonicalized:

- CRLF / CR → LF
- surrounding whitespace trimmed

SHA-256 fingerprints are stored for:

- source text
- translated text

This supports stale detection and change evidence.

## XSS boundary

Source and translated text are rendered through escaped Blade output.

ADMIN-18 does not execute translation HTML or JavaScript.

## Permissions

Existing permissions reused:

- `languages.view`
- `languages.manage`

No new permissions.

No permission/role seeder changes.

## Concurrency

All mutations use:

- database transactions
- row locks
- optimistic version checks

Stale mutations return HTTP `409`.

## Audit events

- `LANGUAGE_TRANSLATION_STATE_UPDATED`
- `TRANSLATION_KEY_CREATED`
- `TRANSLATION_SOURCE_UPDATED`
- `TRANSLATION_DRAFT_UPDATED`
- `TRANSLATION_REVIEWED`

Audit metadata may include:

- language code
- translation key UUID / stable key
- source SHA-256
- translation SHA-256
- source/translation version
- status

Audit deliberately excludes:

- full English source text
- full translated text

## Routes

Exactly eleven ADMIN-18 routes:

- `GET /admin/languages`
- `GET /admin/languages/keys`
- `GET /admin/languages/keys/create`
- `POST /admin/languages/keys`
- `GET /admin/languages/keys/{translationKey}/edit`
- `PATCH /admin/languages/keys/{translationKey}`
- `PATCH /admin/languages/{languageProfile}`
- `GET /admin/languages/{languageProfile}/translations`
- `GET /admin/languages/{languageProfile}/translations/{translationKey}/edit`
- `PATCH /admin/languages/{languageProfile}/translations/{translationKey}`
- `POST /admin/languages/{languageProfile}/translations/{translationKey}/review`

No DELETE route.

## Frozen boundaries

ADMIN-18 does not modify:

- `User.php`
- registration/auth request/controller/service code
- `config/tradex_registration.php`
- Course/Module/Lesson models or migrations
- Education API/Admin implementation
- Legal policies
- payment/refund code
- affiliate code
- notifications
- integrations
- security/KYC/device modules
- Admin permission/role seeders
- existing AuditLog implementation

## Validation

```powershell
php artisan optimize:clear
php artisan migrate
php artisan route:list --name=admin.languages

php artisan test tests/Feature/Admin/Languages/LanguageTranslationManagementTest.php
php artisan test

git diff --check
git status --short
```
