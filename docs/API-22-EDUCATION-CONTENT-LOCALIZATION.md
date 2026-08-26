# API-22 — Education Content Localization Runtime

## Purpose

API-22 localizes existing TradeX education API responses without duplicating courses, modules, lessons, videos, or assignments.

The existing education records remain the canonical source content. Localized text is stored separately and is served only when it is reviewed, hash-valid, and still matches the current source content.

## Supported languages

The runtime reuses the frozen API-18 language allowlist:

- `en` — English
- `ta` — Tamil
- `si` — Sinhala
- `hi` — Hindi
- `ml` — Malayalam
- `te` — Telugu
- `ur` — Urdu / RTL

No new language registry is introduced by API-22.

## Source language rule

Education content does **not** assume that English is always the source language.

`courses.language_code` is the source-language authority for the complete education tree below that course:

- course
- module
- lesson
- lesson video text metadata
- assignment text

Example: if a course was originally authored in Tamil, Tamil remains the fallback source for its modules, lessons, videos, and assignments.

## Requested display language

Priority:

1. `?locale=ta`
2. `X-TradeX-Language: ta`
3. authenticated user profile `language_code`
4. course source language

The pre-existing `language_code` query parameter on the course list remains a **source-language catalog filter** and is not repurposed as the runtime display locale.

## Runtime integration

No new customer route is required.

The existing API-04/API-05 endpoints become localization-aware, including:

- course list
- course detail
- module detail
- lesson detail
- lesson video metadata
- lesson assignment list
- assignment detail
- WhatsApp assignment action

Playback paths, HLS assets, protected storage paths, access gates, progress, premium checks, and assignment material security are unchanged.

## Localized fields

### Course

- title
- short_description
- description

### Module

- title
- short_description
- description

### Lesson

- title
- heading
- short_description
- description

### Video metadata

- title
- short_description
- description

API-22 does not generate dubbed audio, translated video binaries, subtitles, or alternate HLS assets.

### Assignment

- title
- heading
- description
- submission_instructions

Assignment protected material filename/path is not translated or modified.

## Storage

New table:

`education_content_localizations`

One row is unique by:

`content_type + content_id + language_code`

Supported content types:

- `COURSE`
- `MODULE`
- `LESSON`
- `VIDEO`
- `ASSIGNMENT`

Statuses:

- `DRAFT`
- `REVIEWED`

Only `REVIEWED` rows are eligible for customer runtime delivery.

## Source-integrity protection

Each localization stores:

- `source_sha256`
- `translation_sha256`
- `reviewed_at`
- version and reviewer/update audit references

At runtime TradeX recalculates the current source hash.

If the source content was edited after translation review, the hash no longer matches and the localized row is treated as stale. The API safely falls back to the course source language instead of serving an obsolete translation.

A reviewed row is also rejected if its translation hash is invalid or a translated field required by non-empty source text is missing.

## Response metadata

Localized education objects include a `localization` object with:

- runtime_state
- requested_language_code
- source_language_code
- resolved_language_code
- direction
- reviewed_translations_only
- reviewed_translation_used
- fallback_applied
- fallback_reason
- status

Statuses are:

- `SOURCE`
- `REVIEWED`
- `FALLBACK_SOURCE`

For Urdu, resolved direction is `RTL`.

## Fallback behavior

A source fallback occurs when:

- no reviewed translation exists
- translation is stale after source modification
- translation hash fails validation
- required translated text is incomplete
- translation program/profile is unavailable or disabled

Draft/unreviewed text is never served to customers.

## Assignment WhatsApp boundary

API-22 may localize the assignment title and submission instructions inserted into the WhatsApp prefilled message.

It does **not** change the frozen API-05 policy:

- no student file upload to TradeX
- submission remains external through WhatsApp
- TradeX does not claim WhatsApp delivery verification
- no fake submission-completed state is persisted

## Security boundaries preserved

API-22 does not change:

- premium access authorization
- lesson preview rules
- trusted-device authentication
- HLS token issuance
- protected media storage
- assignment material storage/download authorization
- progress tracking
- API-18 UI translation bundles
- original education publication state

## Runtime marker

`config/tradex_languages.php`:

`education_content_localization = WIRED_REVIEWED_RUNTIME`

The existing API-18 marker `runtime_translation_api` is not rewritten by API-22.

## Targeted validation

Run:

```powershell
php artisan migrate
php artisan test tests/Feature/Api/Education/EducationContentLocalizationRuntimeTest.php
php artisan test tests/Feature/Api/Education
php artisan test
```

Then:

```powershell
git diff --check origin/main...HEAD
git status --short
```
