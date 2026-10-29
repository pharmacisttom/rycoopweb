# PR Officer user management and mandatory MFA

Branch: `codex/pr-officer-user-mfa-management`, target: `Test`.
Dependencies: Router normalization PR #10, Staff list/error-state PR #11. Review the PR-officer commit separately from these ancestors. No merge/deployment is automatic.

## Audit and implementation

- Existing StaffManagementController and StaffInvitationService already provide invitation create/revoke/resend and status listing. `/admin/users` now exposes a PR-only form using the existing CSRF-protected, Super-Admin-only endpoints. The role ID comes from the server's `pr_officer` role. Missing role fails closed.
- The existing service previously revoked pending duplicate invitations automatically and could replace all roles/reactivate an existing account on acceptance. Duplicate pending/account emails are now rejected. Acceptance only provisions a new account; existing Google subjects cannot be reassigned by an upsert. Accepted invitations cannot reopen. Invitation mutations and required AuditService writes share a transaction; audit failure rolls back the mutation. MySQL renewal/revocation lock the same invitation row as acceptance, and create/renew serialize duplicate checks on a common role row. Renewal invalidates the old token. An explicit expired token does not fall back to another email invitation.
- LoginPage preserves the invitation token when opening the existing Google OIDC flow. New PR accounts use that flow and the existing TOTP QR, six-digit confirmation and one-time recovery flow. PR password login is denied, and PR MFA enrollment remains mandatory even if global staff policy switches are off. Google disabled/misconfigured means no alternative PR password access.
- `PrOfficerPolicy` restricts runtime PR permissions to approved content capabilities without modifying the permission catalog, role mappings or existing accounts. Existing `content.create/publish` grants provide announcement create/edit/publish compatibility; news.view provides announcement list access. These relationships are explicit and do not grant finance, members, users, roles, permissions, sessions or restore access. The generic enterprise KPI dashboard is also excluded for PR; the content workspace is its dashboard. Other roles and Super Admin behavior remain intact.
- Legacy `/staff` finance/member operations and LED routes previously allowed PR through broad staff role lists. PR is now excluded from those groups and corresponding React routes; news, FAQ, public-content and workspace routes remain available according to permissions.
- `/admin/workspace` shows PR news status, announcements and recent content dates from authenticated APIs. Activity galleries and slideshows reuse the news/gallery editor from the earlier PR, not another media store. FAQ and approved site-content links reuse existing modules. No fake metrics or timers/polling.
- The existing AnnouncementController now serves authenticated JSON list/create/update routes as well as its legacy views. It sanitizes rich text, validates date/status/links, checks publish permission separately and keeps the original table and AuditService. No separate announcement database or duplicate CRUD service. Existing attachments and metadata are retained when editing server records.
- Invitation links are shown only after creation/renewal, kept in component memory, and hidden explicitly. They are **not emailed automatically**; the administrator delivers them over an approved channel. The link is a bearer credential and should not be copied to public tickets/logs.

## Files

Policy/authentication: `PrOfficerPolicy.php`, `Auth.php`, `AccessService.php`, `AdminControlCenterService.php`, Auth/GoogleAuth/Mfa controllers, LoginPage.

Staff UI/workspace: `PrOfficerInvitations.jsx`, `PrOfficerWorkspace.jsx`, AdminControlCenterPage, AdminWorkspacePage, App routes.

Invitation/content API: StaffInvitationService, AnnouncementController, config/routes.php; AuditService adds an optional strict flag used only for invitation transactions.

QA: PrOfficerWorkflowTest, PrOfficerBrowserTest + fixture, existing security/admin/staff browser assertions, CI workflow.

## Verification and acceptance

Run from repository root:

```powershell
php tests/PrOfficerWorkflowTest.php
php tests/StaffRouteDispatchTest.php
php tests/PermissionBehaviorTest.php
php tests/MfaWorkflowTest.php
php tests/MfaRateLimitTest.php
php tests/DualControlSecurityTest.php
php tests/SecurityRegressionTest.php
php tests/RequestSecurityTest.php
php tests/WorkflowRoutesTest.php
php tests/AdminControlCenterTest.php
php tests/NewsWorkflowTest.php
php tests/NewsGalleryTest.php
node tests/WorkflowFrontendTest.mjs
node tests/StaffPageIntegrationTest.mjs
node tests/PrOfficerBrowserTest.mjs
node tests/AdminUIBrowserTest.mjs
npm run build
composer validate --no-check-publish
```

