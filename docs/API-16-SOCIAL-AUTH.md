# API-16 Social Login — Google + Facebook + Apple

## Security contract

- Social provider subject (`sub` / Facebook user id), never provider email, is the durable external identity key.
- Existing TradeX accounts are never silently linked by email. The user must sign in normally and explicitly link the provider with fresh TOTP.
- Social login still requires TradeX TOTP and runs through the existing trusted-device binding, access-token, and refresh-token services.
- New social onboarding preserves the frozen TradeX gates: email OTP, WhatsApp OTP, and TOTP are all required before an ACTIVE user is created.
- Apple requires a server-issued one-time nonce challenge. Google nonce enforcement is configurable. Facebook tokens are server-side validated with `/debug_token` against the configured app id.
- Social credentials are never persisted or written to audit logs.

## Endpoints

- `POST /api/v1/auth/social/{google|apple}/challenge`
- `POST /api/v1/auth/social/{google|facebook|apple}/login`
- `POST /api/v1/auth/social/{google|facebook|apple}/onboard`
- existing registration email/WhatsApp/TOTP endpoints continue the onboarding flow
- `POST /api/v1/auth/social/{google|facebook|apple}/onboard/confirm`
- `POST /api/v1/auth/social/{google|facebook|apple}/link` (authenticated + trusted device + TOTP)

Apple challenge purposes: `LOGIN`, `ONBOARD`, `ONBOARD_CONFIRM`, `LINK`.

## Environment

```text
TRADEX_SOCIAL_GOOGLE_ENABLED=false
TRADEX_SOCIAL_GOOGLE_CLIENT_IDS=
TRADEX_SOCIAL_GOOGLE_REQUIRE_NONCE=false

TRADEX_SOCIAL_FACEBOOK_ENABLED=false
TRADEX_SOCIAL_FACEBOOK_APP_ID=
TRADEX_SOCIAL_FACEBOOK_APP_SECRET=
TRADEX_SOCIAL_FACEBOOK_GRAPH_BASE_URL=https://graph.facebook.com

TRADEX_SOCIAL_APPLE_ENABLED=false
TRADEX_SOCIAL_APPLE_CLIENT_IDS=
```

Enable only providers whose production credentials are configured. Apple nonce enforcement is always on.
