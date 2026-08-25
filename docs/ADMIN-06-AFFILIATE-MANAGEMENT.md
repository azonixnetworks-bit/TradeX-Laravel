# ADMIN-06 — Affiliate Management

## Scope

ADMIN-06 activates the existing Admin sidebar Affiliate module over the frozen API-09 affiliate commercial foundation.

### Included

- Affiliate list/search/filter and portfolio statistics.
- Affiliate detail with direct referrals, qualified sales and recent commission entries.
- Direct-referral-only tree view.
- Progressive commercial stage view and controlled commercial edits.
- Global earned commission ledger visibility.
- Management-approved special commission rate from above 20% through 24%.
- Clearing a special commission so future sales return to standard stage rates.
- RBAC enforcement and audit events for all commercial mutations.
- Mutation throttling for stage and special-rate actions.

### Explicitly excluded

- Multi-level, upline, generation, override or team commissions.
- Voluntary refunds.
- Affiliate-admin payout approval or settlement.
- Editing earned commission ledger rows.
- Editing stage structural bands, stage codes, milestone sales thresholds or stage ordering.
- Retroactive recalculation of previously earned commissions.

## Security / commercial invariants

1. Existing `AffiliateCommercialService` remains the authority for special commission approval and future-sale commission calculation.
2. Standard stage commission cannot exceed 20%.
3. Special management-approved commission must be above 20% and no more than 24%.
4. Stage structural ranges stay immutable through ADMIN-06.
5. Updating a milestone gift recomputes cumulative gift display values transactionally.
6. Earned commissions are displayed as ledger records and are not directly edited.
7. Referral tree scope is `DIRECT_REFERRALS_ONLY`.
8. Payout and payment reversal workflows remain outside ADMIN-06.

## Routes

- `GET /admin/affiliates`
- `GET /admin/affiliates/analytics`
- `GET /admin/affiliates/stages`
- `PATCH /admin/affiliates/stages/{stage}`
- `GET /admin/affiliates/commissions`
- `GET /admin/affiliates/{affiliate}/tree`
- `PATCH /admin/affiliates/{affiliate}/special-commission`
- `DELETE /admin/affiliates/{affiliate}/special-commission`
- `GET /admin/affiliates/{affiliate}`

## Existing permissions reused

- `affiliates.view`
- `affiliates.view_tree`
- `affiliates.manage`
- `affiliate_stages.manage`
- `commissions.view`
- `commissions.manage` (reserved; ADMIN-06 does not expose refund/reversal/payout mutation)
