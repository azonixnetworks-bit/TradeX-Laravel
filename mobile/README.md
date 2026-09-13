# TradeX Mobile (Flutter)

This folder contains the TradeX mobile application UI for Android and iOS.

## Branch rule

All mobile UI work belongs on `feature/mobile-app-ui`.
Do not commit mobile UI work to `main` or `feature/admin-device-sessions`.

## Current implementation

- Shared premium black/gold theme tokens
- Frozen five-item bottom navigation
- Screen 01 Home / Dashboard UI scaffold
- Responsive Flutter layout for Android and iOS
- Static preview data only; Laravel API wiring is intentionally deferred to the next implementation step

## First-time platform bootstrap

Flutter must be installed locally before running the app.

From the repository root:

```powershell
cd mobile
flutter doctor
flutter create --platforms=android,ios --org com.azonixnetworks --project-name tradex_mobile .
flutter pub get
flutter run
```

After `flutter create`, review generated files before committing them to make sure the existing custom `lib/` and `pubspec.yaml` remain intact.

## Production data rule

The current Home values are visual preview data that match the frozen reference design. They are not authoritative market, course, notification, or analytics data. Production values must come only from approved TradeX Laravel APIs.

## Planned API wiring

- `GET /api/v1/auth/me`
- `GET /api/v1/trade-ideas`
- `GET /api/v1/education/courses`
- `GET /api/v1/our-channel/videos`

Existing authentication, `auth:sanctum`, `tradex.mobile`, premium-access, and device-session rules remain authoritative and must not be bypassed in Flutter.
