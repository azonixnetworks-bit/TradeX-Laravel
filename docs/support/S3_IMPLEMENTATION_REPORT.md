# S3 Laravel Support Implementation Report

## Branch

The hardening work originated on `codex/s3-laravel-support-finish`, based on the support implementation already present on `main`. Confirm the deployed revision and migration status from the target environment during each release.

## Implemented

- Additive MySQL/SQLite-compatible support schema with UUID API identities, monotonic references, indexes, FKs, private attachment metadata, feedback cycles, FAQ, SLA, agent presence and notification outbox.
- Customer REST API under `/api/v1/support` protected by existing Sanctum + trusted-device middleware.
- Server-authoritative ownership, priority, state transitions, idempotency and atomic agent claim.
- Persistent ticket/live-chat shared thread, stable priority/deadline queue position, independent customer/agent TTL presence, customer timeout, agent disconnect requeue and customer/admin cursor polling.
- Private MIME/signature-checked attachment upload, retry idempotency, message-history visibility and authorized stream download.
- Existing admin auth/TOTP/RBAC reused with deny-by-default controller checks, support roles/permissions, valid landing route and permission-gated sidebar.
- Functional Blade queue, ticket conversation, public replies, internal notes, claim, lifecycle, presence, category and SLA settings.
- Idempotent queued customer/team email outbox. Support mail is disabled by default.
- Scheduler sweep for stale agents/customers and first-response/resolution SLA breach escalation.
- Row-locked lifecycle transitions with correct before/after audit state, locked customer replies and duplicate-key recovery for concurrent ticket creation.
- Generic admin notifications contain no customer subject, reference or reply body at the shared notification boundary.
- Optional idempotent daily super-admin support summary schedule (disabled until mail transport is verified).

## Flutter compatibility

The local Flutter S3 UI is present on `main`, but `lib/features/support/data/support_repository.dart` still sets `phaseOneAvailable = false` and contains no support API calls. Backend contract is therefore documented but Flutter end-to-end integration is **not verified**. No Flutter code was changed in this Laravel task.

## Verification

- `php artisan route:list --path=support --json`: 32 support routes registered once, including admin presence and cursor-event routes.
- `vendor/bin/pint --test` on every changed PHP file and `git diff --check`: passed.
- `php artisan test tests/Feature/Support tests/Unit/Support/SupportSlaClockTest.php`: 18 tests, 87 assertions, passed.
- Full `php artisan test` after this hardening pass: 1,304 tests; 1,275 passed and 29 failures in the unrelated Screener area. No failure names the support implementation, and the targeted support regression suite passes after the changes.
- Test database migrations ran through Laravel `RefreshDatabase` on in-memory SQLite. The configured local MySQL development database was audited before mutation, contained no support tickets/messages/chats/attachments, and the additive hardening migration ran as batch 89. No production database or external environment was touched.

Coverage includes unauthenticated denial, trusted-device customer flow, owner-only ticket access, internal-note non-disclosure, idempotent ticket/message/chat/attachment creation, stable queue/no fake ETA, feedback/reopen, private attachment owner scope and MIME rejection, admin deny-by-default, online/offline agent queue claim behavior, end-to-end admin/customer event polling, transition audit correctness, customer timeout/agent requeue and configurable business-hour SLA calculation.

## Production readiness gaps

- WebSocket broadcasting is not configured/tested; secure REST cursor sync is the supported transport.
- External SMTP/provider delivery is not staged; `MAIL_MAILER=log` must not be represented as customer delivery.
- Flutter repository still requires wiring to this contract and device testing.
- Holiday calendars and malware scanning provider are not configured.
- Customer support updates create idempotent records in the existing in-app notification/receipt pipeline and invoke its configured push fan-out. Live push delivery still depends on the existing `TRADEX_PUSH_ENABLED` provider configuration and registered trusted-device tokens.
- Production release still requires the migration, seeding, worker/scheduler and smoke-test sequence in `S3_DEPLOYMENT.md`; source control state alone is not proof of a successful live deployment.
