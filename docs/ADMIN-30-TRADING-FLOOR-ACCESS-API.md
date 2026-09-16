# ADMIN-30 — Trading Floor Reader Access API

## Purpose

This endpoint is for authorized Trading Floor hardware/readers only. It is separate from the customer mobile bearer-token API.

Fingerprint biometric templates must remain inside the biometric device/provider. TradeX receives only the device-side user reference required to match the protected credential reference already assigned to a Trading Floor customer.

## Endpoint

`POST /api/v1/trading-floor/access/scan`

## Required headers

- `Accept: application/json`
- `Content-Type: application/json`
- `X-TradeX-Device-Code: <provisioned device code>`
- `X-TradeX-Device-Token: <one-time provisioned secret>`

The raw device token is shown once when a reader is provisioned or rotated. TradeX stores only an HMAC hash of that token.

## Request body

```json
{
  "event_id": "reader-generated-unique-event-id",
  "credential_type": "NFC",
  "credential_reference": "raw-scanned-card-uid",
  "direction": "ENTRY"
}
```

For fingerprint readers:

```json
{
  "event_id": "reader-generated-unique-event-id",
  "credential_type": "FINGERPRINT",
  "credential_reference": "device-side-user-reference",
  "direction": "ENTRY"
}
```

### Fields

- `event_id`: unique per physical scan. Reusing the same event ID on the same device returns the original decision instead of creating a second access event.
- `credential_type`: `NFC` or `FINGERPRINT`.
- `credential_reference`: NFC UID or fingerprint device-side user reference. Never send a biometric image/template.
- `direction`: `ENTRY` or `EXIT`.

## Successful authentication response

A valid reader receives HTTP 200 even when physical access is denied. The hardware must use `data.decision` to open, warn, or keep the door locked.

```json
{
  "success": true,
  "data": {
    "event_id": "reader-generated-unique-event-id",
    "decision": "GRANTED",
    "reason_code": "ACCESS_OK",
    "message": "Access granted.",
    "occurred_at": "2026-09-16T16:00:00+00:00",
    "replayed": false,
    "client": {
      "client_code": "TXF-12345678",
      "full_name": "Customer Name",
      "status": "ACTIVE",
      "floor_code": "FLOOR-01",
      "desk_code": "D-12",
      "package_code": "STANDARD"
    },
    "access": {
      "direction": "ENTRY",
      "method": "NFC",
      "device_code": "ENTRY-READER-01"
    },
    "commission": {
      "has_overdue": false,
      "overdue_invoices": 0,
      "days_overdue": 0,
      "earliest_due_date": null,
      "outstanding_by_currency": {}
    }
  }
}
```

## Decisions

- `GRANTED`: physical access may be opened.
- `WARNING`: access may be opened, but the reader/operator should surface the warning. This is used for warning-only commission enforcement, active grace periods, and allowed exits with overdue commission.
- `DENIED`: do not open entry access.

## Important reason codes

- `ACCESS_OK`
- `UNKNOWN_CREDENTIAL`
- `CREDENTIAL_REVOKED`
- `CLIENT_NOT_ACTIVE`
- `FLOOR_MISMATCH`
- `COMMISSION_OVERDUE_WARNING`
- `COMMISSION_OVERDUE_BLOCKED`
- `COMMISSION_GRACE_ACTIVE`
- `COMMISSION_GRACE_EXPIRED`
- `ADMIN_FORCE_ALLOW`
- `ADMIN_FORCE_DENY`
- `EXIT_ALLOWED`
- `EXIT_ALLOWED_WITH_OVERDUE`
- `ACCESS_API_DISABLED`

## Device authentication failure

Missing, revoked, unknown, or invalid reader credentials return HTTP 401.

```json
{
  "success": false,
  "error": {
    "code": "DEVICE_NOT_AUTHORIZED",
    "message": "The Trading Floor device is not authorized."
  }
}
```

## Commission enforcement modes

The administrator can configure:

- `NONE`: overdue commission does not restrict access.
- `WARNING`: overdue commission returns `WARNING` but allows access.
- `BLOCK_AFTER_GRACE`: blocks after the effective grace period. Effective grace is the larger of the global grace days and the customer's configured grace days.
- `BLOCK_IMMEDIATE`: blocks entry as soon as an unpaid invoice becomes overdue.

Paid and waived invoices are not enforced. Disputed invoices are excluded from physical access enforcement while the dispute is unresolved.

## Manual customer override

An authorized administrator can set a customer to:

- `AUTO`
- `FORCE_ALLOW`
- `FORCE_DENY`

Overrides may have an expiry and reason. Active customer status and active credential checks still apply before a force-allow decision.

## Exit behavior

`allow_exit_when_blocked` defaults to enabled. When enabled, a valid active customer/credential may exit even when commission policy or an entry restriction would otherwise block access.

## Privacy and storage

TradeX does not store raw NFC UIDs or fingerprint biometric templates through this workflow. Credential references are HMAC-protected before persistence. Reader tokens are also persisted only as HMAC hashes. Scan attempts keep protected fingerprints and audit metadata, not the raw credential reference.
