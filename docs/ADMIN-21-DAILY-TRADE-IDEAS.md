# ADMIN-21 — Daily Trade Ideas

## Purpose

Add a Daily Trade Ideas administration lifecycle alongside the existing Long-Term Trade Ideas module without changing the frozen Long-Term Trade Idea model or mobile API contract.

## Branch relationship

ADMIN-21 starts from the current ADMIN-BRAND-01 feature HEAD:

`b18f14eaed9f2d199e1e5c0c414292b7a801c749`

Feature branch:

`feature/admin-21-daily-trade-ideas`

The branding branch must be merged/frozen before ADMIN-21 is merged to `main`, or ADMIN-21 must otherwise be rebased onto the resulting main baseline.

## Admin hierarchy

Trade Ideas

- Long-Term Trade Ideas
- Daily Trade Ideas

Daily Trade Ideas expose:

- Create
- Edit while DRAFT
- Publish from DRAFT
- Archive from PUBLISHED

Lifecycle:

`DRAFT -> PUBLISHED -> ARCHIVED`

Archived Daily Trade Ideas are read-only historical records.

## Data isolation

Daily Trade Ideas use separate tables:

- `daily_trade_ideas`
- `daily_trade_idea_targets`

They do not add a discriminator to the existing `trade_ideas` table and do not change the existing `TradeIdea` status contract (`DRAFT`, `PUBLISHED`, `CLOSED`).

This protects API-06 and the frozen mobile Trade Ideas runtime from an implicit breaking change.

## Reused validation and authority

Daily create/update uses the existing TradeX Trade Idea payload validation and structure validator. This preserves category, symbol, direction, timeframe, optional structure and TradingView URL rules.

Existing RBAC is intentionally reused:

- `trade_ideas.view`
- `trade_ideas.create`
- `trade_ideas.update`
- `trade_ideas.publish`
- `trade_ideas.close` for Daily archive authority

No role is silently granted a new permission during this module.

## Security

- Admin authentication remains required.
- All Daily mutations require existing Trade Ideas permissions.
- New records always begin as DRAFT.
- Published/archived status cannot be supplied by the create/update payload.
- Chart images are stored only on the existing private disk.
- Private chart responses are no-store/no-cache and MIME allow-listed.
- Create/update/publish/archive actions are audited.
- Archived records cannot be edited.

## API boundary

ADMIN-21 is an Admin module only.

It does **not** expose Daily Trade Ideas through the frozen mobile API. A future API module must intentionally define how Long-Term and Daily Trade Ideas are returned to customer applications.

## Local validation

```powershell
php artisan migrate
php artisan optimize:clear
php artisan test tests/Feature/Admin/TradeIdeas/DailyTradeIdeaManagementTest.php
php artisan test tests/Feature/Admin/TradeIdeas/DailyTradeIdeaNavigationTest.php
php artisan test tests/Feature/Admin/TradeIdeas/TradeIdeaManagementTest.php
php artisan test tests/Feature/Admin/AdminRouteHardeningTest.php
```

Then visually inspect:

- `/admin/trade-ideas`
- `/admin/trade-ideas/daily`
- `/admin/trade-ideas/daily/create`

Validate Daily DRAFT -> PUBLISHED -> ARCHIVED manually before full regression.

Do not record PASS until local or CI execution actually succeeds.
