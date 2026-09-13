# TradeX Mobile Security Center — UI Contract

Status: FROZEN CONTRACT
Backend: Laravel API
Target clients: iOS + Android
Theme rule: preserve the approved TradeX mobile theme, typography hierarchy, card radius, glow treatment, spacing system and bottom navigation component. Security screens must not introduce a separate visual language.

## 1. Backend endpoints

All endpoints require the existing authenticated mobile session middleware.

- `GET /api/v1/auth/security/center`
- `GET /api/v1/auth/security/notifications`
- `GET /api/v1/auth/security/history`
- `POST /api/v1/auth/security/events/{eventId}/review`
- `GET /api/v1/auth/devices`
- `DELETE /api/v1/auth/devices/{deviceUuid}`
- Existing current-device revoke/logout endpoints remain unchanged.

Do not display or persist access tokens, refresh tokens, raw Laravel/session identifiers, TOTP secrets, installation hashes or device public keys in the UI.

## 2. Security Center screen

Screen title: `Security Center`

Recommended section order:

1. Header
2. Urgent security banner, only when `data.banner.visible === true`
3. Security summary card
4. `Your Devices`
5. `Security Alerts`
6. `Recent Activity`
7. Security help text

The existing application bottom navigation must remain exactly the same component used elsewhere in the app.

## 3. Screen states

### 3.1 Loading

Show skeleton placeholders for:

- summary card
- two device-card placeholders
- three event-row placeholders

Do not show stale `No alerts` or `No devices` text while the request is loading.

### 3.2 Healthy / normal

Condition:

- `summary.has_pending_review === false`
- `banner.visible === false`

Summary status label: `Account Protected`

Supporting text: `Your trusted devices and recent security activity look normal.`

Use the existing positive/success treatment from the TradeX theme. Do not invent a new navigation style.

### 3.3 Review required

Condition:

- `summary.has_pending_review === true`
- `summary.highest_pending_severity === "MEDIUM"`

Status label: `Security Review Needed`

Primary CTA: `Review Now`

Tap opens the review detail for `banner.event_id` when present. If the banner event is unavailable, open the alerts list filtered to pending reviews.

### 3.4 Suspicious / urgent

Condition:

- `summary.has_pending_review === true`
- `summary.highest_pending_severity === "HIGH"`

Status label: `Suspicious Login Detected`

Primary CTA: `Review Login`

The UI should be prominent but not use panic language. Risk signals are indicators, not proof that the account is compromised.

### 3.5 Empty history

If `notifications` and `history_preview` are empty, show:

Title: `No security activity yet`

Body: `New device sign-ins and important security changes will appear here.`

### 3.6 Error

For transient/network/server errors:

Title: `Security status unavailable`

Body: `We could not load your latest security information.`

CTA: `Try Again`

Never interpret a network failure as `Account Protected`.

### 3.7 Authentication/session failure

For authentication/session/device-revoked responses, follow the global app authentication handling. Do not leave the user on a stale Security Center with cached actions enabled.

## 4. Summary card mapping

Use `GET /api/v1/auth/security/center`.

Map:

- `summary.trusted_devices` → trusted devices count
- `summary.max_trusted_devices` → device limit
- `summary.pending_review_count` → pending review badge/count
- `summary.has_pending_review` → review-required state
- `summary.highest_pending_severity` → visual severity

Suggested compact copy:

`Trusted Devices` — `{trusted_devices} of {max_trusted_devices}`

`Pending Reviews` — `{pending_review_count}`

Do not infer risk from the trusted-device count alone.

## 5. Home/dashboard security banner

The mobile home/dashboard may consume only the `banner` block from the Security Center response.

When `banner.visible === false`, render no security banner and preserve the normal dashboard spacing.

When visible:

- severity from `banner.severity`
- title from `banner.title`
- body from `banner.message`
- event identifier from `banner.event_id`
- supported action currently: `REVIEW_SECURITY_EVENT`

Tap behavior:

- navigate to Security Review screen for `banner.event_id`
- never perform YES/NO directly from the dashboard banner

## 6. Trusted device cards

Source: `data.devices` from Security Center or `GET /api/v1/auth/devices`.

Card content:

- device name
- device model when available
- platform
- OS version when available
- app version when available
- last login time
- last seen time
- last login IP when available
- `Current Device` badge when `is_current === true`
- trusted state

Device card ordering follows backend order. Do not resort in the client unless a future contract explicitly changes this.

### Current device

Do not show `Remove Device` for the current device.

Current-device sign-out/revoke uses the existing dedicated current-device action, not `DELETE /auth/devices/{uuid}`.

### Other trusted device

Show destructive secondary action: `Remove Device`.

Confirmation sheet:

Title: `Remove this trusted device?`

Body: `This device will be signed out and will need full verification before it can be trusted again.`

Actions:

- `Cancel`
- `Remove Device`

On success, refresh `GET /security/center` before updating counters locally.

### Device limit reached

