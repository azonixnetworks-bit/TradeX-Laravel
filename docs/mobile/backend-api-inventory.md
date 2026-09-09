# Current Mobile API Inventory

Source: frozen `routes/api.php` at backend SHA `d955d7d4671fcfb83670559dd5b7ff94f3c85b4e`.
Prefix: `/api/v1` (deployment base may already include `/api`).
Protected routes use `auth:sanctum` + `tradex.mobile`.

## Public (2)
- `POST /v1/register`
- `POST /v1/login`

## Session (2)
- `POST /v1/logout`
- `GET /v1/me`

## Profile (5)
- `GET /v1/profile`
- `PUT /v1/profile`
- `POST /v1/profile/change-password`
- `GET /v1/profile/devices`
- `DELETE /v1/profile/devices/{session}`

## Our Channel (2)
- `GET /v1/our-channel`
- `POST /v1/our-channel/join-telegram`

## Education (6)
- `GET /v1/education`
- `GET /v1/education/modules/{educationModule}`
- `GET /v1/education/videos/{video}`
- `POST /v1/education/videos/{video}/heartbeat`
- `GET /v1/education/videos/{video}/assignment`
- `POST /v1/education/videos/{video}/assignment/whatsapp`

## Trade Ideas (2)
- `GET /v1/trade-ideas`
- `GET /v1/trade-ideas/{tradeIdea}`

**Total current mobile API routes: 19.**

## Gaps / holds
- No `/v1/affiliate` mobile route is present in current `routes/api.php`; UI navigation can exist, but network behavior must wait for a separately frozen API contract.
- No mobile screener route is wired from `routes/api.php`; cTrader Screener is HOLD and customer UI is COMING SOON.
- Payment, notification and copy-trading customer APIs are not assumed from non-API/admin/web routes. Each must be inventoried and explicitly frozen before client wiring.
