# TradeX Mobile (Flutter)

This folder contains the TradeX mobile application for Android and iOS.

## Branch rule

All mobile UI and Flutter application work belongs on `feature/mobile-app-ui`.
Do not commit mobile work to `main` or admin feature branches.

## Current implementation

- Shared premium black/gold theme tokens
- Frozen five-item bottom navigation
- Screen 01 Home / Dashboard UI scaffold
- Responsive Flutter layout for Android and iOS
- Secure login screen using the existing TradeX Laravel auth contract
- TOTP or recovery-code authentication
- Persistent per-installation UUID for backend device binding
- Android/iOS device metadata submission
- Access and refresh tokens stored with `flutter_secure_storage`
- Single-flight refresh-token rotation to prevent concurrent refresh reuse
- Session restore gate before Home is shown
- Runtime API base URL through `--dart-define`

## First-time setup

Flutter must be installed locally before running the app.

From the repository root:

```powershell
cd mobile
flutter doctor
flutter pub get
```

Run against a local Laravel server on Android emulator:

```powershell
flutter run --dart-define=TRADEX_API_BASE_URL=http://10.0.2.2:8000
```

Run against a local Laravel server on iOS simulator:

```powershell
flutter run --dart-define=TRADEX_API_BASE_URL=http://127.0.0.1:8000
```

For a physical device, use a reachable HTTPS development/staging URL. Production builds must supply the production HTTPS API URL through the build pipeline.

## Authentication contract

Flutter uses the existing backend endpoints and does not bypass Laravel security:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`

Login sends email, password, TOTP or recovery code, persistent installation UUID, platform and device/app metadata. The backend remains authoritative for account status, MFA, trusted-device binding, access-token lifetime and refresh-token rotation.

Passwords and MFA codes are never persisted by the Flutter app. Access and refresh tokens are stored only in platform secure storage.

## Production data rule

Home values that are still visual preview data are not authoritative market, course, notification or analytics data. Production values must come only from approved TradeX Laravel APIs.

## Next API wiring

With secure authentication in place, the next Flutter integration sequence is:

- `GET /api/v1/auth/me`
- `GET /api/v1/trading-floor/membership`
- `GET /api/v1/trading-floor/membership/history`
- `GET /api/v1/trade-ideas`
- `GET /api/v1/education/courses`
- `GET /api/v1/our-channel/videos`

Existing `auth:sanctum`, `tradex.mobile`, premium-access and device-session rules remain authoritative and must not be bypassed in Flutter.
