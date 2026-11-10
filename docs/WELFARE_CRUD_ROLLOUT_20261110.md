# RYCOOP Welfare CRUD – Controlled rollout

Branch: `feature/welfare-crud-isolated-20261110` (based on repository default branch Test)

## Scope
- Uses existing `welfare` table; no migrations required for current schema.
- New admin: `/app/admin/welfare`, access limited to `super_admin` by backend role middleware; CSRF required on POST.
- New endpoints: `GET /api/admin/welfare`; `POST /api/admin/welfare`; `POST /api/admin/welfare/{id}/update`; `POST /api/admin/welfare/{id}/delete`.
- Public endpoint `GET /api/public/welfare` reads `welfare`, filters `active` and non-deleted.
- Public Welfare React view loads the real API (no mock fallback).
- New items are saved inactive by default; deletes are soft deletes.

## Protection
- Do not modify member login/PDPA/MFA or dividend controllers/tables.
- Production currently has separate Nginx welfare-hold and member-hold configurations. Do not remove either as part of this module release.
- Backend publication via `status=active` is not equivalent to public go-live: Nginx public welfare hold must remain until explicit approval.
- Database changes must be restricted to the `welfare` table; prohibit access to `members`, `users`, `member_dividends`.
- Do not deploy while member launch is pending without independent validation.

## Required checks before merging/deploying
1. Check PHP syntax with `php -l` for new controller and modified controller/routes.
2. Check frontend `npm ci && npm run build` in isolated working copy.
3. Validate router registration of `/api/admin/welfare` with no duplicate routes.
4. Login via staff Admin TOTP; verify unauthenticated and non-super_admin receive 401/403 on admin Welfare API.
5. Confirm CSRF rejects invalid POST; confirm invalid/duplicate slug fails safely.
6. Test create inactive, edit, activate, deactivate, soft-delete; ensure old/absent version returns 409.
7. Confirm public API only includes active non-deleted items, and fails closed on database outage.
8. Verify existing Member Login and Dividend API untouched, Production flags remain as-is.
9. Verify Nginx blocks public Welfare and Member URLs as before.
10. Record baseline & postdeploy `SELECT COUNT(*) FROM member_dividends WHERE year=2569` and authenticated member checks.
11. Record audit events for Welfare operations before production approval; this initial branch still needs audit-log integration.
12. Confirm precise production `SELECT COUNT(*) FROM welfare`; information_schema.TABLE_ROWS=8 is only an estimate.

## Current limitations
- This is an initial implementation, not a tested/released package.
- Audit log integration and end-to-end tests have not yet been implemented.
- Publication switch remains controlled by the existing Nginx welfare hold. No automatic removal.
- No test credentials or real member personal data should be committed.