If login returns `DEVICE_LIMIT_REACHED`, route user to the trusted-device management screen and explain that one existing device must be removed before adding another trusted device.

Do not automatically evict an older device.

## 7. Security alert rows

Source: `data.notifications`.

Display:

- `title`
- `message`
- formatted `occurred_at`
- device name/platform when present
- IP when present
- severity badge
- review state when `review.required === true`

Severity mapping:

- `INFO` → neutral/informational
- `MEDIUM` → review/warning
- `HIGH` → urgent/security warning

Do not display raw action codes as the primary customer-facing title. They may be retained in debug telemetry only.

## 8. "Was this you?" review screen

Open only for reviewable events where:

- `review.required === true`
- normally `review.status === "PENDING"`

Screen title: `Review Login`

Show:

- event title
- event message
- occurred time
- device name/model/platform
- IP address when available
- risk signals translated into friendly labels

Friendly signal labels:

- `NEW_DEVICE` → `New device`
- `NEW_IP` → `New network or IP`
- `NEW_PLATFORM` → `New platform`
- `NEW_DEVICE_MODEL` → `New device profile`

Question:

`Was this you?`

Actions:

- primary positive: `Yes, it was me`
- destructive: `No, secure my account`

### YES request

`POST /api/v1/auth/security/events/{eventId}/review`

```json
{
  "response": "YES"
}
```

Success state:

Title: `Login confirmed`

Body: `This activity has been marked as recognized.`

Then refresh Security Center.

### NO request

```json
{
  "response": "NO"
}
```

Before submitting, show confirmation:

Title: `Report this login as unauthorized?`

Body: `TradeX will revoke the affected device and its active sessions when it is safe to do so.`

If the backend rejects the action because the suspicious event belongs to the current device, show the backend-safe recovery message and direct the user to the account recovery / other trusted-device flow. Do not bypass this protection client-side.

Success state:

Title: `Device access revoked`

Body: `The affected device and its active sessions were revoked.`

Then clear sensitive cached screen data and refresh Security Center.

## 9. Already-reviewed state

When:

- `review.status === "CONFIRMED"`, show `Confirmed by you`
- `review.status === "REPORTED_UNAUTHORIZED"`, show `Reported as unauthorized`

Hide YES/NO buttons when `review.can_respond === false`.

If the API returns `SECURITY_EVENT_ALREADY_REVIEWED`, refresh the event/center instead of treating it as a fatal app error.

## 10. Security history screen

Source: `GET /api/v1/auth/security/history`.

Default page request may use the backend default limit. The current backend maximum is 50.

Timeline rows should show customer-facing title/message, severity, time and optional device/IP context.

The app must not reconstruct audit payloads or assume fields that are absent from the safe API response.

## 11. Notifications screen

Source: `GET /api/v1/auth/security/notifications`.

Current backend contract returns `read_tracking: false`.

Therefore:

- do not show unread dots/counts as authoritative server state
- do not implement `mark as read` API calls
- a local visual "seen this session" state may be used only if clearly treated as ephemeral UI state

Persistent unread/read tracking requires a future backend notification table/contract.

## 12. Pull-to-refresh and post-action refresh

Security Center supports pull-to-refresh.

After any of these actions, refetch the center endpoint:

- YES review
- NO review
- remove trusted device
- current device security action when the session remains valid

Do not manually guess the resulting pending count, highest severity or banner visibility.

## 13. Time display

API timestamps are ISO-8601 values. Mobile clients should render in the device's local timezone while retaining the original instant.

Admin website Sri Lanka display-time rules do not require forcing customer mobile devices to Asia/Colombo.

## 14. Accessibility and interaction

- destructive actions require confirmation
- buttons must remain reachable with large text enabled
- severity must not be communicated by color alone
- device/current-state badges require text labels
- loading indicators must not block system back navigation
- after a failed mutation, keep the original event/device visible and show a retryable error

## 15. Navigation contract

Recommended routes/screens in the mobile application:

- `SecurityCenter`
- `TrustedDevices`
- `SecurityAlerts`
- `SecurityHistory`
- `SecurityEventReview(eventId)`

The exact navigation framework is owned by the mobile codebase. Existing TradeX bottom-navigation visuals and behavior are frozen and must be reused, not recreated.

## 16. Acceptance checklist

Implementation is complete only when all of the following hold:

- normal state never shows an alert banner
- pending MEDIUM/HIGH review shows the correct banner
- YES removes the event from pending review after refresh
- NO revokes the affected non-current device and sessions
- current-device protection cannot be bypassed
- current trusted device cannot be removed using the other-device endpoint
- max-two-device behavior remains enforced by backend
- loading is not rendered as an empty state
- network error is not rendered as protected/healthy
- cross-user security activity never appears
- no secret/token/TOTP/installation-hash value is logged or rendered
- server read/unread state is not invented while `read_tracking` is false
- iOS and Android use the same functional states and the approved TradeX visual system
