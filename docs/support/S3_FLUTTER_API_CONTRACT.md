# TradeX S3 Flutter API Contract

## Verification status

The local Flutter support UI was inspected on its current `main` branch (including commits `2bcf485` and `185c39b`). The screens are complete, but `lib/features/support/data/support_repository.dart` still sets `phaseOneAvailable = false` and makes no support HTTP requests. Consequently the routes below are the canonical backend contract, but Flutter parsing/integration is **UNVERIFIED** until that repository is wired and device-tested. No Flutter files were changed by this Laravel task.

All paths are relative to `/api/v1`, require a bearer token bound to a trusted TradeX mobile device, and return UTC ISO-8601 timestamps.

Successful envelope:

```json
{"success":true,"data":{}}
```

Authentication/security errors use:

```json
{"success":false,"error":{"code":"DEVICE_NOT_TRUSTED","message":"This device is not trusted."}}
```

Validation errors use Laravel's standard `422` JSON `message` and `errors` map. Foreign customer resources return `404` without confirming existence.

## Routes

| Method | Route | Purpose |
|---|---|---|
| GET | `/support/overview` | Real customer counts, TTL agent availability, no fabricated ETA |
| GET | `/support/categories` | Enabled server-configured categories/intake schema |
| GET | `/support/faq?category=&q=&page=` | Published paginated FAQ |
| GET | `/support/tickets?status=&q=&page=` | Current customer's tickets |
| POST | `/support/tickets` | Idempotent ticket creation |
| GET | `/support/tickets/{ticket_uuid}` | Owner-scoped detail |
| GET | `/support/tickets/{ticket_uuid}/messages?after={cursor}` | Public message cursor sync |
| POST | `/support/tickets/{ticket_uuid}/messages` | Idempotent asynchronous reply |
| POST | `/support/tickets/{ticket_uuid}/attachments` | Private multipart attachment upload |
| GET | `/support/attachments/{attachment_uuid}/download` | Authorized private stream |
| POST | `/support/tickets/{ticket_uuid}/reopen` | Policy-window reopen |
| POST | `/support/tickets/{ticket_uuid}/feedback` | One rating per resolution cycle |
| POST | `/support/live-chat/start` | Create/reuse one ticket-backed queue session |
| GET | `/support/live-chat/{chat_uuid}` | Queue/session state |
| POST | `/support/live-chat/{chat_uuid}/cancel` | Cancel queued/active request |
| POST | `/support/live-chat/{chat_uuid}/end` | End customer session without deleting messages |
| POST | `/support/live-chat/{chat_uuid}/heartbeat` | Customer presence heartbeat |
| GET | `/support/live-chat/{chat_uuid}/events?after={cursor}` | Polling/delta fallback |

## Create ticket / start chat

Header: `Idempotency-Key: <stable-client-uuid>` (required).

```json
{
  "category_uuid": "e6f79a91-5df3-4d35-a5d8-f8b67bf48aaa",
  "subject": "Copy trading verification question",
  "description": "My account remains under review after resubmission.",
  "requested_urgency": "normal",
  "intake": {"platform":"cTrader"}
}
```

The server derives customer identity/contact/account type from auth. Client owner IDs and raw broker credentials are not accepted. Customer urgency can request only `low|normal|high`; the server owns final priority.

```json
{
  "success": true,
  "data": {
    "uuid": "...",
    "reference": "TX-SUP-000001",
    "source": "ticket",
    "subject": "Copy trading verification question",
    "status": "open",
    "priority": "normal",
    "category": {"uuid":"...","label":"Copy Trading / cTrader","slug":"copy-trading-ctrader"},
    "assigned_agent": null,
    "first_response_due_at": "2026-10-28T07:30:00.000000Z",
    "created_at": "2026-10-27T09:30:00.000000Z"
  }
}
```

## Messages

Header `Idempotency-Key` is required on POST.

```json
{"message":"Additional safe diagnostic details."}
```

Public response fields: `uuid`, integer `cursor`, `sender_type`, `message`, `created_at`, and authorized attachment metadata. Internal notes never appear.

## Attachments

`multipart/form-data`: `files[]` (1-5) and optional `message_uuid`. Send a stable optional `Idempotency-Key` header when retrying the same upload; the server derives a separate key per file. If `message_uuid` is omitted, files attach to the latest customer message. Allowed server signatures: JPEG, PNG, WebP, PDF. Default max is 10 MiB each. The response is an array exposing only attachment UUID, display name, MIME, byte count and authenticated download URL.

## Live chat

```json
{
  "uuid":"...",
  "ticket_uuid":"...",
  "ticket_reference":"TX-SUP-000002",
  "state":"queued",
  "queue_position":2,
  "eta_minutes":null,
  "agents_online":1,
  "assigned_agent":null,
  "closure_reason":null,
  "queued_at":"..."
}
```

`queue_position` follows priority, first-response deadline, queue time and ID ordering. `eta_minutes` remains null until an evidence-based estimator exists. Flutter should display queue position and an honest asynchronous fallback, never a fixed wait promise. While a chat is active, POST the heartbeat endpoint more frequently than `TRADEX_SUPPORT_CUSTOMER_PRESENCE_TTL_SECONDS` and poll `/events?after=` every three seconds (or on foreground/resume).

## Enums

- Ticket status: `open`, `in_progress`, `waiting_for_customer`, `resolved`, `closed`
- Priority: `low`, `normal`, `high`, `critical` (customer cannot force critical)
- Chat: `queued`, `assigned`, `active`, `ended`, `cancelled`
- Sender: `customer`, `admin`, `system`

## Realtime handoff

Use `/events?after=` after reconnect/background resume, persist `next_cursor`, and de-duplicate by message `uuid`. The event response includes the latest `chat` object, public `events` and `next_cursor`; internal notes are never returned. No WebSocket endpoint is advertised because the production broadcast driver/process/TLS proxy is not installed or tested. When enabled later, private channel authorization must enforce the same owner/assignment rules.
