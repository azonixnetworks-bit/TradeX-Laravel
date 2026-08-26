# API-17 — Legal Policy Runtime + Customer Acceptance

Base main:

`6ecefa5ed0af56a711128045373ceded206c2d7f`

Feature branch:

`feature/api-17-legal-policy-acceptance`

## Purpose

API-17 wires the customer-facing runtime that ADMIN-17 deliberately left as
`NOT_WIRED`.

It reuses immutable `legal_policy_versions` and adds a separate immutable
customer acceptance evidence ledger.

API-17 does not edit, duplicate or republish legal content.

## Runtime contract

Public current-policy routes:

- `GET /api/v1/legal/policies`
- `GET /api/v1/legal/policies/{policyType}`

Authenticated trusted-device routes:

- `GET /api/v1/legal/acceptance-status`
- `POST /api/v1/legal/policies/{policyVersionUuid}/accept`

Current-policy selection exactly follows frozen ADMIN-17 behavior:

1. status is `PUBLISHED`;
2. `published_at <= now()`;
3. `effective_at <= now()`;
4. greatest `effective_at` wins;
5. version number breaks an effective-time tie.

Draft, future and historical versions are not accepted through API-17.

## Acceptance safety

Acceptance POST requires:

- authenticated Sanctum session;
- existing `tradex.mobile` trusted-device middleware;
- exact current legal-policy UUID;
- exact current `content_sha256`;
- explicit `acceptance_confirmation=true`.

If the content fingerprint supplied by the client is stale, API-17 returns
HTTP 409 and requires the client to reload the current policy.

Repeated acceptance of the same user + exact policy version is idempotent and
does not create duplicate evidence or duplicate audit records.

## Evidence ledger

New table:

`user_legal_policy_acceptances`

Evidence includes:

- acceptance UUID;
- user ID;
- immutable legal-policy-version ID;
- policy type;
- version number;
- exact SHA-256 fingerprint;
- accepted timestamp;
- request IP;
- bounded user-agent snapshot;
- trusted-device UUID;
- device platform.

The model rejects Eloquent update and delete operations.

## Audit

Action:

`LEGAL_POLICY_ACCEPTED`

Audit stores safe policy/evidence metadata and fingerprint, not the full legal
content.

## ADMIN-17 runtime marker

The frozen ADMIN-17 config file is not rewritten. During application boot,
`LegalPolicyApiServiceProvider` promotes the effective runtime marker to:

`WIRED_EVIDENCE_ONLY`

This lets the existing ADMIN-17 UI report the later API integration accurately
without reopening its business implementation.

## Enforcement boundary

API-17 is intentionally an evidence/status layer:

`runtime_state = WIRED_EVIDENCE_ONLY`

`cross_module_enforcement = false`

It does not silently modify frozen registration, login, payments, affiliate,
education or KYC flows. A later explicit integration can decide which policy
audiences gate which actions.

## Frozen boundaries

API-17 does not modify:

- `LegalPolicyVersion` model;
- ADMIN-17 admin routes/controllers/services/config file;
- `User` model;
- mobile auth/TOTP/device implementation;
- Payments/KYC/Affiliate/Education business logic;
- frozen `routes/api.php`.

The only shared file change is registration of
`LegalPolicyApiServiceProvider` in `bootstrap/providers.php`, matching the
existing isolated API module pattern.

## Validation

```powershell
php artisan optimize:clear
php artisan migrate
php artisan route:list --name=api.v1.legal
php artisan test tests/Feature/Api/Legal/LegalPolicyAcceptanceApiTest.php
php artisan test
git diff --check
git status --short
```
