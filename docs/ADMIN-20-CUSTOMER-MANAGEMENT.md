# ADMIN-20 — Customer Management & Admin Onboarding

## Scope

ADMIN-20 adds a customer-oriented control surface to the existing TradeX Admin website without changing the frozen mobile registration, payment settlement, premium entitlement, KYC, affiliate, or device-security authorities.

Base `main` SHA when the branch was created:

`88d8eff49d03b6dd02a26266a963d240bc37597b`

Feature branch:

`feature/admin-20-customer-management`

## Admin customer navigation

The Admin sidebar exposes a dedicated Customers section when the signed-in administrator has the relevant permission:

- Customer Dashboard
- All Customers
- Add Customer
- Pending Onboarding
- Pending Accounts
- Free Customers
- Premium Customers
- Suspended / Blocked
- Recently Joined

The existing internal `User` model and `admin.users.*` detail/status/device routes are retained for compatibility. User-facing Admin wording is changed to Customer where appropriate.

## Security boundary for Add Customer

`Add Customer` does **not** directly create a row in `users`.

It creates a `customer_onboarding_invitations` pre-onboarding record only.

An admin-created invitation does not create or control:

- a customer password;
- email or WhatsApp verification state;
- a TOTP secret or confirmed TOTP state;
- a trusted device or Sanctum session;
- an ACTIVE customer account;
- PREMIUM account type or entitlement;
- payment, settlement, refund, payout, or commission state.

The customer must still complete the normal TradeX registration and security flow. Premium access remains controlled by the existing verified payment/entitlement flow.

ADMIN-20 does not send an email or WhatsApp invitation by itself. It records and tracks the intended onboarding identity; outbound invitation delivery can be integrated separately without weakening the frozen registration authority.

## Invitation identity protection

A pending invitation stores normalized email and WhatsApp plus separate SHA-256 active fingerprints. Nullable unique indexes prevent two active pending invitations from concurrently claiming the same email or the same WhatsApp number.

The service also rejects an invitation when:

- a registered TradeX customer already uses the email or WhatsApp number;
- another pending invitation already uses the email or WhatsApp number;
- the supplied referral code is not owned by an ACTIVE TradeX customer.

When an invitation is matched, expired, or moved to identity conflict, its active fingerprints are cleared so historical records can remain without blocking legitimate future onboarding.

## Reconciliation

Pending invitations are safely reconciled against the normal `users` table when the customer dashboard/onboarding queue is viewed.

- neither email nor WhatsApp matches a registered customer: invitation remains `PENDING`;
- email **and** WhatsApp both resolve to the same registered customer: invitation becomes `MATCHED`;
- only one identity field matches, or email and WhatsApp resolve to different customer accounts: invitation becomes `CONFLICT`;
- expiration time passes before matching: invitation becomes `EXPIRED`.

The strict two-field match prevents a partial identity match from silently attaching an admin onboarding record to the wrong customer.

Reconciliation never changes customer password, account type, entitlement, payment, KYC, affiliate, or device state.

## Sensitive notes and audit

`internal_note` is encrypted at rest using Laravel's encrypted cast.

The note content is never rendered in the onboarding table; the UI only shows whether a note is recorded.

Invitation audit events contain safe operational metadata only and do not copy the customer email, WhatsApp number, internal note, credentials, OTPs, or TOTP secrets into the audit payload.

Relevant audit actions:

- `CUSTOMER_ONBOARDING_INVITATION_CREATED`
- `CUSTOMER_ONBOARDING_MATCHED`
- `CUSTOMER_ONBOARDING_MATCH_CONFLICT`

## RBAC

Existing read permission:

`users.view`

New invitation permission:

`users.invite`

`users.invite` is not added to normal delegated roles by default. `SUPER_ADMIN` receives all seeded permissions through the existing role seeder behavior. Delegation to another role should be explicit through Roles & Permissions.

## Configuration

Optional environment values:

```env
TRADEX_CUSTOMER_INVITATION_DAYS=7
TRADEX_CUSTOMER_RECENT_DAYS=30
```

Invitation days are runtime-bounded to 1–30 days. Recent-customer view is runtime-bounded to 1–365 days.

## Local update commands

From the project root:

```powershell
git fetch origin
git checkout feature/admin-20-customer-management
git pull origin feature/admin-20-customer-management
php artisan migrate
php artisan db:seed --class=AdminPermissionSeeder
php artisan db:seed --class=AdminRolePermissionSeeder
php artisan optimize:clear
```

## Targeted validation

```powershell
php artisan test tests/Feature/Admin/Customers/CustomerManagementTest.php
php artisan test tests/Feature/Admin/Customers/CustomerOnboardingConflictTest.php
php artisan test tests/Feature/Admin/AdminRbacDenialTest.php
php artisan test tests/Feature/Admin/AdminRouteHardeningTest.php
```

After targeted tests pass, run the full regression suite before PR/merge/freeze.

This document records intended behavior only. Test execution results must be recorded from the actual local/CI run and must not be assumed from this document.
