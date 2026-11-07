# RYCOOPWEB — BACKEND REMEDIATION & RECOVERY PLAN

**Repository:** `pharmacisttom/rycoopweb`
**Branch:** `feature/backend-recovery-data-integrity`
**Environment:** TEST ONLY (`https://test.rayongcoop.org`)
**Production Status:** STRICTLY FROZEN
**Date:** 2026-11-07
**Status:** Approved for Implementation

---

## 1. Executive Summary

This remediation plan addresses the systemic defects identified during the Backend Diagnostics & Data Integrity Audit. The goal is to restore fully functional, real-data CRUD workflows, robust authentication decoupling, and strict database schema alignment across all RYCOOPWEB Admin modules without altering Production or touching sensitive financial tables.

All fixes are strictly scoped to the `feature/backend-recovery-data-integrity` branch.

---

## 2. Remediation Matrix & Implementation Order

| Priority | Bug ID | Module | Affected Component | Target File | Core Fix |
|:---:|---|---|---|---|---|
| **P0** | **BUG-001** | Documents & Categories | Backend Controller | `app/Controllers/Admin/DocumentController.php` | Remove invalid `deleted_at` & `icon` references from `document_categories` queries |
| **P0** | **BUG-002** | Frontend API Client | Frontend Service | `src/services/memberAdminApi.js` | Support `method`, `body`, headers, and auto-inject CSRF token for write requests |
| **P0** | **BUG-003** | Financial Products | Router & Controller | `config/routes.php` | Register canonical `/api/admin/loans` alias mapping to `LoanProductController@apiIndex` |
| **P0** | **BUG-004** | E-Services & Personnel | Router & Controllers | `config/routes.php` | Add standard RESTful (`PUT`, `DELETE`) routes for EServices, Board, Staff, Categories, Products |
| **P0** | **BUG-005** | Auth Independence | Auth Context & Routes | `src/App.jsx`, `src/services/api.js`, `src/context/AuthContext.jsx` | Decouple Admin Portal and Staff Login from `VITE_FEATURE_MEMBER_PORTAL` |
| **P1** | **BUG-006** | RBAC Consistency | RBAC & Permissions | `src/App.jsx`, `config/routes.php` | Align permission tokens with real database entries (`hero_slides`, `boards`, `staff`, etc.) |
| **P1** | **BUG-007** | Admin Dashboard | Dashboard Controller | `app/Controllers/Admin/DashboardController.php` | Replace `members.deleted_at` check with `status != 'resigned'` |
| **P2** | **BUG-008** | Surveys & Polls | Survey Controller | `app/Controllers/Admin/SurveyController.php` | Remove non-existent `survey_responses.deleted_at` |
| **P2** | **BUG-009** | Database Backups | Backup Controller | `app/Controllers/Admin/BackupController.php` | Qualify schema queries with active database name |
| **P3** | **BUG-010** | Control Center | React Page | `src/pages/AdminControlCenterPage.jsx` | Remove trailing slash from `/api/admin/staff/` |

---

## 3. Detailed Fix Specifications

### P0-1: BUG-001 — Fix `DocumentController.php` Schema Alignment
- **File:** `app/Controllers/Admin/DocumentController.php`
- **Root Cause:** Table `document_categories` has columns `(id, name, slug, description, sort_order, status, created_at, updated_at)`. It does NOT have `deleted_at` or `icon`.
- **Target Changes:**
  1. In `index()` line 22: Replace `WHERE deleted_at IS NULL` with `WHERE status = 'active'` (or unconditional with active ordering).
  2. In `apiCategories()` line 103: Replace `WHERE deleted_at IS NULL` with `WHERE status = 'active' ORDER BY sort_order ASC, name ASC`.
  3. In `apiUpdateCategory()`: Remove references to `$data['icon']`.
  4. In `apiDestroyCategory()`: Replace `UPDATE document_categories SET deleted_at = NOW()` with `UPDATE document_categories SET status = 'inactive', updated_at = NOW()`.
