# ADMIN-10 — KYC Management

Base main: `f1b18f69b2c0ea851587d46c333b9abdc4f30d6e`

Feature branch: `feature/admin-10-kyc-management`

## Purpose

ADMIN-10 provides controlled admin review over the frozen API-13 KYC submission and private-document foundation.

## Frozen KYC source of truth reused

ADMIN-10 does not modify `KycSubmission`, `KycDocument`, `KycCountryPolicy`, `KycIdentityHasher`, `KycDocumentStorageService`, `KycSubmissionService`, API-13 KYC routes/controllers, existing KYC migrations, or `tradex_kyc` configuration.

No new database migration is required.

## Lifecycle

Only the user's latest `PENDING_REVIEW` submission may be reviewed.

Allowed transitions:

- `PENDING_REVIEW -> VERIFIED`
- `PENDING_REVIEW -> REJECTED`
- `PENDING_REVIEW -> NEEDS_RESUBMISSION`

ADMIN-10 never reopens verified KYC, edits submitted identity data, edits identity hashes, replaces/deletes evidence, uploads evidence for users, creates KYC submissions, or bypasses duplicate-active-identity protection.

Rejected and resubmission-required states release the frozen model's `active_identity_hash` claim so corrected evidence can be submitted without deleting history.

## Sensitive-data policy

Normal review shows legal name, DOB and address but masks the identity number. It never renders identity hashes, private storage paths, original file names, or SHA-256 hashes.

Full identity reveal requires `kyc.view` + `kyc.view_documents`, is a separate audited page, sends no-store/no-cache/nosniff/no-referrer/deny-frame/no-index headers, and records `KYC_IDENTITY_ACCESSED` without placing identity values in the audit record.

Private document access requires `kyc.view` + `kyc.view_documents`. ADMIN-10 validates document/submission ownership, rejects public/unconfigured disks and traversal/absolute paths, verifies the expected KYC ownership prefix, checks file existence, verifies SHA-256, serves a generated safe filename, and records `KYC_DOCUMENT_ACCESSED` without storage path/hash leakage.

## Approval integrity

Before `VERIFIED`:

- configured required identity fields must still be present;
- the identity integrity record must remain a 64-character digest;
- country/document policy must still exist;
- every required KYC document row must exist;
- every required private file must exist;
- each required file SHA-256 must match its immutable evidence record.

Storage SHA verification runs immediately before the database transaction. The decision transaction locks the user first, then the latest submission, re-checks required evidence rows, applies the state transition, reviewer metadata and audit event atomically.

## Reasons and notes

`REJECTED` and `NEEDS_RESUBMISSION` require a user-visible reason. It uses the existing encrypted `rejection_reason` cast. Internal notes are optional and remain encrypted. Neither reason nor internal notes is duplicated into audit values.

## Existing RBAC reused

- `kyc.view`
- `kyc.view_documents`
- `kyc.approve`
- `kyc.reject`
- `kyc.request_resubmission`

No permission seeder changes are required.

## Routes

- `GET /admin/kyc`
- `GET /admin/kyc/{submission}`
- `GET /admin/kyc/{submission}/identity`
- `GET /admin/kyc/{submission}/documents/{document}`
- `PATCH /admin/kyc/{submission}/approve`
- `PATCH /admin/kyc/{submission}/reject`
- `PATCH /admin/kyc/{submission}/request-resubmission`

Sensitive and mutation routes are rate-limited.

## Audit actions

- `KYC_IDENTITY_ACCESSED`
- `KYC_DOCUMENT_ACCESSED`
- `KYC_VERIFIED`
- `KYC_REJECTED`
- `KYC_RESUBMISSION_REQUESTED`

## Validation

```bash
php artisan optimize:clear
php artisan route:list --name=admin.kyc
php artisan test tests/Feature/Admin/Kyc/KycManagementTest.php
php artisan test
git diff --check
git status --short
```
