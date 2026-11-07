# RYCOOPWEB — BACKEND ROUTE AUDIT

**Repository:** `pharmacisttom/rycoopweb`  
**Branch:** `feature/backend-recovery-data-integrity`  
**Environment:** TEST ONLY (`test.rayongcoop.org`)  
**Production Status:** STRICTLY FROZEN  
**Audit Date:** 2026-11-07  

---

## 1. Executive Summary

A comprehensive, system-wide diagnostics audit was performed across all Admin Routes, mapping:
**Frontend Component → API Endpoint → PHP Controller → MySQL Table → API Response → Rendered UI**.

The audit examined:
- **367 backend routes** registered in `config/routes.php`.
- **84 unique API endpoints** called by the React frontend (`src/pages/`, `src/components/admin/`).
- **60 tables** in the live `rayongcoop_db` MySQL database.
- Real PHP router dispatch tests executed against the authenticated super_admin session.

### Audit Findings Summary
- **Total Endpoints Tested with Real Router Dispatch:** 38
- **Endpoints Passing Immediately:** 35
- **Critical / High Failures Identified:** 3 immediate route crashes + 4 REST method mismatches + 1 global auth decoupling blocker.
- **Classification of Defects:**
  - **Critical:** 5
  - **High:** 3
  - **Medium:** 2
  - **Low:** 2

---

## 2. Route Audit Matrix (Frontend to Backend Mapping)

| Frontend Route | Frontend Component | API Endpoint Called | Backend Controller & Method | MySQL Table(s) | Status | Root Cause & Classification |
|---|---|---|---|---|:---:|---|
| `/admin/workspace` | `AdminWorkspacePage` | `GET /api/admin/system/health` | `ControlCenterApiController@systemHealth` | `users`, `audit_logs` | **PASS** | Functional |
| `/admin/dashboard` | `AdminControlCenterPage` | `GET /api/admin/dashboard/summary`<br>`GET /api/admin/dashboard/trends` | `ControlCenterApiController@summary`<br>`ControlCenterApiController@trends` | `members`, `member_dividends`, `audit_logs` | **PASS** | Functional |
| `/admin/members/dashboard` | `AdminMembersDashboardPage` | `GET /api/admin/members/dashboard` | `MemberManagementController@dashboard` | `members`, `member_dividends`, `users` | **PASS** | Functional (2,508 real members) |
| `/admin/members` | `AdminMembersPage` | `GET /api/admin/members?page=...` | `MemberManagementController@index` | `members`, `user_roles`, `users` | **PASS** | Functional (server-side pagination) |
| `/admin/members/health` | `AdminMembersHealthPage` | `GET /api/admin/members/health` | `MemberManagementController@health` | `members`, `users` | **PASS** | Functional (mapping verification) |
| `/admin/members/:id` | `AdminMemberDetailPage` | `GET /api/admin/members/{id}` | `MemberManagementController@show` | `members`, `users`, `member_dividends` | **PASS** | Functional (masked PII display) |
| `/admin/dividends/import` | `AdminDividendImportPage` | `POST /api/admin/dividends/preview`<br>`POST /api/admin/dividends/confirm` | `DividendImportController@preview`<br>`DividendImportController@confirm` | `member_dividends`, `members` | **PASS** | Functional (flat shell verified) |
| `/admin/hero-slides` | `AdminHeroSlidesPage` | `GET /api/admin/hero-slides`<br>`POST /api/admin/hero-slides`<br>`POST /api/admin/hero-slides/{id}/update`<br>`POST /api/admin/hero-slides/{id}/delete` | `HeroSlideController@apiIndex`<br>`HeroSlideController@apiStore`<br>`HeroSlideController@apiUpdate`<br>`HeroSlideController@apiDestroy` | `hero_slides` | **PASS (Read)**<br>**WARN (RBAC)** | Module name in DB is `hero_slides`, but route checked `news` permission. |
| `/admin/popups` | `AdminPopupsPage` | `GET /api/admin/popups`<br>`POST /api/admin/popups`<br>`POST /api/admin/popups/{id}/delete` | `PopupController@apiIndex`<br>`PopupController@apiStore`<br>`PopupController@apiDestroy` | `popups`, `popup_pages`, `popup_events` | **PASS (Read)**<br>**WARN (RBAC)** | Module name in DB is `popups`, but route checked `news` permission. |
| `/admin/welfare` | `AdminWelfarePage` | `GET /api/admin/welfare`<br>`POST /api/admin/welfare`<br>`POST /api/admin/welfare/{id}/update`<br>`POST /api/admin/welfare/{id}/delete` | `WelfareController@apiIndex`<br>`WelfareController@apiStore`<br>`WelfareController@apiUpdate`<br>`WelfareController@apiDestroy` | `welfare` | **PASS** | Functional |
| `/admin/eservices` | `AdminEServicesPage` | `GET /api/admin/eservices`<br>`PUT /api/admin/eservices/{id}`<br>`DELETE /api/admin/eservices/{id}` | `EServiceController@apiIndex`<br>`EServiceController@apiUpdate`<br>`EServiceController@apiDestroy` | `eservice_links` | **FAIL (CRUD)** | **CRITICAL:** Frontend sends PUT/DELETE to `/{id}`, backend only listened on POST `/{id}/update` and `/{id}/delete`. `memberAdminApi` ignored non-data options. |
| `/admin/board-staff` | `AdminBoardPersonnelPage` | `GET /api/admin/board-staff`<br>`POST /api/admin/board-staff/board`<br>`PUT /api/admin/board-staff/board/{id}`<br>`DELETE /api/admin/board-staff/board/{id}` | `BoardStaffController@apiIndex`<br>`BoardStaffController@apiStoreBoard`<br>`BoardStaffController@apiUpdateBoard`<br>`BoardStaffController@apiDestroyBoard` | `boards`, `staff` | **FAIL (CRUD)** | **CRITICAL:** Frontend sends PUT/DELETE to `/{id}`, backend only listened on POST `/{id}/update` and `/{id}/delete`. |
| `/admin/products` | `AdminProductsPage` | `GET /api/admin/loans`<br>`POST /api/admin/loans`<br>`PUT /api/admin/loans/{id}`<br>`DELETE /api/admin/loans/{id}`<br>`POST /api/admin/deposits` | `LoanProductController@apiIndex`<br>`LoanProductController@apiStore`<br>`DepositProductController@apiStore` | `loan_products`, `deposit_products` | **FAIL (404)** | **CRITICAL:** Frontend calls `GET /api/admin/loans`, but backend route was only `/api/admin/products`. CRUD calls PUT/DELETE. |
| `/admin/document-categories` | `AdminDocumentCategoriesPage` | `GET /api/admin/document-categories`<br>`POST /api/admin/document-categories`<br>`PUT /api/admin/document-categories/{id}`<br>`DELETE /api/admin/document-categories/{id}` | `DocumentController@apiCategories`<br>`DocumentController@apiStoreCategory`<br>`DocumentController@apiUpdateCategory`<br>`DocumentController@apiDestroyCategory` | `document_categories` | **FAIL (500)** | **CRITICAL:** `DocumentController` crashes with SQL 1054: `Unknown column 'deleted_at' in where clause` and `Unknown column 'icon' in field list`. |
| `/admin/documents` | `AdminStoredContentPage` | `GET /api/admin/documents`<br>`POST /api/admin/documents` | `DocumentController@index`<br>`DocumentController@store` | `documents`, `document_categories` | **FAIL (500)** | **CRITICAL:** Line 22 queries `WHERE deleted_at IS NULL` on `document_categories`, crashing with HTTP 500. |
| `/admin/content` | `ContentAdminPage` | `GET /api/admin/news`<br>`GET /api/admin/faqs` | `SpaApiController@adminNews`<br>`SpaApiController@adminFaqs` | `news`, `faqs` | **PASS** | Functional |
| `/admin/security/mfa` | `AdminControlCenterPage` | `GET /api/admin/security/mfa` | `ControlCenterApiController@securityMfa` | `users`, `user_roles`, `user_mfa_recovery_codes` | **PASS** | Functional |
| `/admin/audit-logs` | `AdminControlCenterPage` | `GET /api/admin/audit-logs/center` | `ControlCenterApiController@auditLogs` | `audit_logs`, `security_audit_logs` | **PASS** | Functional |
| `/admin/led/*` | `AdminLedDashboardPage` | `GET /api/admin/led/dashboard`<br>`GET /api/admin/led/schema`<br>`GET /api/admin/led/review` | `LedApiController@dashboard`<br>`LedApiController@schema`<br>`LedApiController@reviewQueue` | `led_check_results`, `led_check_runs`, `members` | **PASS** | Functional |

