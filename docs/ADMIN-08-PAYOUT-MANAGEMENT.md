# ADMIN-08 — Payout Management

Base: ADMIN-07 frozen main.

## Scope
- Admin payout dashboard, filtering and request detail.
- Payout review over existing `payout_requests` + `payout_allocations`.
- Approve only from `PENDING_REVIEW`.
- Reject only from `PENDING_REVIEW`; rejected requests stop consuming affiliate balance automatically through the frozen ledger rules.
- Mark Paid only from `APPROVED`.
- Exact payout destination is available only through an audited, no-store sensitive endpoint for authorized payout operators.
- Normal payout detail renders only masked destination data.
- Commission allocations, requested amount, method and destination snapshot are immutable from ADMIN-08.
- Every mutation uses transactions, row locking and audit events.
- Allocation and destination integrity are revalidated before approval and before final paid confirmation.

## Explicit exclusions
- No admin-created payout request.
- No payout amount editing.
- No payout destination editing.
- No direct affiliate commission editing.
- No voluntary refund/reversal workflow.
- No automatic PayPal/Binance/USDT payout execution.
- No KYC workflow changes.
- No database migration required.

## Permissions
- `payouts.view`
- `payouts.approve`
- `payouts.reject`
- `payouts.mark_paid`

## Audit events
- `PAYOUT_DESTINATION_ACCESSED`
- `PAYOUT_APPROVED`
- `PAYOUT_REJECTED`
- `PAYOUT_MARKED_PAID`

## Lifecycle implemented
`PENDING_REVIEW -> APPROVED -> PAID`

or

`PENDING_REVIEW -> REJECTED`

`PROCESSING`, `FAILED` and `CANCELLED` remain reserved for existing/future provider or user flows and are not invented by the admin portal.
