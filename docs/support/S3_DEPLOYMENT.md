# TradeX S3 Support Deployment

## Configuration

Add environment values without committing secrets:

```dotenv
TRADEX_SUPPORT_TIMEZONE=Asia/Colombo
TRADEX_SUPPORT_DISK=private
TRADEX_SUPPORT_MAX_ATTACHMENT_KB=10240
TRADEX_SUPPORT_MAX_ATTACHMENTS=5
TRADEX_SUPPORT_AGENT_PRESENCE_TTL_SECONDS=90
TRADEX_SUPPORT_REOPEN_DAYS=7
TRADEX_SUPPORT_MAIL_ENABLED=false
TRADEX_SUPPORT_NOTIFICATION_QUEUE=support-notifications
TRADEX_SUPPORT_SLA_SWEEP_ENABLED=true
TRADEX_SUPPORT_DAILY_SUMMARY_ENABLED=false
TRADEX_SUPPORT_DAILY_SUMMARY_TIME=08:30
```

Set `TRADEX_SUPPORT_MAIL_ENABLED=true` only after `MAIL_MAILER` is a working non-`log` provider, the sender is verified, and staging receipt/reply tests pass. Credentials stay in environment/server secret storage.

## Staged release

1. Back up the database and private storage metadata.
2. Deploy application code and run `php artisan config:cache`.
3. Review SQL on staging; run `php artisan migrate --force`. Never use `migrate:fresh` on production.
4. Run `php artisan db:seed --class=SupportSystemSeeder --force`, then the normal RBAC seeders/deployment seeding policy so roles receive support permissions.
5. Ensure `storage/app/private/support` is writable and inaccessible from the public web root.
6. Start a dedicated worker, for example `php artisan queue:work --queue=support-notifications,default --tries=3 --backoff=30 --max-time=3600`, supervised and auto-restarted.
7. Ensure scheduler cron runs every minute: `* * * * * php /path/artisan schedule:run >> /dev/null 2>&1`.
8. Verify `/admin/support` as agent, manager and superadmin; verify deny paths with a non-support role.
9. On staging, use a trusted mobile device token to create/retry/list/reply/upload/chat/reconnect/feedback/reopen.
10. Verify real mail provider delivery separately. Automated `Mail::fake`/array transport is not proof of external delivery.

## Realtime

REST cursor sync is production-capable. WebSockets are infrastructure-blocked because this deployment has no verified persistent Reverb/provider process, broadcast configuration or TLS proxy. If added, run a persistent supervised server, authorize private channels, monitor connection limits and retain REST history as source of truth.

## Operations

- `php artisan tradex:support:sweep` expires stale presence, requeues disconnected chats and creates idempotent SLA breach events.
- `php artisan tradex:support:daily-summary` queues one deduplicated daily operations summary per active super administrator. Enable its schedule only after mail transport verification.
- Monitor `jobs`, `failed_jobs`, `support_notification_deliveries`, storage usage and SLA event counts.
- Keep attachment malware scanning disabled only if the operational risk is explicitly accepted; integrate a scanner before allowing higher-risk formats.

## Rollback

Application rollback is safe before new writes. After support data exists, do not run migration `down()` because it deletes support history. Roll back code while retaining tables, or export/archive support records first. Disable new traffic and queue workers before any schema rollback.
