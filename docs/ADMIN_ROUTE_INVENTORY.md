# ADMIN_ROUTE_INVENTORY.md
## RYCOOPWEB Administration Platform — Route, Component & API Inventory
### Reconciled against Actual Source Code (`src/App.jsx`, `config/routes.php`, Controllers)

**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)
**Audit Baseline Commit:** `6b2eb2d` (Sprint 1B Reconciled)
**Evaluator:** Principal Full-Stack Architect & DevSecOps Lead
**Audit Timestamp:** 2026-11-06T15:25:00+07:00

---

### 1. Verification Classification Taxonomy

To ensure absolute audit integrity, each module is classified strictly according to proof of verification:
- **`VERIFIED BY E2E`**: Verified by automated browser/headless test execution (Playwright, Puppeteer, or PHP CLI test) asserting live DOM/API behavior.
- **`STATICALLY VERIFIED`**: Source code, router definition, middleware chain, and API contract verified by static analysis and code review.
- **`NOT VERIFIED`**: Route or API endpoint exists in code but has not yet undergone dedicated automated integration or E2E testing in this sprint.
- **`BROKEN`**: Execution fails, routes mismatch, or regression identified.

---

### 2. Complete Administrative Route Matrix

All administrative and staff routes are protected by `<StaffProtectedRoute>` inside `src/App.jsx`, which establishes the single top-level `<AdminActionProvider>` and `<AdminAppShell>` (pointing to canonical `AdminControlCenterShell`).

