# RYCOOPWEB — BACKEND BUG REGISTER

**Repository:** `pharmacisttom/rycoopweb`  
**Branch:** `feature/backend-recovery-data-integrity`  
**Environment:** TEST ONLY  
**Date:** 2026-11-07  

---

## 1. Summary of Identified Defects

| Bug ID | Module | Severity | Title | Affected File(s) | Status |
|---|---|:---:|---|---|:---:|
| **BUG-001** | Documents / Categories | **CRITICAL** | `document_categories` SQL crash on non-existent `deleted_at` and `icon` columns (HTTP 500) | `app/Controllers/Admin/DocumentController.php` | Pending Fix |
| **BUG-002** | Frontend API Service | **CRITICAL** | `memberAdminApi.js` ignores `method`, `body`, and headers when `data` is omitted (HTTP 404 / 405) | `src/services/memberAdminApi.js` | Pending Fix |
| **BUG-003** | Financial Products | **CRITICAL** | Products page calls `/api/admin/loans` which is missing from backend router (HTTP 404) | `config/routes.php`, `src/pages/AdminProductsPage.jsx` | Pending Fix |
| **BUG-004** | EServices & Personnel | **CRITICAL** | RESTful PUT / DELETE route mismatches on EServices, Board, and Categories (HTTP 404) | `config/routes.php` | Pending Fix |
| **BUG-005** | Authentication Gate | **CRITICAL** | Staff login and routes disabled by `VITE_FEATURE_MEMBER_PORTAL=false` on branch (HTTP 503) | `src/App.jsx`, `src/context/AuthContext.jsx`, `src/services/api.js` | Pending Fix |
| **BUG-006** | RBAC Permissions | **HIGH** | Routes check non-existent permission modules (`content`, `people`, `products`) | `src/App.jsx`, `config/routes.php` | Pending Fix |
| **BUG-007** | Dashboard Metrics | **HIGH** | `DashboardController.php` crashes with SQL error on `members.deleted_at` | `app/Controllers/Admin/DashboardController.php` | Pending Fix |
| **BUG-008** | Surveys | **MEDIUM** | `SurveyController.php` queries non-existent `deleted_at` column on `survey_responses` | `app/Controllers/Admin/SurveyController.php` | Pending Fix |
| **BUG-009** | Database Backups | **MEDIUM** | `BackupController.php` queries unqualified `information_schema` table | `app/Controllers/Admin/BackupController.php` | Pending Fix |
| **BUG-010** | Control Center Navigation | **LOW** | Trailing slash in `/api/admin/staff/` request | `src/pages/AdminControlCenterPage.jsx` | Pending Fix |

---

## 2. Detailed Bug Profiles

### BUG-001: `document_categories` SQL Crash on Missing Columns (Critical)
- **Severity:** Critical (Blocks entire Document & Category administration)
- **Component:** PHP Backend (`DocumentController.php`)
- **Symptoms:** Calling `GET /api/admin/document-categories` or `GET /api/admin/documents` throws `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'deleted_at' in 'where clause'`.
- **Root Cause:** Table `document_categories` was designed without soft-delete (`deleted_at`) and without `icon`. Instead, it uses `status enum('active','inactive')`.
- **Fix:** Update all queries in `DocumentController` to omit `deleted_at` and `icon`, filtering by `status = 'active'` or total records.

### BUG-002: `memberAdminApi.js` Ignored Non-Data Request Options (Critical)
- **Severity:** Critical (Breaks update and delete across multiple Admin forms)
- **Component:** React Frontend Service (`src/services/memberAdminApi.js`)
- **Symptoms:** Submitting updates or deletions in E-Services, Products, Board & Staff, or Categories sends a plain `GET` request without CSRF token or body.
- **Root Cause:** `memberAdminApi(path, { data, signal })` only inspected `data`. When passed `{ method: 'PUT', body: ... }`, `data` was `undefined`.
- **Fix:** Enhance `memberAdminApi` to accept `method`, `body`, and custom headers, automatically obtaining and attaching `X-CSRF-TOKEN` for any write request (`POST`, `PUT`, `PATCH`, `DELETE`).

### BUG-003: Products Page Endpoint Mismatch (Critical)
- **Severity:** Critical (Financial Products page cannot load)
- **Component:** Router & Frontend (`config/routes.php`, `src/pages/AdminProductsPage.jsx`)
- **Symptoms:** Navigating to `/admin/products` triggers `GET /api/admin/loans`, returning HTTP 404 Not Found.
- **Root Cause:** Backend route was registered as `GET /api/admin/products`, but frontend requested `GET /api/admin/loans`.
- **Fix:** Register `GET /api/admin/loans` as a canonical alias pointing to `LoanProductController@apiIndex` (which returns `{ loans, deposits }`), and add matching `POST`, `PUT`, `DELETE` routes for loans and deposits.

### BUG-004: RESTful Route Mismatches for Write Operations (Critical)
- **Severity:** Critical (CRUD update/delete actions fail with 404)
- **Component:** Backend Router (`config/routes.php`)
- **Symptoms:** Frontend dispatches `PUT /api/admin/eservices/{id}`, `DELETE /api/admin/eservices/{id}`, `PUT /api/admin/board-staff/board/{id}`, `DELETE /api/admin/board-staff/board/{id}`, etc., but backend returns 404.
- **Root Cause:** Backend routes were only registered as POST `/{id}/update` and POST `/{id}/delete`.
- **Fix:** Register both RESTful routes (`PUT /{id}`, `DELETE /{id}`) and action routes (`POST /{id}/update`, `POST /{id}/delete`) in `config/routes.php`.

### BUG-005: Decouple Admin Portal from Member Portal Flag (Critical)
- **Severity:** Critical (Blocks entire staff portal on TEST environment)
- **Component:** Auth & Routing (`src/App.jsx`, `src/context/AuthContext.jsx`, `src/services/api.js`)
- **Symptoms:** Staff members accessing `/admin/login` or protected admin routes receive 503 Service Unavailable when `VITE_FEATURE_MEMBER_PORTAL=false`.
- **Root Cause:** Admin routes were guarded with `!memberPortalEnabled`.
- **Fix:** Decouple admin authentication: allow `/admin/login` and `StaffProtectedRoute` to operate independently of the public member portal flag, while keeping `/login` and `/member/*` strictly disabled.

### BUG-006: RBAC Module Mismatches (High)
- **Severity:** High (Staff members with valid permissions receive 403 Forbidden)
- **Component:** Routing & RBAC (`src/App.jsx`, `config/routes.php`)
- **Symptoms:** Non-superadmin staff are denied access to Hero Slides, Popups, EServices, Board & Staff, Products.
- **Root Cause:** Frontend checked `content.view`, `people.view`, and `products.view`. Backend middleware checked `news`, `settings`, and `people`. Database permissions are `hero_slides`, `popups`, `eservice_links`, `boards`, `staff`, `deposit_products`, `loan_products`.
- **Fix:** Align frontend route guards and backend `PermissionMiddleware` with the exact permissions in the database, allowing granular permissions or role fallbacks.

### BUG-007: `DashboardController.php` Crash on `members.deleted_at` (High)
- **Severity:** High (Admin Dashboard statistics throw unhandled 500 error)
- **Component:** Backend Controller (`DashboardController.php`)
- **Symptoms:** `GET /admin/dashboard` fails with SQL 1054 error.
- **Root Cause:** Query executed `SELECT COUNT(*) FROM members WHERE deleted_at IS NULL`.
- **Fix:** Replace with `SELECT COUNT(*) FROM members WHERE status != 'resigned'` (or total count).
