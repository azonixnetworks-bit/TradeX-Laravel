# TradeX User Activity Analytics — Phase 2

## Delivery

- Phase 2 is stacked on Phase 1 (`feature/audit-phase1-rollout`) because that PR is not yet merged. Merge Phase 1 first, then rebase/retarget Phase 2 to `main`. Do not deploy Phase 2 before Phase 1.
- Super Admin only: `GET /admin/analytics/user-activity`.
- Read-only timeline, date/customer/module/event filters, daily DAU/sessions/events, module mix, estimated active minutes, and bounded `GET /admin/analytics/user-activity/export` CSV (10,000 rows maximum per request).
- Deep link in `/admin/users/{user_id}` activity panel.
- Adds query indexes only to `user_activity_events`; no payment, user, audit security or journal schemas are modified.
- Session heartbeat is allowlisted in Laravel and sent approximately every 2 minutes while the Flutter app is foregrounded and server tracking is enabled.

## Interpreting metrics

- `Active Users` = distinct users with activity events during the selected UTC calendar days. It is not a count of registered or online users.
- `Sessions` = distinct non-null client-reported session identifiers. It is not a verified authentication session count.
- `Estimated Active Minutes` = sum of consecutive event gaps for each user+session, capped at 180 seconds per gap; no time accumulates through pause/end or across resume/start boundaries. Old clients without heartbeats can undercount or estimate poorly. It is NOT exact screen time.
- `Daily Engagement` is computed from retained `user_activity_events` at request time; no new daily materialized table in Phase 2.
- Aggregate cards summarize *all events* within selected dates and optional customer filters. Module/event filters only affect timeline and export.
- Dates are UTC. Interactive query windows are restricted to 31 inclusive calendar days within the last 45 days. Historical activity older than 45 days is pruned by Phase 1's scheduler.

## Security and reliability

- Only `AdminUser::isSuperAdmin()` may view/export data; verified in controller, not only in sidebar visibility.
- Exports are read-only, rate-limited, non-cacheable and limited to 10,000 rows. No raw journal text, trading positions, chat content, secrets or payment details are ever exported.
- The original `/admin/audit` security explorer, payment pipeline, authentication and customer workflows remain intact.
- Run targeted activity tests, migration, route and smoke checks in staging before production release.
- The Phase 1 backend kill switch `TRADEX_ACTIVITY_ENABLED=false` remains the emergency stop for mobile activity ingestion. Existing rows can still be viewed until retention pruning.

## Operator verification

```bash
php artisan migrate --force
php artisan route:list --path=admin/analytics/user-activity
php artisan test --filter=UserActivity
php artisan tradex:user-activity:check
```

On staging, use a test user to open lessons and trade ideas, wait for at least one foreground heartbeat, background/resume, and confirm session estimates, charts, filters and exported CSV are consistent with the events. Verify ordinary admins are forbidden.

Neither GitHub commits nor CI alone can verify production live deployment, cron scheduling or privacy notice.