- **Verification:**
  - `GET /api/admin/document-categories` returns HTTP 200 with 6 real categories (`data` array length 6).
  - `GET /api/admin/documents` returns HTTP 200 with empty documents array and populated categories.

---

### P0-2: BUG-002 — Upgrade `src/services/memberAdminApi.js`
- **File:** `src/services/memberAdminApi.js`
- **Root Cause:** Signature `memberAdminApi(path, { data, signal })` dropped `method` and `body` when callers passed `{ method: 'PUT', body: ... }`.
- **Target Changes:**
  1. Support options `{ method = 'GET', body, data, headers = {}, signal }`.
  2. Normalize payload: if `data` is provided, use it as payload; if `body` is provided, use it.
  3. For write methods (`POST`, `PUT`, `PATCH`, `DELETE`):
     - Automatically fetch CSRF token from `/api/csrf-token` if not cached.
     - Attach `X-CSRF-TOKEN` header and `X-Requested-With: XMLHttpRequest`.
     - JSON-encode payload if not FormData or string.
- **Verification:**
  - PUT and DELETE requests from Admin forms include CSRF headers, use correct HTTP method, and transmit serialized JSON body.

---

### P0-3: BUG-003 & BUG-004 — Router Enhancement (`config/routes.php`)
- **File:** `config/routes.php`
- **Root Cause:** Frontend expects standard RESTful verbs (`PUT /api/admin/{resource}/{id}`, `DELETE /api/admin/{resource}/{id}`) and endpoint `/api/admin/loans`.
- **Target Changes:**
  1. Add route alias `GET /api/admin/loans` -> `App\Controllers\Admin\LoanProductController@apiIndex`.
  2. Add RESTful routes for Loans and Deposits:
     - `POST /api/admin/loans` -> `LoanProductController@apiStore`
     - `PUT /api/admin/loans/{id}` -> `LoanProductController@apiUpdate`
     - `DELETE /api/admin/loans/{id}` -> `LoanProductController@apiDestroy`
     - `POST /api/admin/deposits` -> `DepositProductController@apiStore`
     - `PUT /api/admin/deposits/{id}` -> `DepositProductController@apiUpdate`
     - `DELETE /api/admin/deposits/{id}` -> `DepositProductController@apiDestroy`
  3. Add RESTful routes for EServices:
     - `PUT /api/admin/eservices/{id}` -> `EServiceController@apiUpdate`
     - `DELETE /api/admin/eservices/{id}` -> `EServiceController@apiDestroy`
  4. Add RESTful routes for Board & Staff:
     - `PUT /api/admin/board-staff/board/{id}` -> `BoardStaffController@apiUpdateBoard`
     - `DELETE /api/admin/board-staff/board/{id}` -> `BoardStaffController@apiDestroyBoard`
     - `PUT /api/admin/board-staff/staff/{id}` -> `BoardStaffController@apiUpdateStaff`
     - `DELETE /api/admin/board-staff/staff/{id}` -> `BoardStaffController@apiDestroyStaff`
  5. Add RESTful routes for Document Categories:
     - `PUT /api/admin/document-categories/{id}` -> `DocumentController@apiUpdateCategory`
     - `DELETE /api/admin/document-categories/{id}` -> `DocumentController@apiDestroyCategory`
- **Verification:**
  - Dispatching `GET /api/admin/loans` returns HTTP 200 with loans and deposits data.
  - Dispatching `PUT` and `DELETE` on all resources routes to the correct controller methods.

---

