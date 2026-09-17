# ADMIN-31 — Trading Floor Membership Mobile API

Read-only customer API for the Flutter/mobile application. These endpoints reuse the existing TradeX mobile authentication and hardening middleware.

## Authentication

Send the normal authenticated mobile bearer token used by the rest of `/api/v1`.

The routes require:

- `auth:sanctum`
- `tradex.mobile`
- API hardening middleware
- request throttling

A customer can only read the Trading Floor client linked to their authenticated TradeX `user_id`. No arbitrary client or membership identifier is accepted by these endpoints.

## GET `/api/v1/trading-floor/membership`

Returns the authenticated customer's current open Trading Floor membership. Priority is ACTIVE, then PENDING, then SUSPENDED.

Example shape:

```json
{
  "success": true,
  "data": {
    "enrolled": true,
    "client": {
      "uuid": "...",
      "client_code": "TXF-...",
      "status": "ACTIVE"
    },
    "current_membership": {
      "uuid": "...",
      "membership_number": "TFM-...",
      "status": "ACTIVE",
      "starts_at": "2026-09-01",
      "expires_at": "2026-09-30",
      "due_date": "2026-09-01",
      "billing_cycle": "MONTHLY",
      "auto_renew": false,
      "package": {
        "uuid": "...",
        "code": "PREMIUM",
        "name": "Premium Floor",
        "description": "...",
        "features": []
      },
      "allocation": {
        "floor": {
          "uuid": "...",
          "code": "F1",
          "name": "Main Floor"
        },
        "desk": {
          "uuid": "...",
          "code": "D12",
          "name": "Desk 12",
          "zone": "A"
        }
      },
      "payment": {
        "currency": "LKR",
        "price_amount": 10000,
        "paid_amount": 5000,
        "balance_amount": 5000,
        "status": "PARTIALLY_PAID",
        "history": []
      },
      "access_snapshot": {
        "client_status": "ACTIVE",
        "membership_status": "ACTIVE",
        "final_access_decision_requires_reader_policy": true
      }
    }
  }
}
```

If the authenticated TradeX user is not linked to a Trading Floor client, `enrolled` is `false` and the membership is `null`.

The `access_snapshot` is informational only. The physical reader endpoint remains the authority for final GRANTED / WARNING / DENIED access decisions because reader policy, commission enforcement and manual overrides can also apply.

## GET `/api/v1/trading-floor/membership/history`

Returns paginated membership history for the authenticated customer's linked Trading Floor client.

Optional query:

- `per_page` — 1 to 50, default 20
- `page` — Laravel pagination page

Response includes membership period, status, package, floor/desk allocation, price snapshot and reconciled payment totals. Admin-only notes, audit metadata and other customers are not returned.

## Admin Documents

Admin printable membership documents remain permission-gated web routes:

- Membership invoice: `/admin/trading-floor/memberships/{membership}/invoice`
- Membership payment receipt: `/admin/trading-floor/memberships/{membership}/payments/{payment}/receipt`

## Renewal Reminders

Membership expiry reminders are queued through the existing Trading Floor reminder foundation at:

- 7 days before expiry
- 3 days before expiry
- expiry day

The queue selects WhatsApp first when a WhatsApp number exists, then email, then in-app. Queue generation is deduplicated per membership, reminder type and channel. Provider sending remains separate from reminder intent generation.
