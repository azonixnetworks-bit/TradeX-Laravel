TradeX Laravel — API-11 Profile & Account Settings
==================================================

Purpose
-------
Complete the mobile Account Settings layer without duplicating the existing
profile/contact/financial APIs.

Adds
----
- consolidated settings overview
- account security status
- trusted-device list
- TOTP-protected remote-device revocation

Reuses
------
- ProfilePresenter
- TotpService
- AccessTokenService
- UserDevice / UserRefreshToken
- AuditLog
- tradex.mobile middleware

Install
-------
1. Current branch must be:
   feature/api-11-profile-account-settings

2. Extract this ZIP into the TradeX Laravel project root.

3. Run:
   php artisan optimize:clear
   php artisan route:list --name=api.v1.profile.settings
   php artisan test --filter=AccountSettings
   php artisan test
   git diff --check
   git status

No migration is required.

Expected new routes
-------------------
GET   api/v1/profile/settings
GET   api/v1/profile/settings/security
GET   api/v1/profile/settings/devices
PATCH api/v1/profile/settings/devices/{device}/revoke
