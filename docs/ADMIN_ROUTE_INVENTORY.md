# ADMIN_ROUTE_INVENTORY.md
## RYCOOPWEB Administration Platform — Route, Component & API Inventory

**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)  
**Audit Baseline Commit:** `f97c72b`  
**Evaluator:** Principal Full-Stack Architect, UI/UX Lead & DevSecOps  
**Audit Timestamp:** 2026-11-06T15:10:00+07:00  

---

### 1. Route Inventory Matrix

All administrative and staff routes are protected by `StaffProtectedRoute` inside `src/App.jsx`, which establishes the single top-level `AdminActionProvider` and `AdminAppShell` (aliased to `AdminControlCenterShell`).

| Route Path | Page Component | Owning Layout Container | Access Role / Permission | Backend REST API Endpoints | Test Verification Status |
|---|---|---|---|---|:---:|
| `/admin` | `AdminControlCenterPage` (`dashboard`) | `StaffProtectedRoute` → `AdminAppShell` | `dashboard.view` | `GET /api/admin/dashboard/summary`, `GET /api/admin/dashboard/trends` | PASS |
| `/admin/dashboard` | `AdminControlCenterPage` (`dashboard`) | `StaffProtectedRoute` → `AdminAppShell` | `dashboard.view` | `GET /api/admin/dashboard/summary`, `GET /api/admin/dashboard/trends` | PASS |
| `/admin/workspace` | `AdminWorkspacePage` | `StaffProtectedRoute` → `AdminAppShell` | `dashboard.view` | `GET /api/auth/me`, `GET /api/admin/announcements` | PASS |
| `/admin/approvals` | `AdminControlCenterPage` (`approvals`) | `StaffProtectedRoute` → `AdminAppShell` | `super_admin`, `security_admin`, `auditor` | `GET /api/admin/approvals`, `POST /api/admin/backups/restore-request` | PASS (Dual-Control) |
| `/admin/analytics/visitors` | `AdminControlCenterPage` (`visitor-analytics`) | `StaffProtectedRoute` → `AdminAppShell` | `analytics.visitors.view`, `analytics.view` | `GET /api/admin/analytics/visitors` | PASS |
| `/admin/operations` | `AdminControlCenterPage` (`operations`) | `StaffProtectedRoute` → `AdminAppShell` | `operations.view` | `GET /api/admin/operations` | PASS |
| `/admin/users` | `AdminControlCenterPage` (`users`) | `StaffProtectedRoute` → `AdminAppShell` | `users.view`, `users.create`, `users.update` | `GET /api/admin/access/users`, `POST /api/admin/staff/invite` | PASS |
| `/admin/roles` | `AdminControlCenterPage` (`roles`) | `StaffProtectedRoute` → `AdminAppShell` | `roles.view`, `roles.manage` | `GET /api/admin/access/roles`, `POST /api/admin/access/roles` | PASS (Step-Up MFA) |
| `/admin/permissions` | `AdminControlCenterPage` (`permissions`) | `StaffProtectedRoute` → `AdminAppShell` | `roles.view`, `permissions.manage` | `GET /api/admin/access/permissions` | PASS |
| `/admin/departments` | `AdminControlCenterPage` (`departments`) | `StaffProtectedRoute` → `AdminAppShell` | `departments.view` | `GET /api/admin/access/departments` | PASS |
| `/admin/audit-logs` | `AdminControlCenterPage` (`audit-logs`) | `StaffProtectedRoute` → `AdminAppShell` | `audit.view`, `security.view` | `GET /api/admin/audit-logs` | PASS (Redacted) |
| `/admin/security` | `AdminControlCenterPage` (`security`) | `StaffProtectedRoute` → `AdminAppShell` | `security.view` | `GET /api/admin/security/overview` | PASS |
| `/admin/security/sessions` | `AdminControlCenterPage` (`sessions`) | `StaffProtectedRoute` → `AdminAppShell` | `security.view` | `GET /api/admin/security/sessions` | PASS |
| `/admin/security/mfa` | `AdminControlCenterPage` (`mfa`) | `StaffProtectedRoute` → `AdminAppShell` | `security.view` | `GET /api/admin/security/mfa` | PASS |
| `/admin/backups` | `AdminControlCenterPage` (`backups`) | `StaffProtectedRoute` → `AdminAppShell` | `backups.view` | `GET /api/admin/backups` | PASS |
| `/admin/backups/history` | `AdminControlCenterPage` (`backups-history`) | `StaffProtectedRoute` → `AdminAppShell` | `backups.view` | `GET /api/admin/backups/history` | PASS |
| `/admin/health` | `AdminControlCenterPage` (`health`) | `StaffProtectedRoute` → `AdminAppShell` | `health.view` | `GET /api/admin/system/health` | PASS |
| `/admin/reports` | `AdminControlCenterPage` (`reports`) | `StaffProtectedRoute` → `AdminAppShell` | `reports.view`, `finance.view` | `GET /api/admin/reports` | PASS |
| `/admin/settings` | `AdminControlCenterPage` (`settings`) | `StaffProtectedRoute` → `AdminAppShell` | `settings.view` | `GET /api/admin/settings` | PASS |
| `/admin/access` | `AdminAccessPage` | `StaffProtectedRoute` → `AdminAppShell` | `super_admin` | `GET /api/admin/access/roles`, `GET /api/admin/access/users` | PASS |
| `/admin/hero-slides` | `AdminHeroSlidesPage` | `StaffProtectedRoute` → `AdminAppShell` | `content.view` | `GET /api/admin/hero-slides`, `POST /api/admin/hero-slides` | PASS (CRUD 3.0) |
| `/admin/popups` | `AdminPopupsPage` | `StaffProtectedRoute` → `AdminAppShell` | `content.view` | `GET /api/admin/popups`, `POST /api/admin/popups` | PASS (Quota Lock) |
| `/admin/welfare` | `AdminWelfarePage` | `StaffProtectedRoute` → `AdminAppShell` | `welfare.view` | `GET /api/admin/welfare`, `POST /api/admin/welfare` | PASS (CRUD 3.0) |
| `/admin/eservices` | `AdminEServicesPage` | `StaffProtectedRoute` → `AdminAppShell` | `content.view` | `GET /api/admin/eservices`, `POST /api/admin/eservices` | PASS (CRUD 3.0) |
| `/admin/board-staff` | `AdminBoardPersonnelPage` | `StaffProtectedRoute` → `AdminAppShell` | `people.view` | `GET /api/admin/board-staff`, `POST /api/admin/board-staff` | PASS (CRUD 3.0) |
| `/admin/products` | `AdminProductsPage` | `StaffProtectedRoute` → `AdminAppShell` | `products.view` | `GET /api/admin/products`, `POST /api/admin/products` | PASS (CRUD 3.0) |
| `/admin/document-categories`| `AdminDocumentCategoriesPage` | `StaffProtectedRoute` → `AdminAppShell` | `documents.view` | `GET /api/admin/document-categories`, `POST ...` | PASS (CRUD 3.0) |
| `/admin/documents` | `AdminStoredContentPage` (`documents`) | `StaffProtectedRoute` → `AdminAppShell` | `documents.view` | `GET /api/admin/documents`, `POST /api/admin/documents` | PASS |
| `/admin/settings/content` | `AdminStoredContentPage` (`settings`) | `StaffProtectedRoute` → `AdminAppShell` | `settings.view` | `GET /api/admin/settings/content` | PASS |
| `/admin/content` | `ContentAdminPage` | `StaffProtectedRoute` → `AdminAppShell` | `news.view`, `faqs.view` | `GET /api/admin/news`, `GET /api/admin/faqs` | PASS |
| `/admin/public-content` | `PublicContentAdminPage` | `StaffProtectedRoute` → `AdminAppShell` | `coop_info.view`, `people.view`, `rates.view`, `products.view` | `GET /api/public-content` | PASS |
| `/admin/dividends/import` | `AdminDividendImportPage` | `StaffProtectedRoute` → `AdminAppShell` | `dividends.import` | `POST /api/admin/dividends/preview`, `confirm` | PASS (Flat Shell) |
| `/admin/members/dashboard` | `AdminMembersDashboardPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view` | `GET /api/admin/members/summary` | PASS (READ-ONLY) |
| `/admin/members` | `AdminMembersPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view` | `GET /api/admin/members` | PASS (READ-ONLY) |
| `/admin/members/health` | `AdminMembersHealthPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view` | `GET /api/admin/members/health` | PASS (READ-ONLY) |
| `/admin/members/:id` | `AdminMemberDetailPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view` | `GET /api/admin/members/{id}` | PASS (READ-ONLY) |
| `/admin/led/dashboard` | `AdminLedDashboardPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view`, `operations.view` | `GET /api/admin/led/dashboard` | PASS |
| `/admin/led/search` | `AdminLedSearchPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view`, `operations.view` | `GET /api/admin/led/search` | PASS |
| `/admin/led/review` | `AdminLedReviewPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view`, `operations.view` | `GET /api/admin/led/review` | PASS |
| `/admin/led/batch` | `AdminLedBatchPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view`, `operations.view` | `GET /api/admin/led/batches` | PASS |
| `/admin/led/schema` | `AdminLedSchemaPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view`, `operations.view` | `GET /api/admin/led/schema` | PASS |

---

### 2. Layout & Shell Architecture Audit

- **Single Outer Shell Enforcement:** All admin routes receive exactly one `<AdminAppShell>` from `StaffProtectedRoute`.
- **Nested Shell Elimination:**
  - Resolved `AdminDividendImportPage`: Converted to flat `<section className="member-admin">`, verified via `verify_flat_shell_regression.cjs`.
  - Inner modules contributing their own header do so cleanly through `AdminPageHeader` or `ShellContext` without re-instantiating `<aside>` or `<header className="acc-topbar">`.
