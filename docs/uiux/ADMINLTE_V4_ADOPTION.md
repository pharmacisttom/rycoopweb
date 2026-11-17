# AdminLTE 4 adoption plan — RYCOOPWEB React Control Center

Status: **UI integration design decision, not a claim that the AdminLTE package has been installed**.

## Verified upstream (2026-11-17)
- Source: https://github.com/ColorlibHQ/AdminLTE
- Latest GitHub release inspected: **v4.10.2** (2026-11-16)
- Package: `admin-lte` (Bootstrap 5 based); MIT license.
- Keep the original MIT notice when redistributing actual AdminLTE assets.

## Decision
Use **AdminLTE v4** as the design-system and layout reference for the React Admin Control Center. Preserve React Router, Vite, existing secure PHP endpoints, RBAC, CSRF and MFA. Do not replace protected routes with AdminLTE's static HTML examples or global JS that manipulates React-owned navigation DOM.

### RC01 (this PR)
- Wider 326px desktop sidebar, collapsible 76px compact mode.
- Site-wide **system tools footer** with RBAC-filtered groups; removed duplicate tool list from sticky sidebar footer.
- Better shared surfaces, typography, spacing, focus, dark-mode tokens and responsive Media Library.
- No destructive media changes, no DB migrations, and no authentication changes.
- Presently *AdminLTE-inspired scoped CSS*, not the npm package.

### RC02 (actual package integration; separate TEST-first change)
1. Pin `admin-lte@4.10.2` (or an explicitly reviewed compatible release) and matching Bootstrap version; commit both `package.json` and the regenerated `package-lock.json` using `npm install --save-exact`.
2. Import AdminLTE v4 CSS **only inside the protected React Admin route boundary** using a scoped or isolated CSS build to prevent public-site styles being overridden. Validate Bootstrap/reboot collisions.
3. Map shell to documented v4 `app-wrapper`, `app-header`, `app-sidebar`, `app-main`, `app-footer` structure **after** the styles have been isolated; React owns all dropdown/accordion state and route navigation.
4. Add contract and Playwright layout QA at 1440/1024/768/390px, light/dark, keyboard focus/ESC, sticky footer, RBAC filtered menus and no horizontal overflow.
5. Test existing modules' forms, media upload/preview, dashboard, tables, session/MFA before any further merge.
6. Merge into Test after green CI and human screenshot UAT; Production requires explicit canary/rollback gates.

## Non-goals
- Do not copy upstream example HTML/JS wholesale.
- Do not load AdminLTE assets from a third-party CDN at runtime.
- Do not change member/public frontends, PHP server, authentication or database as part of purely visual UI modernization.


## Member portal, dividend checking and other service preservation (mandatory)

This is a visual refactor of the **staff React Admin Control Center only**. Existing member portal screens, all member sign-in/PDPA acknowledgement steps, dividend checking by fiscal year, member data masking, profile, loans, deposits, welfare, tax receipts, finance, public news and other service workflows are **out of scope for destructive changes**.

Critical invariants before merging any AdminLTE-related UI change:
- React member routes `/member/login`, `/member/dividends` and member dashboards remain present and use their previous authentication workflow.
- PHP route `GET /api/member/dividends` remains protected by authentication, member role and MemberMiddleware.
- Member portal `/api/member/*` and `/member/*` keep their existing feature flag and security gates.
- PDPA acknowledgement and auditing requirements remain unchanged; there is no forced new password/MFA change for member access.
- No member record, dividend value, consent record or database schema is migrated/modified by a UI-only PR.
- All admin menu entries including dividend import and member management stay RBAC filtered and visible to authorized roles.
- Every release is evaluated on a disposable CI database, then TEST; Production remains unchanged until member, PDPA, dividends and staff UAT pass.

This release adds a static route-contract regression test in CI; it does NOT represent successful end-to-end member login or live dividend testing.
