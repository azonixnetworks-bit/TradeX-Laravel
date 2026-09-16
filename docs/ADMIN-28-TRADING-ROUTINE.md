# ADMIN-28 — Daily Trading Routine Planner

## Ownership model

Customer routine preferences are customer-owned and written only through the authenticated mobile API. Administration is monitoring-only. Customer name, email and WhatsApp destination are sourced from the authenticated TradeX user record and are not accepted from request input.

## Scheduler

Run Laravel's scheduler continuously in production (for example with the standard once-per-minute cron invoking `php artisan schedule:run`). The registered command is:

```bash
php artisan tradex:trading-routine:sweep
```

The command checks each enabled customer's IANA timezone and configured local delivery time. Database unique constraints prevent duplicate daily plans and duplicate WhatsApp delivery records.

## WhatsApp PDF delivery

The module reuses the existing Meta WhatsApp Cloud API graph version, phone-number ID, access token and HTTP timeouts. It does not alter the registration OTP template or OTP delivery service.

Create and approve a separate WhatsApp utility template intended for the daily TradeX plan. The implementation expects a DOCUMENT media header and a static body, so no customer-controlled body text is injected into the template message.

### Meta template specification

Create this as a separate template in WhatsApp Manager. Do not reuse the registration OTP/authentication template.

- Template name: `tradex_daily_trading_plan`
- Category: `UTILITY`
- Language: `English (US)` / `en_US`
- Header type: `DOCUMENT`
- Body variables: none
- Buttons: none required
- Sample header media: upload a non-sensitive sample TradeX Daily Trading Routine PDF when Meta asks for a document example

Recommended body copy:

> Your TradeX Daily Trading Routine is ready. Review the attached PDF before you trade today. Follow your risk limits, session plan, checklist and stop rules. This is a planning and discipline tool, not a trade signal.

Recommended footer:

> TradeX OS — Trading discipline & risk planning

Keep the template operational rather than promotional. The attached PDF contains the customer-specific plan; the WhatsApp template itself remains static and contains no customer name, account balance, trade signal, guaranteed-return language or sensitive account information.

Deployment settings:

```dotenv
TRADEX_TRADING_ROUTINE_ENABLED=true
TRADEX_TRADING_ROUTINE_DELIVERY_WINDOW_MINUTES=180
TRADEX_TRADING_ROUTINE_SWEEP_LIMIT=250
TRADEX_TRADING_ROUTINE_MAX_DELIVERY_ATTEMPTS=3
TRADEX_TRADING_ROUTINE_RETRY_AFTER_MINUTES=15
TRADEX_TRADING_ROUTINE_WHATSAPP_DRIVER=meta
META_WHATSAPP_TRADING_ROUTINE_TEMPLATE_NAME=tradex_daily_trading_plan
META_WHATSAPP_TRADING_ROUTINE_TEMPLATE_LANGUAGE=en_US
```

Existing Meta secrets remain deployment secrets:

```dotenv
META_WHATSAPP_GRAPH_VERSION=
META_WHATSAPP_PHONE_NUMBER_ID=
META_WHATSAPP_ACCESS_TOKEN=
META_WHATSAPP_TIMEOUT_SECONDS=10
META_WHATSAPP_CONNECT_TIMEOUT_SECONDS=5
```

Keep `TRADEX_TRADING_ROUTINE_WHATSAPP_DRIVER=disabled` until the document template is approved and tested. A provider API acceptance is stored as `ACCEPTED`; the module does not falsely label it `DELIVERED` without a delivery-status webhook.

### Approval and activation sequence

1. Create `tradex_daily_trading_plan` in WhatsApp Manager using the specification above.
2. Upload a non-sensitive sample PDF for the DOCUMENT header sample if requested.
3. Submit the template for Meta review.
4. Keep `TRADEX_TRADING_ROUTINE_WHATSAPP_DRIVER=disabled` while the template is pending or rejected.
5. After the exact template name and `en_US` language are approved, set `TRADEX_TRADING_ROUTINE_WHATSAPP_DRIVER=meta` in the production deployment environment.
6. Run `php artisan optimize:clear` after changing deployment environment values.
7. Test with one internal verified WhatsApp account before enabling customer opt-ins broadly.
8. Confirm the persisted provider status is `ACCEPTED` and that the PDF opens correctly in WhatsApp.

## Customer API

- `GET /api/v1/trading-routine`
- `PUT /api/v1/trading-routine/preferences`
- `GET /api/v1/trading-routine/today`
- `GET /api/v1/trading-routine/plans/{uuid}`
- `GET /api/v1/trading-routine/plans/{uuid}/pdf`

All routes require `auth:sanctum` and the existing `tradex.mobile` device/session middleware.

## Admin

- `/admin/trading-tools/trading-routine`
- permission: `trading_routine.view`

The admin page displays automation health, plan counts, provider-accepted submissions, failures, recent plan snapshots and PDF downloads. It does not expose an admin form for changing customer routine preferences.
