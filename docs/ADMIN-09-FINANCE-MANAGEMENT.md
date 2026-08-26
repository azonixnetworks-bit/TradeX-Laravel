# ADMIN-09 — Finance Management

Base main: `31b01139b8e1a23daaff369e2ff9cdce35e49a68`

## Purpose

Provide owner/finance-admin visibility over the frozen payment, affiliate and payout ledgers without creating a second accounting source of truth.

## Finance rules

1. `payments` is the realized sales ledger.
2. `payment_adjustments` records signed later adjustments such as mandatory payment reversals.
3. Processor fees are reported only when a payment row actually contains a fee. Manual Local Bank / USDT rows with `NULL` fee remain **unknown/not recorded**; ADMIN-09 does not invent zero-cost processing.
4. USD, LKR and USDT are never combined into one profit/contribution number without an approved FX source.
5. Affiliate commission expense is accrued when commission is earned.
6. Affiliate commission adjustments modify that accrued expense on their own accounting date.
7. Milestone gifts are accrued when earned.
8. Paid payouts are cash settlement of previously accrued affiliate liabilities and are not deducted a second time from contribution.
9. Finance reconciliation is read-only. Domain corrections must use the owning audited Payments/Payouts/Affiliate workflows.

## Admin routes

- `GET /admin/finance`
- `GET /admin/finance/daily-sales`
- `GET /admin/finance/daily-sales/export`
- `GET /admin/finance/monthly-sales`
- `GET /admin/finance/monthly-sales/export`
- `GET /admin/finance/reconciliation`

## RBAC

Existing permissions are reused:

- `finance.view`
- `finance.reports`
- `finance.reconciliation`

No permission seeder change is required.

## Daily owner report

Command:

```bash
php artisan tradex:finance:daily-sales-report
```

Default behavior reports the previous Finance timezone business day.

Manual date:

```bash
php artisan tradex:finance:daily-sales-report --date=2026-08-26
```

Manual recipient override:

```bash
php artisan tradex:finance:daily-sales-report --date=2026-08-26 --to=owner@example.com
```

Configuration:

- `TRADEX_FINANCE_TIMEZONE=Asia/Colombo`
- `TRADEX_FINANCE_DAILY_REPORT_ENABLED=false`
- `TRADEX_FINANCE_OWNER_REPORT_EMAIL=`
- `TRADEX_FINANCE_DAILY_REPORT_TIME=00:10`

The scheduler runs after midnight and reports the previous completed local business day.

Production requires the normal Laravel scheduler process/cron:

```bash
php artisan schedule:run
```

every minute, or the deployment platform's equivalent scheduler.

## Explicit exclusions

- No FX conversion engine.
- No manual revenue entries.
- No expense-entry bookkeeping subsystem.
- No refund/reversal mutation.
- No payment approval mutation.
- No payout mutation.
- No commission mutation.
- No payroll/staff salary accounting.
- No tax calculation.
- No new database migration.
