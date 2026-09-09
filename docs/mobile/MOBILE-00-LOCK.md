# TradeX Mobile Baseline Lock

Locked: 2026-09-09
Backend repository: `azonixnetworks-bit/TradeX-Laravel`
Frozen backend baseline: `d955d7d4671fcfb83670559dd5b7ff94f3c85b4e`
Mobile stack: Flutter single codebase for Android + iOS.

## Non-negotiable invariants

1. Existing Laravel contracts, migrations, auth/security behavior, entitlements, payments, affiliate rules, education rules, Trade Ideas rules and copy-trading rules are not changed by mobile phases unless a separately reviewed backend contract phase is explicitly approved.
2. Bottom navigation order is exactly: **Home | Learn | Trade Ideas | Affiliate | Profile**.
3. cTrader/customer Screener integration is **HOLD**. Mobile displays **Screener — COMING SOON** only. Existing backend screener code is not deleted or rewired.
4. Mobile API authentication follows the existing Laravel Sanctum/mobile middleware contract. Device-bound and security controls remain server-authoritative.
5. Education assignment submission is not uploaded in-app. The existing WhatsApp action contract is preserved.
6. Premium education and Trade Ideas remain entitlement-gated/server-authoritative.
7. Affiliate mobile networking is not invented until a frozen `/api/v1` affiliate contract exists.
8. One feature branch per MOBILE phase. Required gate: branch -> code -> build/test -> diff/audit -> PR -> review/CI -> merge -> exact merge SHA -> freeze.
9. No unrelated backend file changes in a MOBILE phase.

## Architecture lock

- Feature-first Flutter structure with strict `app`, `core`, and `features` boundaries as modules are introduced.
- Presentation state: Riverpod when stateful feature integration begins.
- Routing: `go_router` when auth/deep-link routing begins; bottom-tab state must remain preserved.
- HTTP: Dio with one API client, request IDs/timeouts and typed feature repositories.
- Secrets/tokens: platform secure storage; never SharedPreferences/plain files.
- API base URL: build-time environment (`--dart-define`/flavor), never a production URL hardcoded in widgets.
- Server response keys and authorization rules are adapted client-side; mobile must not require backend contract drift.
- Android and iOS stay behaviorally equivalent from the same Dart feature layer.

MOBILE-01 intentionally uses only Flutter SDK primitives to establish a dependency-light shell. Integration packages are added only in the phase that needs them and are pinned/audited then.
