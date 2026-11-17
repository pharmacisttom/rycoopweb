# RYCOOPWEB Frontend Rebuild — Production-first Baseline (RC01)

## Decision (17 November 2026)
**The live production frontend is the sole visual/functional baseline.** Do not use the TEST frontend as the design starting point; its look/behavior may not match what members currently see. Development happens in an isolated worktree and TEST environment only until a formally accepted release.

This plan follows Admin RC06 UAT acceptance. Keep completed AdminLTE staff improvements intact while rebuilding the public/frontend experience.

## Gate 0 — identify the exact running production artifact (blocking)
1. Run `bash scripts/inventory-production-frontend-readonly.sh` on VPS as `coopadmin`. It reads Production Nginx configuration and hashes live frontend assets, creating a report in the operator's HOME. This **does not** change production, TEST, PHP, DB, or Nginx.
2. Identify production document root, immutable release, built `index.html`, CSS and JS hash fingerprint, and actual source commit **if a .git worktree exists**.
3. If no source SHA is recoverable, **stop**: do not choose an unrelated branch by name. Find the deployed release's source archive or verified matching build; request a sanitized source-only export if necessary. Never copy `.env`, upload directories, database, logs, SSH keys or user financial files.
4. Freeze and record `PROD_BASELINE_SHA` (or release source archive SHA256), `PROD_BUILD_FINGERPRINT`, collection timestamp and approved list of public pages.
5. The earlier GitHub `release/integrated-all-modules-20261116` branch is **not proven to be the current production runtime** and differs substantially from `Test`. It cannot substitute for this check.

## Gate 1 — isolate and develop from production snapshot
- Create a dedicated `feature/frontend-prod-baseline-*` branch rooted at the **verified production source**, never a blind reset of `Test`.
- Carry forward approved backend security changes/contract definitions from TEST as small, reviewed commits. Keep ADMIN RC06 shell and vendor scoped CSS isolated; never overwrite production frontend source with old TEST HTML/CSS.
- Preserve existing public content and images; compare API contracts and media URL behavior before integration.
- Separate frontend visual changes from member financial data migrations or feature toggles.

## Page inventory — compare before changing
- Homepage / hero / navigation / announcements / events / popups
- About, committee terms, personnel, products, rates, welfare
- News, articles, gallery, media, document downloads and search
- Public contacts, services, accessibility, SEO/canonical URLs
- Login flows: member access, PDPA acknowledgment, dividend/tax statements, account status
- Footer, policy pages, mobile navigation and loading/error/empty states

For each page record: live PRODUCTION screenshot at desktop 1440, tablet 768, mobile 390; visual DOM/component source; TEST screenshot; REST API endpoint and response contract; what differs; approved change; owner; pass/fail and evidence references.

## Gate 2 — nonregression and security
- Validate member login → mandatory PDPA acknowledgement → authorized dividends and financial-data rendering, masked PII, protected APIs, session timeout and logout; staff RBAC/MFA and audit logs unaffected.
- **No feature flag activation by a frontend rebuild** and no automatic Production DB migration.
- Browser QA on public routes with 1440/1024/768/390 viewports, navigation, content parity, no 404, console errors, layout shifts, dark mode if present, web performance and WCAG keyboard/focus.
- SEO: canonical tags, title/description, sitemap, robots, OG tags, valid semantic heading order and redirects; compare to current production to prevent regression.
- Real API integration UAT with TEST database; static/CI route tests alone are insufficient for actual dividends or authenticated flows.

## Gate 3 — single coordinated release
- Promote only after Admin RC06 and production-baseline frontend have separate signoff, full integration UAT, green CI and freeze.
- Capture Production database+file backups and release/config inventory; verify Nginx reverse-switch/rollback and PHP-FPM/ports; record immutable Git SHA.
- Deploy a **new** release under `production-releases/` only through approved deployment guard. Smoke-test key public pages, assets and member workflows without exposing private data.
- Roll back if mismatches, failed security checks, financial-data discrepancies or missing assets occur.

## Current status
- Admin RC06 passes the user's six stated UAT checks on TEST.
- The active production frontend source release **has not yet been established**. Do not claim parity or begin UI changes to the wrong source until Gate 0 succeeds.
- Production, frontend and member data remain unchanged by the inventory task.
