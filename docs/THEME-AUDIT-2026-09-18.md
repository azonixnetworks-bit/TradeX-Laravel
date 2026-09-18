# TradeX Premium Admin Theme Audit

Date: 2026-09-18

## Reference

The project-wide admin visual language is based on the Trading Floor Customer Directory screen:

- deep navy / near-black shell
- subtle premium panel gradients
- TradeX gold as the primary action and emphasis colour
- blue active-navigation state
- rounded cards and controls
- low-contrast borders and shadows
- muted blue-grey secondary text
- compact, consistent action controls
- dark table, filter, form and empty-state treatment

## Scope audited

Repository audit covered the complete admin presentation layer:

- 194 Blade views under `resources/views/admin`
- 26 existing admin CSS files under `public/admin/css`
- shared admin shell: sidebar, topbar, content, footer
- standard CRUD screens and shared components
- authentication/security screens
- route-specific modules with their own stylesheets

## Shared theme implementation

### `public/admin/css/project-theme.css`

Global presentation layer loaded after existing admin styles. It standardises:

- application shell and content background
- sidebar and active navigation
- topbar
- hero/page headers
- primary and secondary buttons
- cards, panels and stat cards
- forms, search and filter controls
- tables
- badges and status chips
- pagination
- responsive geometry
- theme-toggle presentation

### Route-specific harmonisation

The audit specifically normalises the custom design systems used by:

- Economic News Intelligence
- Market Clock
- Payment Accounts
- Screener Monitor
- Funded Account Advisor
- Funded No-Trade Zone
- Risk / Reward Calculator
- Trading Routine Planner
- Trading Floor
- Dashboard command-centre surfaces

Custom functional layouts remain owned by their original module stylesheets; the project theme only harmonises visual tokens, borders, surfaces, button language and typography.

### Authentication

`public/admin/css/auth-theme.css` applies the same premium language to:

- Admin Login
- Forgot Password
- Reset Password
- TOTP Challenge

Authentication form actions, routes, CSRF, password behaviour and TOTP behaviour are unchanged.

## Safety guardrails

This theme branch does not modify:

- controllers
- models
- services
- routes
- migrations
- database schema
- API payloads
- RBAC / permissions
- validation
- payment logic
- trading logic
- JavaScript business behaviour

The implementation is limited to CSS and stylesheet references in Blade templates.

## Theme layering

Existing module CSS is preserved. The project theme is loaded last in the authenticated admin layout so it acts as a visual consistency layer without replacing module functionality.

Trading Floor's stronger route-specific theme lock is preserved; its `!important` rules continue to protect the reference design while the global layer brings other modules into the same language.

## Form-control hardening

Global input selectors deliberately exclude submit, button, reset and file inputs so normal buttons and special controls are not accidentally restyled as text fields.

## Merge criteria

Before merging to `main`:

1. branch must remain based on the latest `main`
2. comparison must show no unexpected backend files
3. shared layout must load `project-theme.css`
4. all standalone admin auth screens must load `auth-theme.css`
5. CSS assets must be present on the feature branch
6. final diff must remain presentation-only