Local result: PR Officer workflow **58 checks passed**; Staff route **337**, RBAC **18**, MFA **19**, MFA rate limit **25**, dual-control **72**, security **72**, request security **5**, workflow routes **387**, control center **44**, news **11** and gallery **23** passed. Frontend Workflow, real PHP-session staff browser, PR browser at 390/768/1440 and the existing admin suite (**195 responsive screenshots**) passed. React production build, changed-file PHP syntax, Composer validation and diff whitespace checks passed.

PHP tests use SQLite memory fixtures and never the configured MySQL database. Invitation tests run actual controllers/routes, CSRF/role/MFA guards, transactional provisioning and audit writes. Google email inputs in these tests represent already-verified OIDC claims; no live Google exchange is claimed. PR presentation tests use synthetic responses; the separate staff browser suite uses an actual isolated PHP Super Admin session. Screenshots: `storage/tmp/pr-officer-qa/` (390/768/1440). A slow screenshot capture was retried with animations disabled and a bounded 60-second capture timeout.

Acceptance:

1. Super Admin can create a PR invitation, see its expiry/status, renew or revoke it; a one-time link remains visible until hidden.
2. Duplicate, expired, replayed, mismatched and account-conversion attempts fail; create/accept are audited without tokens, TOTP secrets or OTP values.
3. PR Google login requires enrollment then verification; recovery codes are shown only to the enrolling officer, never the staff roster.
4. PR can manage only approved content; user/role/finance/member/restore/session operations return denial. Super Admin keeps existing access.
5. Workspace uses actual API responses and shows a visible failure state on errors; it does not refresh automatically.

## TEST release runbook

1. Review dependency PRs #10/#11 and this PR; approve the combined commit before release. Do not modify active TEST files in place.
2. Build an immutable release from the approved commit with `npm ci`, `npm run build`, and `composer install --no-dev --prefer-dist --no-interaction`. Run required CI checks first.
3. Read-only prerequisite checks: existing users/roles/permissions/role_permissions, staff_invitations, user_google_identities, user_mfa_recovery_codes and important_announcements schemas must match their existing migrations. `pr_officer` must already exist with approved content grants. Report missing prerequisites; **do not rerun migration 025** or seed the database. No new migration is included or required by this change.
4. Use a separate TEST database, existing TEST HTTPS/Basic Auth, approved Google callback and existing encryption keys. Never overwrite `.env`, APP_KEY or MFA key. Keep 019/020 ON HOLD and Backup Restore Execution disabled.
5. Switch only the approved TEST release, serving backend/frontend from the same revision. Check canonical Staff GET, CSRF failures and the Users error state before invitation UAT.
6. Human UAT uses a dedicated authorized TEST Super Admin and a new synthetic PR account: create invite, accept with the matching Google account, scan QR, confirm TOTP, securely save recovery codes, verify news/gallery/FAQ/announcements and forbidden APIs, then revoke TEST sessions as authorized. Do not alter live staff accounts. No credentials/OTP in chat or evidence screenshots.
7. Production is a separate approval. No deployment or merge was executed by this development task.

## Recovery procedure

Lost authenticator: use the existing MFA recovery page with an unused recovery code over HTTPS; each code is consumed atomically. With no authenticator or recovery code, stop login and verify identity via the cooperative's authorized security process. This change does not add an admin MFA-disable/reset bypass. Administrators must never request the secret/QR/OTP, reuse invitation acceptance to reset MFA, or change encryption keys as account recovery.

## Rollback

Switch TEST back to the previous immutable backend/frontend release together. No down migration, account deletion or key rotation. Code rollback does not undo invitations/content legitimately written during UAT; revoke unused TEST invitations using the approved API and separately review any UAT content cleanup. Rolling back this security ceiling removes its protections, so restrict TEST access until a corrected build is approved.

## Remaining release checks

Interactive Google OIDC + authenticator UAT on HTTPS TEST is intentionally pending, per the user's instruction to finish local QA first. Local fixture results do not prove VPS configuration, live callback delivery or live schema readiness. SMTP delivery and an administrator MFA-reset workflow are not introduced. Existing role editor preserves unsupported permission mappings; this change does not resynchronize role tables.
