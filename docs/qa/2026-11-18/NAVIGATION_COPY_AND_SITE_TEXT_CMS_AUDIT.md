# RYCOOPWEB Website Text and Navigation CMS — RC01 Audit

Scope: current Production-look public navigation and TEST-only CMS development. Source inspection on 18 Nov 2026; no authenticated TEST browser/database data writes have been verified.

## Confirmed source defects from user-supplied navigation screenshot

| Element | Existing source | Correction in PR #53 |
|---|---|---|
| Board menu description | hard-coded `ชุดที่ 32` | supports `{term}` populated from newest active, published board term |
| Dividend history description | hard-coded rate `5.25% - 5.50%` every year | removed unverified rate claim; editorial copy editable |
| Financial status badge | asserted `มั่นคงสูง` without a live indicator | removed static claim |
| Side financial banner | asserted fixed assets `4,850 ล้านบาท` | replaced with neutral wording pending verified data |
| Staff / finance / document descriptions | static React string | now database-driven published copy with safe fallback |
| Main navigation label | static React strings | now configurable copy with preserved links |

## Delivered CMS contract (Draft PR #53)

- `site_settings` holds versioned public/draft nav copy.
- `SiteNavigationCopyService` applies strict key/value allowlist, length bounds, optimistic version conflict and required audit logging.
- `/api/public/navigation-copy` exposes published text only, no drafts.
- Admin reads/edits/publishes at `/api/admin/navigation-copy`, requiring super_admin, CSRF on writes and fresh MFA step-up.
- `AdminNavigationCopyPage` renders form fields for primary labels, menu headings and descriptions.
- `Navbar.jsx` uses these published values, with defaults if API unavailable; links and role-based behaviors remain defined by source.
- `site_layout_home_v1` allows visibility toggles of six registered homepage sections through separate Layout Manager.
- The feature is **not** a universal visual editor: page body sections in About, News, Footer, Board and other pages are NOT all registered for editing yet.

## Remaining scope for “every text on the site”

1. Inventory textual elements across public routes: headings, subtitles, content body, CTA links, alt text, SEO titles/descriptions, footer, contact and legal.
2. Preserve domain data as structured CMS records (board terms, members, rates, dividends, legal docs); do not turn financial/clinical/regulatory facts into uncontrolled static free text.
3. Add a stable site-copy key registry with page/section/field metadata, translations, rich-text sanitation, publish/preview/rollback and revision history.
4. Add per-page routing permissions and maker/checker for sensitive public financial and legal information.
5. Compare actual Production deployed UI against TEST builds to retain current appearance; reconcile Admin RC06.
6. E2E TEST: draft must not change public view, publication changes only intended fields; verify desktop/mobile, reload, cache, permission denial and rollback.
7. Before any TEST deployment: isolate release, verify TEST DB `rycoopweb_test`, backing files/media paths, health checks, rollback and all CI security gates. Production remains untouched.

## Current release decision

The PR is draft and NOT deployed to TEST. Passing isolated builds does not override the repository-wide npm vulnerability gate, whose previous run reported high-severity advisories in transitive `picomatch` dependencies. Do not run `npm audit fix --force` without testing the resulting Vite major/version compatibility.
