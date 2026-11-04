# TradeX User Activity — Phase 1 rollout

This is an additive, opt-in mobile analytics pipeline. It does **not** replace the existing `audit_logs` system or constitute proof of financial, security, or trading actions.

## Components

- MySQL/SQLite migration: `user_activity_events`, unique `(user_id,event_id)`.
- Authenticated/device-bound `GET /api/v1/activity/config` returns availability and policy version.
- Authenticated/device-bound `POST /api/v1/activity/events` accepts up to 25 validated, allowlisted events, 202 on acceptance.
- `TRADEX_ACTIVITY_ENABLED=false` is the safe server default. The API returns `enabled=false` and refuses ingestion (503, `ACTIVITY_DISABLED`) until enabled **and** schema is present.
- Client never submits the authoritative user ID; it is derived from the authenticated session.
- User-specific Super Admin activity panel remains at `/admin/users/{user_id}`.
- `tradex:user-activity:prune` keeps at most 45 days and runs daily via Laravel scheduler.
- `tradex:user-activity:check` checks schema, routes and server switch without printing customer information.

## Deployment sequence (operator actions, not performed by this PR)

1. Merge **Laravel first**, keep `TRADEX_ACTIVITY_ENABLED=false` in production.
2. Back up database as appropriate; deploy code; run:
   ```sh
   php artisan migrate --force
   php artisan route:list --path=api/v1/activity
   php artisan schedule:list
   php artisan tradex:user-activity:check --allow-disabled
   ```
3. Ensure existing Laravel scheduler cron/worker is running. The `00:45 UTC` activity-prune command only runs when `schedule:run` is executed by the deployment scheduler.
4. Review/publish privacy notice and retention disclosure for analytics, and verify relevant authorization rules.
5. Enable on the **server only**, in deployment secrets/environment: `TRADEX_ACTIVITY_ENABLED=true`. Refresh Laravel config using your normal deployment process (for example `php artisan config:cache`) and run:
   ```sh
   php artisan tradex:user-activity:check
   ```
6. Merge/release the compatible Flutter build. The mobile feature flag defaults to true but records **nothing** until the authenticated readiness API reports `enabled=true` with `policy_version=1`; it has an emergency opt-out build flag `--dart-define=TRADEX_ACTIVITY_TRACKING_ENABLED=false`.
7. On a staging test account/device, verify `GET /api/v1/activity/config` returns `data.enabled=true`, then open Home/Learn/Trade Ideas, background/resume, and verify `POST /api/v1/activity/events` returns HTTP 202. Check `user_activity_events` records are tied to that authenticated account and shown only on its Super Admin user detail page.
8. Simulate offline/reconnect, token expiration, logout and account switching. Confirm no other user's events are sent, and user actions remain unaffected by analytics errors.

## Emergency rollback

Set `TRADEX_ACTIVITY_ENABLED=false`, refresh config. New ingestion is immediately blocked, readiness returns false, and compatible Flutter clients pause tracking. The old security audit system continues working. Existing activity rows are not destroyed by disabling; the 45-day pruning schedule still applies.

Do not merge/release blindly: the scoped tests and actual staging/live verification must pass. GitHub commits alone do not prove migration, scheduler execution, production configuration, data arrival, or privacy compliance.
