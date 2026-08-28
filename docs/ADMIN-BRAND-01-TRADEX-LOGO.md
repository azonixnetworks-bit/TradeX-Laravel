# ADMIN-BRAND-01 — Official TradeX Logo Integration

## Baseline

Base frozen `main` SHA when this module started:

`1e53fbb0edcddfad51ec9cd41ecf5a6d9960403c`

Feature branch:

`feature/admin-brand-01-tradex-logo`

## Official brand source

The TradeX logo supplied by the owner is the official Admin brand identity for this module.

The brand includes:

- the gold bull;
- the dark bear;
- the TradeX `X` mark;
- the `TRADEX` wordmark;
- the tagline `LEARN. TRADE. GROW.`

Optimized runtime assets are stored locally in the Laravel public tree. No external image host, CDN dependency, or third-party branding service is required.

## Runtime assets

- `public/admin/brand/tradex-logo.webp` — full brand lockup for login, sidebar, dashboard and report headers.
- `public/admin/brand/tradex-favicon.png` — compact transparent icon for favicon, topbar and loader/splash use.

## Admin surfaces

ADMIN-BRAND-01 wires the official brand into:

- Admin login;
- Admin two-factor challenge;
- shared Admin layout;
- sidebar identity;
- topbar identity;
- Dashboard header;
- browser favicon / theme color;
- Admin page loader / splash;
- reusable report / print header component.

The report component is:

`resources/views/admin/components/report-brand-header.blade.php`

It can be included by a report/PDF/print template without duplicating branding markup.

## Loader safety

`public/admin/js/branding.js` removes the loader after the window load event.

A short fail-safe timeout also removes it if another resource stalls. The branding loader therefore must never become an authentication or administration availability dependency.

Reduced-motion preferences disable the logo pulse animation.

## Accessibility

- Meaningful full-logo placements use `TradeX — Learn. Trade. Grow.` alternative text.
- Decorative compact loader marks use empty alternative text.
- The loader exposes a short status label while visible and becomes `aria-hidden` when dismissed.
- Branding remains responsive from mobile Admin screens through desktop layouts.

## Security and frozen-module boundary

This module is presentation-only.

It does not change:

- Admin authentication credentials;
- password verification;
- TOTP verification logic or challenge routing;
- admin session creation;
- RBAC permissions or authorization;
- customer onboarding business rules;
- education rules;
- payment provider configuration;
- payment settlement or webhooks;
- KYC, affiliate, payout, finance, device-security or audit business logic.

The existing Admin login form action, CSRF protection, password fields and TOTP challenge submission contract are retained.

## Local validation

After syncing the feature branch:

```powershell
php artisan optimize:clear
php artisan test tests/Feature/Admin/Branding/AdminBrandingTest.php
php artisan test tests/Feature/Admin/Hardening
php artisan test tests/Feature/Admin/AdminRouteHardeningTest.php
```

Then visually inspect:

- `/admin/login`
- `/admin`
- the Admin sidebar/topbar after login
- Dashboard header
- TOTP challenge when naturally triggered
- mobile-width Admin navigation

After targeted checks pass, run:

```powershell
php artisan test
git diff --check origin/main...HEAD
git status --short
git rev-parse HEAD
```

Do not record a PASS result until the commands have actually been executed locally or in CI.
