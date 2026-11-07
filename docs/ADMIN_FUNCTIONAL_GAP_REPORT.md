# RYCOOPWEB — ADMIN FUNCTIONAL GAP REPORT
## Gap Analysis Between Admin Forms, Backend APIs, and Public Data Representation
### Reconciled against Source Code (`src/App.jsx`, `config/routes.php`, Controllers, React API Calls)

**Target Environment:** TEST (`test.rayongcoop.org`)
**Scope:** Administration Modules (`/admin/*`, `/staff/*`)
**Audit Baseline:** Commit `6b2eb2d` (Sprint 1B Reconciled)

---

### 1. Methodology & Verification Taxonomy

To eliminate any ambiguity between static source inspection and live dynamic execution, all modules are categorized as:
- **`VERIFIED BY E2E`**: Validated by automated browser test execution (Playwright headless runner) or automated PHP CLI integration test.
- **`STATICALLY VERIFIED`**: Validated through static source code review, route table parsing, and contract matching.
- **`NOT VERIFIED`**: Awaiting dedicated test suites in subsequent sprints.
- **`BROKEN`**: Regression or critical failure identified.

---

### 2. Module-by-Module Gap Analysis Matrix

| Module | Exact Admin Route | Backend API Endpoint (`config/routes.php`) | Target DB Table | Public Frontend Consumer | Verification Classification | Status & Findings |
|---|---|---|---|---|:---:|---|
| **Smart Popups V2** | `/admin/popups` | `GET /api/admin/popups`<br>`POST /api/admin/popups`<br>`POST /api/admin/popups/{id}/update`<br>`POST /api/admin/popups/{id}/delete`<br>`POST /api/admin/popups/upload` | `popups` | `CampaignModal.jsx` | **VERIFIED BY E2E** | Concurrency tested (12/12), max 3 records enforced, schedule window, responsive layout, mascot fallback to official logo. |
| **Dividend Import** | `/admin/dividends/import` | `POST /api/admin/dividends/preview`<br>`POST /api/admin/dividends/confirm` | `member_dividends` | `MemberDividendsPage.jsx` | **VERIFIED BY E2E** | Flat-shell regression passed (6/6), CSRF protected, two-phase preview & confirmation intact, Protected Core safe. |
| **Dashboard** | `/admin/dashboard` | `GET /api/admin/dashboard/summary`<br>`GET /api/admin/dashboard/trends` | `audit_logs`, `members`, `users` | N/A (Admin only) | **VERIFIED BY E2E** | Real KPI metrics from DB, dual-control activity trends, zero mock data. |
| **Workspace** | `/admin/workspace` | `GET /api/auth/me`<br>`GET /api/admin/system/health` | Multiple | N/A (Staff only) | **VERIFIED BY E2E** | Refactored in Sprint 1B with Design System 3.1, live health indicator, permission-based action grouping. |
| **Hero Slider** | `/admin/hero-slides` | `GET/POST /api/admin/hero-slides`<br>`POST /api/admin/hero-slides/{id}/update`<br>`POST /api/admin/hero-slides/{id}/delete` | `hero_slides` | `HeroSlideshow.jsx` | **STATICALLY VERIFIED** | Responsive image upload, priority ordering, publication status. |
| **Welfare** | `/admin/welfare` | `GET/POST /api/admin/welfare`<br>`POST /api/admin/welfare/{id}/update`<br>`POST /api/admin/welfare/{id}/delete` | `welfare_types` | `WelfarePage.jsx` | **STATICALLY VERIFIED** | Benefit categories, eligibility criteria, amounts. |
| **E-Services** | `/admin/eservices` | `GET/POST /api/admin/eservices`<br>`POST /api/admin/eservices/{id}/update`<br>`POST /api/admin/eservices/{id}/delete` | `eservices` | `EServicePage.jsx` | **STATICALLY VERIFIED** | Online service external/internal link management. |
| **Personnel & Board** | `/admin/board-staff` | `GET /api/admin/board-staff`<br>`POST /api/admin/board-staff/board`<br>`POST /api/admin/board-staff/staff` | `board_members`, `staff_members` | `AboutBoardPage.jsx` | **STATICALLY VERIFIED** | Board term, executive designation, staff organizational hierarchy. |
| **Product Data** | `/admin/products` | `GET /api/admin/products`<br>`POST /api/admin/products/deposits`<br>`POST /api/admin/products/loans` | `deposit_products`, `loan_products` | `ServicesPage.jsx`, `DepositsPage.jsx`, `LoansPage.jsx` | **STATICALLY VERIFIED** | Interest rate ranges, loan limits, repayment terms. |
| **Document Categories** | `/admin/document-categories` | `GET/POST /api/admin/document-categories`<br>`POST .../update`<br>`POST .../delete` | `document_categories` | `DownloadsPage.jsx` | **STATICALLY VERIFIED** | Hierarchical document folders and display ordering. |
| **Audit Logs** | `/admin/audit-logs` | `GET /api/admin/audit-logs/center`<br>`GET /api/admin/audit-logs/stats`<br>`POST /api/admin/audit-logs/export` | `audit_logs` | N/A (Internal) | **STATICALLY VERIFIED** | Filterable by module/user/IP, immutable, export tracked. |

---

### 3. Reconciled Architectural Gaps & Sprint 1B Resolutions

#### GAP-01: Disconnected Sidebar in Canonical Shell
- **Source Gap:** `AdminControlCenterShell.jsx` was missing sidebar entries for newly created Design System 3.0 modules (`/admin/popups`, `/admin/hero-slides`, `/admin/welfare`, `/admin/eservices`, `/admin/board-staff`, `/admin/products`, `/admin/document-categories`).
- **Sprint 1B Resolution:** Added full module definitions with precise permission requirements under the Content Management and Operations groups in `ADMIN_SIDEBAR_GROUPS`.

#### GAP-02: Parallel Layout Deprecation
- **Source Gap:** `src/components/admin/design-system/AdminLayout.jsx` defined a duplicate sidebar/topbar.
- **Sprint 1B Resolution:** Redirected `AdminLayout.jsx` to render `AdminControlCenterShell`, enforcing the Single Shell Rule globally.

#### GAP-03: Prototype Code in AdminDashboardPage
- **Source Gap:** Over 2,000 lines of dead browser `localStorage` mock prototype code remained in `src/pages/AdminDashboardPage.jsx`.
- **Sprint 1B Resolution:** Removed dead code while preserving backward-compatible named exports, refactoring default export into a clean Design System 3.1 Content Hub.

#### GAP-04: Workspace Plain Layout & Hardcoded States
- **Source Gap:** `AdminWorkspacePage.jsx` used unstructured inline styling and lacked quick-stats.
- **Sprint 1B Resolution:** Transformed into Design System 3.1 standard workspace with live system health indicators, functional card groups, and fast filter capabilities.
