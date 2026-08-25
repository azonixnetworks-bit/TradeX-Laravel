# ADMIN-07 — Payments Management

## Scope

ADMIN-07 activates the existing Payments admin skeleton over the frozen API-12 payment foundation.

- All payment order list/detail with search and filters.
- PayPal ledger is read-only and remains provider/webhook/capture verified.
- Local Bank manual evidence review with protected proof download.
- USDT TRC20 manual evidence review using submitted on-chain transaction hash.
- Authorized approve/reject actions with database row locks and audit records.
- Successful manual reconciliation creates the existing `payments` ledger row, clears the order and activates the existing Premium entitlement atomically.
- Company payment-account configuration status is shown without rendering secrets; deployment environment remains the configuration source of truth.

## Security / policy boundaries

- `payments.view` is read-only.
- `payments.verify` is required before validation for manual approval.
- `payments.reject` is required before validation for manual rejection.
- Bank proof requires `payments.verify` or `payments.reconcile` and is served from private storage with no-store headers.
- PayPal has no admin manual-approve route.
- Provider capture references, client secrets, webhook secrets, storage paths and idempotency hashes are never rendered.
- Manual Bank/USDT settlement does **not** invent processor fees or affiliate-eligible revenue; affiliate processing is `NOT_APPLICABLE` because the frozen affiliate commercial engine is PayPal-specific.
- No voluntary/discretionary refund action exists.
- No payout mutation exists in this module.
- No existing frozen payment service/model/migration is changed.

## Manual settlement invariants

Approval is allowed only while both the payment order and evidence record are pending/submitted. The order, evidence, user and settlement state are locked transactionally. A pre-existing payment row or reused evidence reference blocks settlement. Successful approval updates evidence review metadata, creates one payment ledger row, marks the order cleared, activates Premium and writes audit events within the same transaction.

Rejection marks the evidence rejected and order failed with `MANUAL_REVIEW_REJECTED`. It never creates a payment row or Premium entitlement.
