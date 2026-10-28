# Admin UI audit and modernization

Baseline: Test `8ac8202`. Logo work is PR #8; this branch builds on that work and
must be reviewed after it. Slideshow is independent PR #7. No backend rewrite,
database migration or automatic merge/deployment is authorized by this work.

## Phase A: inventory before implementation

Classification: A complete presentation; B partially styled; C broken contract/layout;
D missing reusable components; E mobile inconsistency; F sensitive-action UX gap.

| Routes | Actual component | Before | Severity and remedy |
|---|---|---|---|
| `/admin`, `/admin/dashboard`, `/dashboard` | AdminControlCenterPage | B,D | Medium: reuse existing shell/cards; avoid a duplicate design system |
| `/admin/analytics/visitors` | AdminControlCenterPage visitor-analytics | B | Medium: retain real API charts/date filters; improve accessible summaries |
| `/admin/operations` | AdminControlCenterPage operations | D,F | High: surface existing Maker/Checker requests and action states |
| `/admin/access` | AdminAccessPage | C,D,E,F | High: bare tables/forms, no shell, no Step-Up handling; replace with shared table, modal and permission matrix |
| `/admin/users`, `/admin/roles`, `/admin/permissions` | AdminControlCenterPage | C,D,F | High: staff API returns top-level `staff`, UI incorrectly checks `data.staff`; preserve contracts and fix consumer |
| `/admin/departments` | AdminControlCenterPage departments | B | Medium: consistent table region, empty/loading/error states |
| `/admin/audit-logs` | AdminControlCenterPage audit-logs | B | Medium: accessible filters, readable audit rows; no fabricated history |
| `/admin/security`, `/admin/security/sessions`, `/admin/security/mfa` | AdminControlCenterPage | B,F | High: revoke-session action lacks fresh Step-Up UX and duplicate-action lock |
| `/admin/backups`, `/admin/backups/history` | AdminControlCenterPage | B,F | High: no complete approval presentation; keep restore policy locked |
| `/admin/health`, `/admin/settings`, `/admin/reports` | AdminControlCenterPage | B,D | Medium: common loading/error/card/form styles; report unsupported capabilities explicitly |
| `/admin/workspace` | AdminWorkspacePage | D,E | Medium: no shared sidebar; retain effective-permission destinations |
| `/admin/public-content` | PublicContentAdminPage | D,E | Medium: public container + custom CSS; common shell and scoped form/table styles |
| `/admin/content` | ContentAdminPage | D,E | Medium: common shell; preserve news draft/review/publish workflow |
| `/admin/members/dashboard`, `/admin/members`, `/admin/members/:id`, `/admin/members/health`, `/admin/dividends/import` | member admin pages + AdminMemberShell | D,E | Medium: separate sidebar conventions; converge on shared shell, retain member operations |
| `/admin/led`, `/admin/led/dashboard`, `/admin/led/search`, `/admin/led/review`, `/admin/led/batch`, `/admin/led/schema` | AdminLed* pages | D,E | Medium: separate sub-navigation; keep it as module navigation within shared admin shell |
| `/admin/cms`, `/staff`, `/staff/dashboard` | AdminDashboardPage | B,D,F | High legacy risk: retain functionality, common shell; localStorage-backed workflows require separately reviewed backend plan |
| `/admin/login`, `/admin/2fa`, `/admin/mfa/enroll`, `/admin/mfa/verify` | LoginPage/MfaPage/MfaAuthShell | B,E | Medium: isolated auth layout, no private navigation before MFA completion |

`/admin/control-center` is not registered at baseline. It may be an alias to the
existing dashboard; no fictitious new API/module should be introduced.

## Confirmed shared issues

- `AdminControlCenterShell` already supplies the main design tokens, sidebar and
  topbar. Unwrapped administrative pages instead use public `.container.section`.
- Native plain buttons and inline unbounded tables on Access are structural issues,
  not a reason for global public CSS overrides.
- Sidebar derives fallback permissions from hardcoded role names. Navigation must
  use effective server permissions; backend authorization remains authoritative.
- Mobile sidebar has a backdrop but no focus trapping/restore or Escape handling.
- Critical Access/staff mutations return Step-Up errors that existing UI only prints.
- Backend approval endpoints exist, but there is no complete client approval workflow.
- Existing auth, member, public and legacy modules must not be removed to restyle them.

## Evidence and contract gaps

Logo presentation before/after screenshots: `storage/tmp/branding-qa/{before,after}`.
These use actual presentation components with synthetic context at isolated fixture
URLs. They are not authenticated TEST screenshots or evidence of live-account UAT.
Admin page/component fixtures and functional QA will be added during Phases B–E.

The Access users endpoint paginates by 25 but has no server-side status filter.
Any local status filter must explicitly state it applies to the current fetched page;
global server filtering is a separate API proposal. Staff directory returns top-level
`staff`, `invitations`, `roles`, and may contain one row per role assignment.
No frontend should invent aggregate counts, MFA secrets, approval history, financial
operations, or writable settings that the existing APIs do not support.

Live protected TEST screenshots need approved TEST login plus MFA. Do not weaken
Basic Auth, change MFA keys, or bypass protected routes to obtain them.
