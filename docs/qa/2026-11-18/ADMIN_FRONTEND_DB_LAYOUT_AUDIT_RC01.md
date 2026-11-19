# RYCOOPWEB Admin → DB → Public Frontend Contract Audit (RC01)

Status: **Preliminary source-code audit; NOT database-verified or browser-UAT approved. TEST ONLY.**

## Verified paths from source

| Domain | Admin editor | Backend or DB path | Public consumer | Finding |
|---|---|---|---|---|
| Cooperative profile | `/admin/public-content` | `PublicContentService`: `site_settings.public_content_catalog`, `public_content_revisions` | `/api/public/content` → `PublicContentContext` | Defined publication flow, live TEST end-to-end not yet checked |
| Board/staff/advisors/auditors | Same editor (section selector) | Same revision flow | `usePublicInformation()` | Data path defined; verify image URLs and published date |
| Deposit rates / loan rates / products | Same editor (section selector) | Same revision flow; product rates derived from loan-rate records | `usePublicInformation()` plus other home API consumers | Risk of inconsistent sources; reconcile `/api/public/content` vs home-data API and calculator |
| News / FAQ | `/admin/content?tab=news|faqs` | Separate admin endpoints | Homepage news via `fetchHomeData()` | Need published-state / visibility verification |
| Hero visual and layout | No single comprehensive layout editor found in reviewed files | Homepage has React constants and local image import | `HomePage.jsx` | Not fully DB-configurable |
| Sidebar deep-link products | `/admin/public-content?section=products` | Allowed sections returned by admin API | `PublicContentAdminPage` | Bug fixed on PR #51; TEST still has old build |
| Admin sections with tabs | `/admin/dashboard?tab=...`, `/admin/operations?tab=...`, `/admin/health?tab=...`, `/admin/settings?tab=...` | `AdminControlCenterPage` | Content sections | Query tab handling requires individual contract tests; URL uniqueness does NOT ensure different content |

## Source-grounded concerns

1. `PublicContentContext.jsx` starts with `legacyReference.json` and falls back on fetch failure. Browser UAT must distinguish *published data* from fallback state; never present fallback as confirmed database content.
2. The Public Content editor formerly ignored `?section=products` and loaded the first allowed section. PR #51 fixes URL/selection synchronization, without changing server permissions.
3. `HomePage.jsx` includes local hero image import and hard-coded layout/hero styling. Current admin editor cannot be assumed to control every home section.
4. Product interest rate in `PublicContentService::catalog()` is derived from rate rows. Validate matches by immutable productId, and audit other APIs that might bypass that published calculation.
5. TEST currently serves `frontend-baseline-rc01-20261118T015616Z`, produced from older source candidate; GitHub `Test` Admin RC06 and PR #51 are NOT automatically deployed to that live TEST build. Preserve/merge those features in a guarded TEST rebuild; do not copy stale build over current Test branch.

## Proposed layout control (separate future scope; no schema change in this PR)

- `site_layout_sections`: stable section key (e.g. hero, news, rates, services), published boolean, sort order, metadata/content JSON, breakpoint rules, timestamps, version.
- `site_layout_revisions`: draft/pending/approved/published, maker/checker, source, schedule, before/after, rollback pointer.
- Public endpoint returns published allowlisted config only; no auth data/financial records.
- Admin CRUD, reorder, preview (draft mode only), desktop/tablet/mobile simulation, disable/enable controls.
- Server-side schema validation, sanitization, CSP/image allowlist, RBAC, 2FA, concurrency/version checks, audit logs.
- Atomic publication + cache invalidation and rollback; fail-safe defaults on public API failure. No migration without approved TEST DB guard and backup.

## UAT gates

1. Confirm TEST Nginx active release, database name `rycoopweb_test`, Basic Auth and required TEST assets.
2. Route contract matrix: for every visible sidebar entry, destination pathname, query, page heading, actual module, role/permission and deep-link refresh.
3. DB contract: make a **TEST-only** draft, verify public unchanged, publish by permitted checker, verify API and frontend changed exactly as authorized, rollback then verify.
4. Verify news/documents/media/hero/board/welfare changes and frontend response across breakpoints.
5. Confirm member PDPA, dividends, MFA and RBAC non-regression; never use production personal data in test.
6. Production deployment held until complete end-to-end audit, sidebar regression suite, rollback, and sign-off.

## Current decision

- PR #51 resolves **one confirmed sidebar deep-link issue**; it does not claim to fix all sidebar menus.
- Database compatibility is **not yet verified** because no live TEST database read or browser-authenticated UAT has been performed.
- No Production changes.
