# Admin device security

Every admin, including Super Admin, is allowed one MOBILE and one DESKTOP browser installation. An additional device in an occupied category is blocked after successful password/TOTP authentication. Existing approved devices remain usable. Blocked devices show **Contact Super Admin** and cannot automatically become trusted when a slot is freed.

The Super Admin dashboard includes **Admin Devices & Sessions**, showing every admin (paginated), approved mobile/desktop slots, distinct signed-in devices, signed-in sessions, blocked devices and last activity. **View Devices** opens the device/session details and approve/revoke controls. The same link is available from each admin's account details. Only Super Admin sees these cross-admin details.

Sessions are tracked by a hash of the session ID, never the session credential itself. They expire with the configured idle/absolute admin timeouts and end on logout, revocation or session rotation. Closing a tab/browser does not send a reliable logout event; a recently closed browser may remain listed until timeout. Multiple sessions in one browser are counted separately from registered devices. No old unbound sessions are automatically trusted during rollout: they must sign in again, so the previously open browsers cannot all keep their access.

In **Admin Users → Devices**, only an active Super Admin can inspect devices, revoke a previous device, or approve a blocked replacement. Every change requires a reason and is audited. Delegated permissions do not grant these actions. Revocation invalidates all sessions bound to that device on their next request. Approval requires a new login and does not revive old sessions. Logout does not free the device slot.

## Browser limitations

Device identity uses a random server-generated, encrypted, HttpOnly cookie, with its hash stored in the database. Registration happens only after required authentication factors pass. Existing sessions without a binding must sign in again. A different browser/profile, private mode or cleared cookies counts as a new device. A mobile/tablet user agent selects MOBILE; other browsers select DESKTOP. User agents are reported metadata and can be spoofed. This is a browser-installation limit, not hardware attestation: copied cookies cannot be distinguished from the original browser. IMEI, MAC address and hardware serial numbers are not available to websites. The UI and alerts show the available IP, user agent (browser/OS), category, internal device ID and time without inventing unavailable details.

## Rollout

Back up the production database and deploy the code with the application in maintenance mode. Run `php artisan migrate --force` before accepting admin traffic. Set `TRADEX_ADMIN_DEVICE_SECURITY_ENABLED=true`, then run `php artisan optimize` and restart queue workers. Existing admins must sign in again; their first successful login in each category claims that slot. Verify the intended Super Admin desktop first and then their mobile. Do not enroll unfamiliar devices during rollout.

The feature defaults to enabled. PHPUnit disables it for legacy tests that use `actingAs`; dedicated device tests explicitly enable it and exercise real login/session checks.

## Email and WhatsApp alerts

Blocked-attempt email goes to active admins with the active SUPER_ADMIN role, including the affected admin if applicable. Role authority is checked again before queued email delivery. WhatsApp uses an explicitly configured list of Super Admin-owned numbers; no number is inferred from a customer profile. Populate the following values manually:

```dotenv
TRADEX_ADMIN_SECURITY_WHATSAPP_RECIPIENTS=+94771234567
TRADEX_ADMIN_SECURITY_WHATSAPP_TEMPLATE=tradex_admin_device_security
TRADEX_ADMIN_SECURITY_WHATSAPP_LANGUAGE=en_US
```

The sender reuses `META_WHATSAPP_GRAPH_VERSION`, `META_WHATSAPP_PHONE_NUMBER_ID` and `META_WHATSAPP_ACCESS_TOKEN`. Create a separate approved utility template with exactly one text body parameter, for example: `TradeX admin security alert: {{1}}. Review the device in Admin Users.` Do not reuse the authentication/OTP template. Meta decides template approval and categorization.

A ready-to-submit template specification is in [templates/admin-device-security.json](templates/admin-device-security.json). It uses `tradex_admin_device_security`, English (US), one positional body parameter, and fictional example data. This file is a draft, not evidence that Meta has created or approved the template. Submit it in the correct WhatsApp Business account and wait for APPROVED status before testing delivery.

Provider reference: [Meta's template-message API](https://www.postman.com/meta/whatsapp-business-platform/request/o65u5m5/send-message-template-text).

After entering credentials and the exact approved template name/language in the server `.env`, run:

```sh
php artisan config:cache
php artisan queue:restart
php artisan tradex:whatsapp-security-alert-check
```

The check displays only PASS/MISSING results, never tokens or recipient numbers. It is offline and does not prove Meta approval or delivery. Keep real tokens and recipient numbers out of Git. Have an authorized Super Admin verify receipt during a controlled blocked-device login; inspect the incident's WhatsApp delivery status. Do not manufacture a blocked-device incident just to send a test alert.

Configure production SMTP and a working Laravel queue worker. Each recipient/channel has a database delivery record. Missing WhatsApp recipients are shown as FAILED, not silently treated as sent. Provider failures do not unblock login. Jobs retry up to three times; ambiguous provider timeouts may result in duplicate alerts. Repeated denied attempts for the same device/reason are deduplicated for one hour. ACCEPTED only indicates transport/provider acceptance, not receipt.

Alerts contain the admin identity, device ID, category, observed IP, reported browser/OS string, UTC time and denial reason. Full recorded user-agent details remain on the Super Admin device page; WhatsApp summaries are capped at 1,000 characters. Tokens, passwords, TOTP codes and device-cookie values are never included.

If every trusted Super Admin browser is lost, the server owner can use the audited recovery command `php artisan tradex:admin-device-revoke <super-admin-email> <device-id> --reason="Lost trusted browser"` to free a slot. This requires server shell access and is not a web-accessible bypass. The former device remains revoked; signing in with correct credentials from a new browser is still required.
