# WhatsApp verification for new customer profiles

TradeX already requires WhatsApp OTP during registration and admin-invited customer activation. The admin records the customer’s number in international format (for example `+94771234567`). The customer opens their invitation link and verifies email first. TradeX then sends a six-digit WhatsApp code automatically. The customer enters it before setting their password, authenticator and completing activation. Admins never receive the OTP or mark the number verified on the customer's behalf.

## Production configuration

Create an approved **Authentication** template with a **Copy code** OTP button in Meta WhatsApp Manager. Use the exact template name and language below. If the template includes an expiry footer, match TradeX's configured expiry (10 minutes by default).

Meta reference: [Copy-code authentication templates](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/authentication-templates/copy-code-button-authentication-templates).

Set these values manually in the server `.env` (never commit real credentials):

```dotenv
TRADEX_REG_WHATSAPP_DRIVER=meta
META_WHATSAPP_GRAPH_VERSION=<supported Graph API version, e.g. v26.0>
META_WHATSAPP_PHONE_NUMBER_ID=<sending phone number ID, not WABA ID or phone digits>
META_WHATSAPP_ACCESS_TOKEN=<system user access token with WhatsApp messaging permission>
META_WHATSAPP_OTP_TEMPLATE_NAME=<approved authentication template name>
META_WHATSAPP_OTP_TEMPLATE_LANGUAGE=en_US
META_WHATSAPP_TIMEOUT_SECONDS=10
META_WHATSAPP_CONNECT_TIMEOUT_SECONDS=5
```

Replace every placeholder. Use a registered WhatsApp Business sender and a token authorized for that sender. Email delivery must also be configured because email verification precedes WhatsApp verification. The `log` driver is for local testing only and is rejected in production.

From `/home/cloudvid/tradex-live`, after the code and `.env` changes:

```sh
php artisan config:cache
php artisan tradex:whatsapp-otp-check
```

The check is offline: it reports missing/invalid configuration without printing tokens, phone IDs or template values, and never sends a message. Passing does not prove that Meta accepts the token or that the template is approved. Invalid/missing configuration exits with status 1.

## Verify delivery

Use an authorized test customer's own WhatsApp number in a new invitation. Open the invitation, verify email and confirm that the WhatsApp code arrives. An API message ID means Meta accepted the send request; actual receipt is confirmed by entering the code. A rejected or timed-out send leaves the number unverified and offers resend. There is no automatic HTTP retry that could send duplicate codes.

The default code expiry is 10 minutes, resend cooldown is 60 seconds, and maximum incorrect attempts is 5. Codes are stored as HMACs; replacing a code invalidates its predecessor. No active customer account is created until all onboarding gates pass.

If sending fails, check token authorization, sender registration, exact template language/name and approval in Meta. After changing `.env`, rebuild the configuration cache. During Meta test-sender setup, use a recipient permitted by that setup. Never share access tokens or OTPs in support screenshots.

## Scope

This uses the existing registration API and invitation activation page. It does not send a message just because an admin saves an invitation. Sending begins after the customer verifies email. Existing account contact-change policies are unchanged.
