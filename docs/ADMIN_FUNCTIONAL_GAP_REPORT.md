# RYCOOPWEB — ADMIN FUNCTIONAL GAP REPORT
## Gap Analysis Between Admin Forms, Backend APIs, and Public Data Representation
**Target Environment:** TEST (`test.rayongcoop.org`)  
**Scope:** Administration Modules (`/admin/*`, `/staff/*`)

---

### 1. Overview & Methodology

To ensure that every admin feature connects to robust backend endpoints and translates faithfully to the public site, we performed an end-to-end verification across:
1. **Frontend Admin Form:** Field inputs, validation, payload formatting, CSRF inclusion.
2. **Backend API Endpoint:** HTTP verb, route definition, controller handling, authorization/RBAC.
3. **Database Schema:** Column existence, foreign keys, constraints, audit logs.
4. **Public Frontend Consumer:** Corresponding public page/component displaying the managed data.

---

### 2. Module-by-Module Gap Analysis

| Module | Admin Route | Backend API Endpoint | Database Table | Public Consumer | Status & Findings |
|---|---|---|---|---|---|
| **Smart Popups V2** | `/admin/popups` | `GET/POST /api/popups` | `popups` | `CampaignModal.jsx` | **VERIFIED & SECURED**<br>Max 3 popups, schedule window, layout mode, logo fallback. |
| **Hero Slider** | `/admin/hero-slides` | `GET/POST /api/hero-slides` | `hero_slides` | `HeroSlideshow.jsx` | **VERIFIED**<br>Responsive banner delivery, order sorting. |
| **Dividend Import** | `/admin/dividend-import` | `POST /api/member/dividend/import` | `member_dividends` | `MemberDividendsPage.jsx` | **VERIFIED**<br>Flat-shell regression fixed, Protected Core intact. |
| **News & PR** | `/admin/news` | `GET/POST /api/news` | `news_articles` | `NewsPage.jsx` | **CONNECTED**<br>Needs standardized drawer UI alignment in Sprint 2. |
| **Loan & Deposit Products** | `/admin/products` | `GET/POST /api/products` | `products` | `ServicesPage.jsx` | **CONNECTED**<br>Category and interest rate mapping functional. |
| **Executive Board** | `/admin/board` | `GET/POST /api/board` | `board_members` | `AboutBoardPage.jsx` | **CONNECTED**<br>Ordering index and tenure fields present. |
| **Downloadable Forms** | `/admin/forms` | `GET/POST /api/documents` | `documents` | `DownloadsPage.jsx` | **CONNECTED**<br>File upload size validation enforced on PHP side. |
| **Audit Logs** | `/admin/audit-logs` | `GET /api/staff/audit-logs` | `audit_logs` | N/A (Internal only) | **SECURED**<br>Read-only for Officers/Auditors, immutable logs. |
| **Workspace Overview** | `/admin/workspace` | `GET /api/staff/workspace-metrics` | Multiple | N/A (Admin only) | **ACTION REQUIRED**<br>Currently uses hardcoded quick counts; needs live API metrics in Sprint 1. |

---

### 3. Identified Functional Gaps & Remedies

#### GAP-01: Legacy Dashboard Prototype vs Control Center
- **Gap:** Route `/admin/dashboard` historically pointed to `AdminDashboardPage.jsx`, which had 2,000+ lines of simulated browser localStorage storage.
- **Resolution:** Route `/admin/dashboard` is rendered by `AdminControlCenterPage.jsx` which consumes real staff metrics (`/api/staff/system-health`, `/api/staff/audit-logs`). `AdminDashboardPage.jsx` is marked for final cleanup/deprecation.

#### GAP-02: Admin Workspace Metric Dynamic Integration
- **Gap:** `AdminWorkspacePage.jsx` renders static quick-link cards and placeholder numbers rather than real-time pending approvals.
- **Resolution:** In Sprint 1, wire `AdminWorkspacePage` with the unified summary API `/api/staff/system-health` to display live member/content counts.

#### GAP-03: Responsive Table Scroll Indication
- **Gap:** On viewports under 600px, administrative data tables scroll horizontally without an affordance indicator, confusing non-technical users.
- **Resolution:** Add gradient overlay scroll indicator to `CrudDataTable` container.

---

### 4. Protected Core Safety Confirmation
All analyzed admin modules strictly respect:
- Non-interference with `/api/auth/*` and `/api/member/auth/*`.
- Strict CSRF token verification on all POST/PUT/DELETE calls.
- Strict session and RBAC enforcement.
- Financial audit trails maintained for all member dividend actions.
