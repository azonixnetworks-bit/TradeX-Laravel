# TradeX Mobile Phase Roadmap

Each phase follows: feature branch -> implementation -> Flutter analyze/test/build -> changed-files/contract audit -> PR -> CI/review -> merge -> exact main SHA -> freeze.

1. **MOBILE-01 Foundation** — Flutter app shell, exact five-tab navigation, environment/config boundary, architecture/docs lock, CI; no Laravel contract changes.
2. **MOBILE-02 Identity & Device Security** — registration/login/TOTP/session/device handling using frozen auth APIs and secure token storage.
3. **MOBILE-03 Home & Our Channel** — home composition, entitlement summary surfaces available from frozen contracts, Our Channel; Screener remains COMING SOON.
4. **MOBILE-04 Learn** — education list/modules/video heartbeat/assignment WhatsApp/progress UX with protected-content rules.
5. **MOBILE-05 Trade Ideas** — Long Term/Daily UX, market/stock-region navigation, detail and approved sharing, premium gating.
6. **MOBILE-BE-01 Affiliate API Contract** — only if still absent: separately review/freeze customer affiliate `/api/v1` contract without altering frozen commercial rules.
7. **MOBILE-06 Affiliate** — wire Affiliate tab only after MOBILE-BE-01 is frozen.
8. **MOBILE-07 Profile & Security** — profile update, password, trusted devices, payout details/broker-account surfaces only where APIs are frozen.
9. **MOBILE-BE-02 Commerce/Copy/Notification Contract Audit** — inventory/freeze any missing customer APIs before client wiring; no invented endpoints.
10. **MOBILE-08 Payments & Entitlements** — lifetime USD 79 checkout/status/history/detail/invoice against frozen payment contracts.
11. **MOBILE-09 Copy Trade** — IB/referral broker onboarding, submission, PENDING/VERIFIED/activation states against frozen server gates.
12. **MOBILE-10 Notifications & Deep Links** — notification center, routing and safe link handling.
13. **MOBILE-11 Hardening** — content protection, watermarking, screenshot/recording deterrence where platform-supported, secure storage review, logging/privacy, accessibility/performance.
14. **MOBILE-12 Release** — Android/iOS signing config review, release builds, store readiness, regression and production checklist.

### HOLD lane
`cTrader Screener integration`: HOLD until explicitly reopened. No mobile network integration during the above phases; customer sees COMING SOON.
