# ADMIN-17 — Legal & Policy Management

Base main:

`772a81b7b4604e9b663e013af094f5ebcebb72d1`

Feature branch:

`feature/admin-17-legal-policy-management`

## Purpose

ADMIN-17 provides a controlled legal-document lifecycle for TradeX:

Draft → Review → Publish → Immutable History.

It manages document lifecycle and evidence of publication. It does **not**
generate or certify substantive legal wording.

Production policy text must be reviewed for each jurisdiction by qualified
legal/compliance professionals.

## Policy registry

ADMIN-17 supports:

- Terms of Service
- Privacy Policy
- No-Refund Policy
- Trading Risk Disclosure
- Affiliate Program Terms
- Marketing Consent Notice
- Complaints Policy

No policy content is seeded by ADMIN-17.

## Permissions

Existing permissions are reused:

- `legal.view`
- `legal.manage`
- `legal.publish`

Separation of duties:

- `legal.view` — read policy inventory/history
- `legal.manage` — create/edit drafts
- `legal.publish` — publish/lock reviewed drafts

`legal.publish` does not require `legal.manage`, allowing a separate publisher
role.

No permission or role seeder change.

## Version model

Every policy type has numbered versions:

- v1
- v2
- v3
- ...

Only one editable DRAFT is allowed per policy type through ADMIN-17.

A policy change after publication requires a new draft/version.

## Publication

Publishing requires:

- `legal.view`
- `legal.publish`
- matching draft revision version
- explicit irreversible-publication confirmation
- non-backdated effective time

Effective time may be immediate or future.

A future effective version is derived as `SCHEDULED`.

Current policy selection uses the latest published version whose:

- `published_at <= now`
- `effective_at <= now`

Published history remains preserved.

## Immutability

Published versions cannot be edited through the service or controller.

The `LegalPolicyVersion` model also rejects Eloquent updates when the original
row is already published.

Legal policy rows cannot be deleted through Eloquent.

ADMIN-17 exposes no DELETE route.

Publication does not rewrite historical rows into a "superseded" state.
Old published rows remain intact. "Current" is derived from effective/version
ordering.

## Integrity fingerprint

Every draft stores a SHA-256 fingerprint over canonical plain-text content.

Canonicalization:

- trims surrounding whitespace
- converts CRLF/CR line endings to LF

The fingerprint is updated while the draft changes.

At publication, content becomes immutable, so the SHA-256 value becomes the
stable fingerprint of the published text.

## XSS boundary

Policy content is stored as plain text.

Admin rendering uses escaped Blade output.

ADMIN-17 does not execute policy HTML or JavaScript.

## Audit

Actions:

- `LEGAL_POLICY_DRAFT_CREATED`
- `LEGAL_POLICY_DRAFT_UPDATED`
- `LEGAL_POLICY_PUBLISHED`
- `LEGAL_POLICY_PUBLISHED_SCHEDULED`

Audit payload includes safe metadata:

- policy UUID
- policy type
- version number
- state/status
- content SHA-256
- draft revision version
- publication/effective timestamps where relevant

Audit deliberately excludes:

- full legal content
- title
- summary

## Customer acceptance

Current repository inspection found no customer legal-acceptance API/ledger.

ADMIN-17 therefore exposes:

`acceptance_runtime = NOT_WIRED`

It does not falsely claim customer acceptance enforcement.

A later API module can bind customer acceptance records to immutable
`legal_policy_versions.id/uuid` and the stable SHA-256 fingerprint without
changing ADMIN-17 publication history.

## Database

New table:

`legal_policy_versions`

Columns:

- id
- uuid
- policy_type
- version_number
- title
- summary
- content
- content_sha256
- status
- effective_at
- published_at
- revision_version
- created_by_admin_id
- updated_by_admin_id
- published_by_admin_id
- timestamps

Unique:

`policy_type + version_number`

## Concurrency

Draft updates and publication use:

- database transaction
- row lock
- optimistic `revision_version`

Stale mutation returns HTTP `409`.

## Routes

Exactly seven routes:

- `GET /admin/legal`
- `GET /admin/legal/create/{policyType}`
- `POST /admin/legal`
- `GET /admin/legal/{legalPolicyVersion}/edit`
- `PATCH /admin/legal/{legalPolicyVersion}`
- `POST /admin/legal/{legalPolicyVersion}/publish`
- `GET /admin/legal/{legalPolicyVersion}`

No DELETE route.

## Frozen boundaries

ADMIN-17 does not modify:

- User model
- customer registration/auth flows
- payment/refund transaction implementation
- affiliate ledger/commission implementation
- notification module
- integration module
- Admin permission/role seeders
- existing AuditLog implementation
- security/KYC/device modules
- existing API routes

## Validation

```powershell
php artisan optimize:clear
php artisan migrate
php artisan route:list --name=admin.legal

php artisan test tests/Feature/Admin/Legal/LegalPolicyManagementTest.php
php artisan test

git diff --check
git status --short
```