---

## 3. Detailed Root Cause Analysis

### Defect 1: SQL Column Not Found on `document_categories` (Critical)
- **Problem:** `DocumentController.php` queries `WHERE deleted_at IS NULL` and inserts/updates column `icon`.
- **Evidence:** `DESCRIBE document_categories` shows columns are: `id`, `name`, `slug`, `description`, `sort_order`, `status`, `created_at`, `updated_at`. There is NO `deleted_at` or `icon` column.
- **Impact:** Crashes `GET /api/admin/document-categories` and `GET /api/admin/documents` with HTTP 500 PDOException.

### Defect 2: `memberAdminApi.js` Ignored Non-Data Request Options (Critical)
- **Problem:** `memberAdminApi(path, { data, signal })` only inspected `data`. When callers sent `{ method: 'PUT', body: ... }` or `{ method: 'DELETE' }`, `data` was undefined, falling back to a plain `GET` request without CSRF tokens.
- **Impact:** All update and delete calls in EServices, Document Categories, Products, and Board & Staff sent malformed GET requests, resulting in 404 or 405.

### Defect 3: Products API Endpoint Mismatch (Critical)
- **Problem:** `AdminProductsPage.jsx` queries `/api/admin/loans`, but `config/routes.php` only registered `/api/admin/products`.
- **Impact:** Navigating to `/admin/products` immediately yielded HTTP 404 Not Found.

### Defect 4: Missing REST Route Aliases (Critical)
- **Problem:** React pages use RESTful conventions (`PUT /api/admin/{resource}/{id}` and `DELETE /api/admin/{resource}/{id}`), while backend routes only handled POST `/{id}/update` and POST `/{id}/delete`.
- **Impact:** Form updates and deletion triggers failed with 404.

### Defect 5: Decoupling Admin Authentication from Member Portal Flag (Critical)
- **Problem:** `feature/admin-experience-transformation` still contained legacy checks blocking `/admin/login` and `StaffProtectedRoute` when `VITE_FEATURE_MEMBER_PORTAL=false`.
- **Impact:** Admin portal was rendered completely inaccessible in production-like test configurations where the member portal is turned off.

### Defect 6: RBAC Module Mismatches (High)
- **Problem:** Routes checked `news.view` for hero-slides and popups, `settings.view` for eservices, and `people.view` for board-staff. The database permissions table actually defines granular modules: `hero_slides`, `popups`, `eservice_links`, `boards`, `staff`.
- **Impact:** Non-superadmin staff roles with legitimate permissions were blocked with HTTP 403 Forbidden.
