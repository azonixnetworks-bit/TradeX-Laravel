# TradeX S3 Support Architecture

## Scope

The support engine reuses the existing `users`, `admin_users`, Sanctum device-bound customer authentication, admin session/TOTP boundary, RBAC tables, private filesystem and Laravel queue. It does not introduce a second identity system.

## Data model

- `support_departments`, `support_categories`: enabled routing configuration and versioned customer intake schema.
- `support_sla_policies`: editable business-minute first-response and resolution targets per department/priority.
- `support_tickets`: UUID API identity, monotonic `TX-SUP-000001` reference, customer owner, routing, status, assignment and SLA timestamps.
- `support_ticket_messages`: durable ordered conversation. `visibility=internal` is filtered from every customer endpoint.
- `support_ticket_attachments`: private-disk object metadata. Raw paths are never returned.
- `support_chat_sessions`: chat queue state linked to the same ticket and message history.
- `support_agent_profiles`, `support_agent_departments`: capacity and TTL heartbeat presence linked to `admin_users`.
- `support_ticket_events`: immutable transition/audit evidence.
- `support_feedback`: one record per ticket resolution cycle.
- `support_notification_deliveries`: idempotent mail outbox with retry/failure evidence.
- `support_faqs`, `support_canned_replies`: published knowledge and admin response helpers.

## State machines

Ticket transitions are enforced in `SupportTicketService`:

`open -> in_progress -> waiting_for_customer -> resolved -> closed`

Permitted recovery transitions include `waiting_for_customer -> in_progress` and policy-window reopen of `resolved|closed -> in_progress`. Assignment is independent from public status.

Chat states are `queued`, `assigned`, `active`, `ended`, `cancelled`. Customer and agent heartbeats are tracked independently. `tradex:support:sweep` cancels a stale queued customer session, ends a stale assigned/active customer session and returns a stale assigned/active agent session to the queue. Durable messages are never discarded.

## Queue and concurrency

- Reference allocation uses the `support_sequences` row under a database transaction and row lock.
- Ticket/message/chat idempotency keys have database unique constraints.
- Only one active customer chat is returned by `startChat`; the transaction locks the current active set before creating.
- Agent claim obtains a row lock and refuses a ticket already owned by a different agent. A support agent can claim only while enabled, recently online, below capacity and authorized for the ticket department; managers with `support.tickets.assign` retain their explicit override.
- Queue position is derived from the same stable priority, first-response deadline, queue time and ID order used by the admin queue. ETA is deliberately `null` because no statistically valid estimator exists.
- Ticket queue order is priority, first-response deadline and creation time. SLA sweep applies aging escalation from low/normal to high without inventing a critical priority.
- Ticket creation, customer replies and attachment retries are protected by database uniqueness constraints in addition to application checks, so concurrent duplicate requests converge on the existing record.

## SLA calendar

`SupportSlaClock` calculates minutes inside each department's configured `business_hours` JSON and timezone (default fallback: Monday-Friday 09:00-17:00 in `Asia/Colombo`) and skips closed periods. Holiday calendars are not yet configured; this limitation must be considered before customer-facing SLA commitments.

## Authorization

Customer routes require `auth:sanctum` and `tradex.mobile`. Every nested ticket, chat, message and attachment lookup scopes by the authenticated `user_id`; foreign UUIDs return 404.

Admin controllers explicitly call `AdminUser::hasPermission()` and do not trust the legacy pass-through permission middleware. `support.tickets.view_assigned` is limited to the current assignment plus eligible unassigned live-chat queue items in the agent's departments; `support.tickets.view_all` grants team queue access. Internal notes require `support.notes.write`. Admin cursor polling applies the same ticket scope on every request.

Permissions: `support.view`, `support.tickets.view_assigned`, `support.tickets.view_all`, `support.tickets.reply`, `support.tickets.assign`, `support.tickets.manage`, `support.notes.write`, `support.chat.participate`, `support.chat.supervise`, `support.sla.manage`, `support.settings.manage`, `support.reports.view`, `support.agents.manage`, `support.categories.manage`.

## Attachments

PNG, JPEG, WebP and PDF are accepted, server MIME/signature checked, capped at 10 MiB and five files per request by default. Files are randomly named, stored on the private disk and streamed only after customer-owner or admin-ticket authorization. If `message_uuid` is omitted, the upload is attached to the latest customer message so it remains visible in cursor history. An optional upload `Idempotency-Key` is converted into per-file keys and protected by a database unique constraint. SVG/HTML and arbitrary executables are rejected. An external malware scanner is not configured and is a production gap.

## Realtime boundary

MySQL is authoritative. Customer `/api/v1/support/live-chat/{chat}/events` and admin `/admin/support/{ticket}/events` cursor endpoints provide reconnection-safe ordered delta synchronization. The Laravel admin UI polls every three seconds and sends presence heartbeats separately; Flutter must adopt the same contract when its repository is wired. This repository has no installed/configured Laravel broadcasting driver or persistent WebSocket process, so WebSocket transport is intentionally not claimed as live. A future Reverb/Pusher deployment must authorize private ticket channels for the customer owner or assigned/authorized admin and maintain a distinct admin-only internal-note channel.
