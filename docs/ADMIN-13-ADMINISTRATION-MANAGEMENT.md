# ADMIN-13 — Administration Management

Base main:

`9b2dfe67aacc24310975eba65827dc376dd3a052`

Feature branch:

`feature/admin-13-administration-management`

## Scope

ADMIN-13 activates the existing Administration skeleton:

- Admin Users;
- Roles;
- Permission catalog.

No database migration is required.

## Existing foundations reused

- `admin_users`
- `admin_roles`
- `admin_permissions`
- `admin_user_roles`
- `admin_role_permissions`
- existing `AdminUser`, `AdminRole`, `AdminPermission`
- existing source-controlled Admin role/permission seeders
- existing `audit_logs`
- existing `admin.auth`

## Admin User rules

- `admins.view` — list/inspect admin accounts.
- `admins.create` + `roles.manage` — create delegated admin and assign initial roles.
- `admins.update` — update non-Super-Admin name/email.
- `admins.update` + `roles.manage` — change non-Super-Admin role assignments.
- `admins.disable` — enable/disable another non-Super-Admin account.
- self status changes are blocked.
- seeded Super Admin is read-only/protected.
- SUPER_ADMIN cannot be assigned through ADMIN-13.
- at least one active delegated role is required.
- no admin delete endpoint.
- no password reset endpoint.
- initial password is accepted only at account creation and never audited/rendered afterward.

## Role rules

- `roles.view` — inspect roles and permission catalog.
- `roles.manage` — create/manage custom roles.
- system roles are source-controlled and immutable in the UI.
- SUPER_ADMIN cannot be created or modified.
- custom role code is immutable after creation.
- custom role metadata/status/permission assignments are audited.
- permission catalog is read-only; ADMIN-13 does not create/rename/delete permission codes.
- no role delete endpoint.

## Audit actions

- `ADMIN_ACCOUNT_CREATED`
- `ADMIN_ACCOUNT_UPDATED`
- `ADMIN_ACCOUNT_ENABLED`
- `ADMIN_ACCOUNT_DISABLED`
- `ADMIN_ACCOUNT_ROLES_CHANGED`
- `ADMIN_ROLE_CREATED`
- `ADMIN_ROLE_UPDATED`
- `ADMIN_ROLE_ENABLED`
- `ADMIN_ROLE_DISABLED`
- `ADMIN_ROLE_PERMISSIONS_CHANGED`

Passwords are never included in audit payloads.

## Transaction and locking

All ADMIN-13 mutations use database transactions. Target admin/role rows and selected role/permission rows are reloaded under row locks before mutation.

## Important current boundary

Disabling an admin blocks future password login. ADMIN-13 does not add forced invalidation of an already-authenticated admin browser session because the existing admin authentication stack does not yet have a dedicated admin-session inventory/revocation model. That hardening should be designed separately rather than faked.

## Routes

Admin Users:

- `GET /admin/administration/admins`
- `GET /admin/administration/admins/create`
- `POST /admin/administration/admins`
- `GET /admin/administration/admins/{adminUser}/edit`
- `PATCH /admin/administration/admins/{adminUser}`
- `PATCH /admin/administration/admins/{adminUser}/status`
- `PATCH /admin/administration/admins/{adminUser}/roles`

Roles:

- `GET /admin/administration/roles`
- `GET /admin/administration/roles/create`
- `POST /admin/administration/roles`
- `GET /admin/administration/roles/{adminRole}/permissions`
- `PATCH /admin/administration/roles/{adminRole}`
- `PATCH /admin/administration/roles/{adminRole}/status`
- `PATCH /admin/administration/roles/{adminRole}/permissions`

Permission catalog:

- `GET /admin/administration/permissions`

## Frozen boundaries

ADMIN-13 does not modify:

- AdminUser/AdminRole/AdminPermission models;
- RBAC migrations;
- role/permission seeders;
- SuperAdminSeeder;
- Admin login controller;
- ADMIN-11 Security;
- ADMIN-12 Audit;
- User/KYC/Payments/Payouts/Finance/Auth API modules.

## Validation

```bash
php artisan optimize:clear
php artisan route:list --name=admin.administration
php artisan test tests/Feature/Admin/Administration/AdministrationManagementTest.php
php artisan test
git diff --check
git status --short
```