### P0-4: BUG-005 & BUG-006 — Auth Decoupling & RBAC Alignment
- **Files:** `src/App.jsx`, `src/services/api.js`, `src/context/AuthContext.jsx`
- **Target Changes:**
  1. In `src/App.jsx`:
     - Allow `/admin/login`, `/admin/mfa/*`, and `/admin/*` routes to render regardless of `VITE_FEATURE_MEMBER_PORTAL`.
     - Member Portal routes (`/login`, `/register`, `/forgot-password`, `/member/*`) remain guarded by `memberPortalEnabled`.
  2. In `src/context/AuthContext.jsx`:
     - Ensure session verification (`/api/auth/me`) runs when user is attempting staff access, even if `memberPortalEnabled === false`.
  3. In `src/App.jsx` (RBAC alignment):
     - Update `StaffProtectedRoute` permissions:
       - Hero Slides: require `hero_slides.view` (or Super Admin)
       - Popups: require `popups.view` (or Super Admin)
       - EServices: require `eservices.view` / `eservice_links.view` (or Super Admin)
       - Board & Staff: require `boards.view` / `staff.view` (or Super Admin)
       - Products: require `loan_products.view` / `deposit_products.view` (or Super Admin)
       - Documents: require `documents.view` (or Super Admin)
- **Verification:**
  - Running test suite with `VITE_FEATURE_MEMBER_PORTAL=false` allows full Admin login and route access.
  - Public Member portal routes return 503 Maintenance page.

---

### P1-1: BUG-007 — Fix `DashboardController.php`
- **File:** `app/Controllers/Admin/DashboardController.php`
- **Root Cause:** Query attempts `SELECT COUNT(*) FROM members WHERE deleted_at IS NULL`.
- **Target Changes:**
  - Replace line with: `SELECT COUNT(*) FROM members WHERE status != 'resigned'` (or `WHERE status = 'active'`).
- **Verification:**
  - `GET /admin/dashboard` returns HTTP 200 without SQL errors.

---

### P2-1 & P2-2: BUG-008 & BUG-009 — Surveys & Backups Cleanup
- **Files:** `app/Controllers/Admin/SurveyController.php`, `app/Controllers/Admin/BackupController.php`
- **Target Changes:**
  - `SurveyController.php`: Remove `deleted_at IS NULL` where table `survey_responses` has no `deleted_at`.
  - `BackupController.php`: Ensure table size queries qualify `table_schema = DATABASE()`.

---

## 4. Verification & Testing Strategy

1. **Automated Unit & Integration Test:**
   - Execute `php scripts/test_all_admin_endpoints_real.php` across all 38 endpoints.
   - **Target:** 38 / 38 PASS (100% success rate, 0 failures, 0 SQL errors).
2. **Auth Decoupling Regression Test:**
   - Execute `php tests/AdminLoginMfaDecouplingTest.php`.
   - **Target:** 100% PASS.
3. **Mock Data Removal Verification:**
   - Execute `php tests/ProductionMockDataRemovalTest.php`.
   - **Target:** 100% PASS.
4. **Shell & Layout Integrity Test:**
   - Execute `node scripts/verify_flat_shell_regression.cjs`.
   - **Target:** 0 double headers, 0 broken layouts.
5. **End-to-End Headless Browser UAT:**
   - Execute Playwright headless browser test simulating real Staff Admin session against real backend API:
     - Admin Dashboard metrics check
     - Member Registry (2,508 real members, zero-padding preserved, PII masked)
     - Hero Slides listing (3 real slides)
     - Popups listing (0 records, clean empty state)
     - Board & Staff listing (5 real board members, 0 staff clean empty state)
     - Documents & Categories listing (6 real categories)
     - E-Services listing (5 real active services)
     - Loan & Deposit Products listing (10 loan products, 4 deposit products)
6. **Frontend Production Build:**
   - Execute `npm run build`.
   - **Target:** Zero build errors, clean distribution bundle.

---

## 5. Rollback & Safety Measures

- **Git Commit Isolation:** Each phase will be staged and committed cleanly.
- **Production Protection:** No git push or deployment commands targeting `main`, `production`, or production servers.
- **Financial Immutability:** No schema modifications or UPDATE queries applied to `member_dividends`, `members`, or ledger tables.