| Route Path in `src/App.jsx` | Page Component | Owning Layout Container | Access Role / Permission | Backend REST API Endpoints (`config/routes.php`) | Verification Status |
|---|---|---|---|---|:---:|
| `/admin` | `AdminControlCenterPage` (`dashboard`) | `StaffProtectedRoute` → `AdminAppShell` | `dashboard.view` | `GET /api/admin/dashboard/summary`, `GET /api/admin/dashboard/trends` | **VERIFIED BY E2E** |
| `/admin/dashboard` | `AdminControlCenterPage` (`dashboard`) | `StaffProtectedRoute` → `AdminAppShell` | `dashboard.view` | `GET /api/admin/dashboard/summary`, `GET /api/admin/dashboard/trends` | **VERIFIED BY E2E** |
| `/admin/workspace` | `AdminWorkspacePage` | `StaffProtectedRoute` → `AdminAppShell` | `dashboard.view` / Staff roles | `GET /api/auth/me`, `GET /api/admin/system/health` | **VERIFIED BY E2E** |
| `/admin/popups` | `AdminPopupsPage` | `StaffProtectedRoute` → `AdminAppShell` | `content.view` (Backend: `news.view/create/edit/delete`) | `GET/POST /api/admin/popups`, `POST /api/admin/popups/{id}/update`, `POST /api/admin/popups/{id}/delete`, `POST /api/admin/popups/upload` | **VERIFIED BY E2E** |
| `/admin/dividends/import` | `AdminDividendImportPage` | `StaffProtectedRoute` → `AdminAppShell` | `dividends.import` | `POST /api/admin/dividends/preview`, `POST /api/admin/dividends/confirm` | **VERIFIED BY E2E** |
| `/admin/hero-slides` | `AdminHeroSlidesPage` | `StaffProtectedRoute` → `AdminAppShell` | `content.view` (Backend: `news.view/create/edit/delete`) | `GET/POST /api/admin/hero-slides`, `POST /api/admin/hero-slides/{id}/update`, `POST /api/admin/hero-slides/{id}/delete` | **STATICALLY VERIFIED** |
| `/admin/welfare` | `AdminWelfarePage` | `StaffProtectedRoute` → `AdminAppShell` | `welfare.view` (Backend: `welfare.view/create/edit/delete`) | `GET/POST /api/admin/welfare`, `POST /api/admin/welfare/{id}/update`, `POST /api/admin/welfare/{id}/delete` | **STATICALLY VERIFIED** |
| `/admin/eservices` | `AdminEServicesPage` | `StaffProtectedRoute` → `AdminAppShell` | `content.view` (Backend: `settings.view/update`) | `GET/POST /api/admin/eservices`, `POST /api/admin/eservices/{id}/update`, `POST /api/admin/eservices/{id}/delete` | **STATICALLY VERIFIED** |
| `/admin/board-staff` | `AdminBoardPersonnelPage` | `StaffProtectedRoute` → `AdminAppShell` | `people.view` (Backend: `people.view/update`) | `GET /api/admin/board-staff`, `POST /api/admin/board-staff/board`, `POST /api/admin/board-staff/staff` | **STATICALLY VERIFIED** |
| `/admin/products` | `AdminProductsPage` | `StaffProtectedRoute` → `AdminAppShell` | `products.view` (Backend: `products.view/update`) | `GET /api/admin/products`, `POST /api/admin/products/deposits`, `POST /api/admin/products/loans` | **STATICALLY VERIFIED** |
| `/admin/document-categories` | `AdminDocumentCategoriesPage` | `StaffProtectedRoute` → `AdminAppShell` | `documents.view` (Backend: `documents.view/create/edit/delete`) | `GET/POST /api/admin/document-categories`, `POST /api/admin/document-categories/{id}/update`, `POST /api/admin/document-categories/{id}/delete` | **STATICALLY VERIFIED** |
| `/admin/documents` | `AdminStoredContentPage` (`documents`) | `StaffProtectedRoute` → `AdminAppShell` | `documents.view` | `GET /api/public/documents`, `POST /api/public/documents` | **STATICALLY VERIFIED** |
| `/admin/settings/content` | `AdminStoredContentPage` (`settings`) | `StaffProtectedRoute` → `AdminAppShell` | `settings.view` | `GET/POST /api/admin/settings/content` | **STATICALLY VERIFIED** |
| `/admin/content` | `ContentAdminPage` | `StaffProtectedRoute` → `AdminAppShell` | `news.view`, `faqs.view` | `GET /api/public/news`, `GET /api/public/faqs` | **STATICALLY VERIFIED** |
| `/admin/public-content` | `PublicContentAdminPage` | `StaffProtectedRoute` → `AdminAppShell` | `coop_info.view`, `people.view`, `rates.view`, `products.view` | `GET /api/public/content` | **STATICALLY VERIFIED** |
| `/admin/members/dashboard` | `AdminMembersDashboardPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view` | `GET /api/admin/members/dashboard` | **STATICALLY VERIFIED** |
| `/admin/members` | `AdminMembersPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view` | `GET /api/admin/members` | **STATICALLY VERIFIED** |
| `/admin/members/health` | `AdminMembersHealthPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view` | `GET /api/admin/members/health` | **STATICALLY VERIFIED** |
| `/admin/members/:id` | `AdminMemberDetailPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view` | `GET /api/admin/members/{id}` | **STATICALLY VERIFIED** |
| `/admin/led/dashboard` | `AdminLedDashboardPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view`, `operations.view` | `GET /api/admin/led/dashboard` | **STATICALLY VERIFIED** |
| `/admin/led/search` | `AdminLedSearchPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view`, `operations.view` | `GET /api/admin/led/search` | **STATICALLY VERIFIED** |
| `/admin/led/review` | `AdminLedReviewPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view`, `operations.view` | `GET /api/admin/led/review` | **STATICALLY VERIFIED** |
| `/admin/led/batch` | `AdminLedBatchPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view`, `operations.view` | `GET /api/admin/led/batches` | **STATICALLY VERIFIED** |
| `/admin/led/schema` | `AdminLedSchemaPage` | `StaffProtectedRoute` → `AdminAppShell` | `members.view`, `operations.view` | `GET /api/admin/led/schema` | **STATICALLY VERIFIED** |
| `/admin/approvals` | `AdminControlCenterPage` (`approvals`) | `StaffProtectedRoute` → `AdminAppShell` | `super_admin`, `security_admin`, `auditor` | `GET /api/admin/approvals`, `POST /api/admin/approvals/{id}/approve` | **STATICALLY VERIFIED** |
| `/admin/analytics/visitors` | `AdminControlCenterPage` (`visitor-analytics`) | `StaffProtectedRoute` → `AdminAppShell` | `analytics.visitors.view`, `analytics.view` | `GET /api/admin/analytics/visitors` | **STATICALLY VERIFIED** |
| `/admin/operations` | `AdminControlCenterPage` (`operations`) | `StaffProtectedRoute` → `AdminAppShell` | `operations.view` | `GET /api/admin/operations/overview` | **STATICALLY VERIFIED** |
| `/admin/users` | `AdminControlCenterPage` (`users`) | `StaffProtectedRoute` → `AdminAppShell` | `users.view`, `users.create`, `users.update` | `GET /api/admin/access/users` | **STATICALLY VERIFIED** |
| `/admin/roles` | `AdminControlCenterPage` (`roles`) | `StaffProtectedRoute` → `AdminAppShell` | `roles.view`, `roles.manage` | `GET /api/admin/access/roles` | **STATICALLY VERIFIED** |
| `/admin/permissions` | `AdminControlCenterPage` (`permissions`) | `StaffProtectedRoute` → `AdminAppShell` | `roles.view`, `permissions.manage` | `GET /api/admin/access/permissions` | **STATICALLY VERIFIED** |
| `/admin/departments` | `AdminControlCenterPage` (`departments`) | `StaffProtectedRoute` → `AdminAppShell` | `departments.view` | `GET /api/admin/departments` | **STATICALLY VERIFIED** |
| `/admin/audit-logs` | `AdminControlCenterPage` (`audit-logs`) | `StaffProtectedRoute` → `AdminAppShell` | `audit.view`, `security.view` | `GET /api/admin/audit-logs/center` | **STATICALLY VERIFIED** |
| `/admin/security` | `AdminControlCenterPage` (`security`) | `StaffProtectedRoute` → `AdminAppShell` | `security.view` | `GET /api/admin/security/overview` | **STATICALLY VERIFIED** |
| `/admin/security/sessions` | `AdminControlCenterPage` (`sessions`) | `StaffProtectedRoute` → `AdminAppShell` | `security.view` | `GET /api/admin/security/sessions` | **STATICALLY VERIFIED** |
| `/admin/security/mfa` | `AdminControlCenterPage` (`mfa`) | `StaffProtectedRoute` → `AdminAppShell` | `security.view` | `GET /api/admin/security/mfa` | **STATICALLY VERIFIED** |
| `/admin/backups` | `AdminControlCenterPage` (`backups`) | `StaffProtectedRoute` → `AdminAppShell` | `backup.view` | `GET /api/admin/backups` | **STATICALLY VERIFIED** |
| `/admin/backups/history` | `AdminControlCenterPage` (`backups-history`) | `StaffProtectedRoute` → `AdminAppShell` | `backup.view`, `backup.verify` | `GET /api/admin/backups/health` | **STATICALLY VERIFIED** |
| `/admin/health` | `AdminControlCenterPage` (`health`) | `StaffProtectedRoute` → `AdminAppShell` | `health.view` | `GET /api/admin/system/health` | **STATICALLY VERIFIED** |
| `/admin/reports` | `AdminControlCenterPage` (`reports`) | `StaffProtectedRoute` → `AdminAppShell` | `reports.view`, `finance.view` | `GET /api/admin/reports/catalog` | **STATICALLY VERIFIED** |
| `/admin/settings` | `AdminControlCenterPage` (`settings`) | `StaffProtectedRoute` → `AdminAppShell` | `settings.view` | `GET /api/admin/settings` | **STATICALLY VERIFIED** |
| `/admin/access` | `AdminAccessPage` | `StaffProtectedRoute` → `AdminAppShell` | `super_admin` | `GET /api/admin/access/roles`, `GET /api/admin/access/users` | **STATICALLY VERIFIED** |
| `/staff`, `/staff/dashboard`, `/admin/cms` | `AdminDashboardPage` | `StaffProtectedRoute` → `AdminAppShell` | `staffRoles`, `super_admin` | Static navigation hub | **STATICALLY VERIFIED** |

---

### 3. Reconciled Route & Endpoint Clarifications
1. **Dividend Import Route:** The route is strictly `/admin/dividends/import` (plural `dividends`, matching `config/routes.php` and `App.jsx`).
2. **Dividend Import APIs:** Endpoints are `POST /api/admin/dividends/preview` and `POST /api/admin/dividends/confirm`, protected by `AuthMiddleware`, `PermissionMiddleware('dividends', 'import')`, and `CsrfMiddleware`.
3. **Popup Manager APIs:** Endpoints are under `prefix => 'api/admin'`, handling `GET/POST /api/admin/popups`, `POST /api/admin/popups/{id}/update`, `POST /api/admin/popups/{id}/delete`, and `POST /api/admin/popups/upload` with quota enforcement (max 3 popups).
