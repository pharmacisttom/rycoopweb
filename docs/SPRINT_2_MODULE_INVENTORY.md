# RYCOOPWEB — SPRINT 2 MODULE INVENTORY
## Content Standardization (Phase 2A) & Member Module Workflow Architecture (Phase 2B)

**Repository:** `pharmacisttom/rycoopweb`
**Development Base Branch:** `feature/admin-experience-transformation`
**Baseline Commit:** `b17e6c6`
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)

---

### 1. Phase 2A: Content Management Modules Inventory

All content modules must strictly follow the Single Shell Rule (`AdminControlCenterShell`), Design System 3.1 visual tokens, and genuine end-to-end data flow:
`Admin Form → Backend API → Database Table → Public API → Public View`

| Module Name | Admin Route | Admin Component | Backend Controller | DB Table | Public API Endpoint | Public Component | Data Safety / Verification |
|---|---|---|---|---|---|---|:---:|
| **1. News & PR** | `/admin/content?tab=news` | `ContentAdminPage.jsx` | `SpaApiController@news`<br>`NewsController.php` | `news_articles` | `GET /api/public/news` | `NewsPage.jsx` | DB-backed, rich text HTML sanitized via DOMPurify. |
| **2. Announcements** | `/admin/workspace` | `PrOfficerWorkspace.jsx`<br>`ContentAdminPage.jsx` | `AnnouncementController.php` | `announcements` | `GET /api/public/announcements` | `HomePage.jsx` | Audit-logged, date range validation. |
| **3. Hero Slides** | `/admin/hero-slides` | `AdminHeroSlidesPage.jsx` | `HeroSlideController.php` | `hero_slides` | `GET /api/public/home` | `HeroSlider.jsx` | Responsive desktop/mobile banners, sort order. |
| **4. Popup Campaigns** | `/admin/popups` | `AdminPopupsPage.jsx` | `PopupController.php` | `popups` | `GET /api/public/popups` | `CampaignModal.jsx` | Max 3 quota lock, MySQL named lock, sequential display. |
| **5. Board & Personnel** | `/admin/board-staff` | `AdminBoardPersonnelPage.jsx` | `BoardStaffController.php` | `board_members`<br>`staff_members` | `GET /api/public/board-members` | `AboutBoardPage.jsx`<br>`BoardPage.jsx` | Executive hierarchy ordering, tenure dates. |
| **6. Downloads & Forms** | `/admin/documents`<br>`/admin/document-categories` | `AdminStoredContentPage.jsx`<br>`AdminDocumentCategoriesPage.jsx` | `DocumentController.php` | `documents`<br>`document_categories` | `GET /api/public/documents` | `DownloadsPage.jsx` | File size & MIME validation, category grouping. |
| **7. Cooperative Info** | `/admin/public-content` | `PublicContentAdminPage.jsx` | `PublicContentController.php` | `coop_info`<br>`interest_rates` | `GET /api/public/coop-info`<br>`GET /api/public/interest-rates` | `AboutPage.jsx`<br>`DepositsPage.jsx`<br>`LoansPage.jsx` | Real interest rates from DB, zero fabricated rates. |
| **8. Welfare** | `/admin/welfare` | `AdminWelfarePage.jsx` | `WelfareController.php` | `welfare_types` | `GET /api/public/welfare` | `WelfarePage.jsx` | Eligibility criteria, grant conditions. |
| **9. E-Services** | `/admin/eservices` | `AdminEServicesPage.jsx` | `EServiceController.php` | `eservices` | `GET /api/public/online-services` | `EServicePage.jsx` | External/internal digital portal link catalog. |

---

### 2. Phase 2B: Member Module Workflow Architecture (Read-Only & Planning)

The Member Administration domain operates under strict Protected Core rules. Phase 2B establishes the read-only operational UI, audit mechanisms, and Excel import preflight checks without altering member credentials or financial balances.

| Member Module | Admin Route | Primary Data Entity | Read-Only UI Scope | Audit & Governance Controls |
|---|---|---|---|---|
| **Member Registry Dashboard** | `/admin/members/dashboard` | `members` (2,508 records) | • Member status counts (Active, Suspended, Resigned).<br>• Dividend yearly distribution summary.<br>• Recent import run logs. | Read-only metrics from live MySQL database. |
| **Member Identity Mapping** | `/admin/members` | `members`, `users` | • Search by member number, name, or masked ID card.<br>• Verification of account link status (`no_account` vs `linked`). | PII masked by default (First name xx, surname xxx). |
| **Excel Import Preflight** | `/admin/dividends/import` | Staging memory / SQLite preview | • Validate `.xlsx` and UTF-8 `.csv` structure.<br>• Verify column headers, member numbers, and ID cards.<br>• Display 20-row sample preview with diff comparison. | Zero mutation during preview; requires explicit checkbox acknowledgment. |
| **New/Existing Reconciliation** | `/admin/members/health` | `members`, `member_dividends` | • Cross-check dividend records against registered member IDs.<br>• Identify unmapped records before committing import. | Prohibits creating new member accounts from dividend sheets. |
| **Annual Dividend History** | `/admin/members/:id` | `member_dividends` (4,855 rows) | • Historical dividends view by fiscal year.<br>• Detailed breakdowns (Shares dividend, Loan refund). | Read-only view for cooperative officers and members. |
| **Audit & Approval Workflow** | `/admin/approvals` | `audit_logs`, `approvals` | • Dual-Control Maker/Checker requests.<br>• Step-up MFA authorization on sensitive actions. | Immutable audit trail with actor ID, timestamp, and IP. |
