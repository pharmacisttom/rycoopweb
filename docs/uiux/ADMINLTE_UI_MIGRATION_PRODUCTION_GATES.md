# RYCOOPWEB AdminLTE modernization — UI migration and production gates

## Scope and architecture (2026-11-17)
- Actual AdminLTE 4.10.2 package now installed in TEST RC04; React owns the interactions. CSS is scoped to the staff `.acc-shell`.
- Public pages and **member login, PDPA consent, dividends, shares, loans, deposits, receipts and services** are not to be rewritten or restyled globally.
- Existing PHP endpoint contracts, authentication, TOTP MFA, RBAC, audit trails, validation, CSRF and CSRF-aware CRUD must remain unchanged by visual refactors.
- The member portal's existing status/feature flags must not be changed during an AdminLTE UI release.

## Initial source audit and workstream

| Module/routes | Current source | Required UI QA | Rollout |
|---|---|---|---|
| Shared `/admin/*` layout | `AdminControlCenterShell.jsx` | Header/profile, sidebar 100dvh, bottom tools, focus, dark theme, mobile backdrop | RC04 already merged |
| Executive dashboard | `AdminControlCenterPage.jsx` | 16px titles/cards, no overflow, real/empty data, CSV downloads | RC05 |
| Welfare admin | `AdminWelfarePage.jsx` | standardized cards/tables/form, CRUD, server-side MFA/RBAC/audit | RC05 |
| Media | `AdminMediaLibraryPage.jsx` | thumbnails, captions, upload, pagination, CSRF, image access | subsequent UAT |
| Content/news/FAQs | `ContentAdminPage.jsx` | rich editor, file picker, forms, publish checks, errors | subsequent migration |
| Documents/settings | `AdminStoredContentPage.jsx` | tables/search/upload, responsive controls | subsequent migration |
| Members | `AdminMembersPage.jsx`, `AdminMemberShell.jsx` | PDPA masking, search, pagination, identity permissions, details | separate guarded UAT |
| Dividends staff import | `AdminDividendImportPage.jsx` | import preview, year validation, rollback, audit, maker/checker policies | separate guarded UAT |
| LED and review | `AdminLed*Page.jsx` | approvals, audit, modal/keyboard, tables | separate guarded UAT |
| RBAC/security/audit | `AdminAccessPage.jsx`, `AdminControlCenterPage.jsx` | MFA, permissions, forbidden action controls, session handling | separate guarded UAT |

## Production release decision
**Finish critical staff UI and member-facing TEST UAT before a single coordinated production release.**
Do not combine with unrelated feature activation, schema migration or dividend data import. The deployment candidate must be cut from an immutable reviewed commit, have a verified backup, config DB allowlist, smoke tests and a documented reverse switch.

Gate checklist:
1. All required CI green (React, real AdminLTE dependency, PHP 8.3/8.5, browser, media, security).
2. Desktop 1440/1024px, tablet 768px and mobile 390px screenshots; 16px typography, no horizontal overflow, accessible keyboard focus.
3. Authenticated staff CRUD, media delivery, activity/news workflows, permissions, session expiry and MFA on TEST.
4. Member sign-in → mandatory PDPA acknowledgment → authorized dividend check (correct years/amounts) and masked personal data; verify disabled service flags stay disabled.
5. No JS console errors, no image 404 regressions, no Nginx/public-site/member stylesheet leak.
6. Backup database and current production release, record hashes, deploy canary/maintenance window, smoke and rollback drills.
7. Production promotion only after signed-off UAT evidence. **CI structural tests are not live dividend acceptance evidence.**

## Known limitations
- Current RC05 changes are a first pass at a common typography and Welfare page; not proof that every legacy page has been migrated.
- Do not automatically publish member financial records while preparing an AdminLTE staff-only release.
