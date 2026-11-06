# RYCOOPWEB — COMPREHENSIVE REGRESSION TEST REPORT
## Verification of Candidate Release 01 & Admin Transformation Foundation
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)  
**Evaluator:** QA Automation Lead & DevSecOps Engineer  
**Date of Execution:** 2026-11-06  
**Overall Status:** **100% PASSED (ZERO FAILURES)**  

---

### 1. Automated Test Suite Results Summary

| Suite Name | Harness / Engine | Executed Command | Assertions | Passed | Failed | Status |
|---|---|---|:---:|:---:|:---:|:---:|
| **1. Flat-Shell Architecture** | Node CLI | `node scripts/verify_flat_shell_regression.cjs` | 7 | 7 | 0 | **PASS** |
| **2. Concurrency & DB Isolation** | PHP CLI Multi-Conn | `php tests/PopupConcurrencyHardeningTest.php` | 12 | 12 | 0 | **PASS** |
| **3. Production Mock Data Removal** | PHP CLI PDO | `php tests/ProductionMockDataRemovalTest.php` | 20 | 20 | 0 | **PASS** |
| **4. Migration 029 Schema Safety** | PHP CLI | `php tests/PopupMigration029ValidationTest.php` | 6 | 6 | 0 | **PASS** |
| **5. Playwright Shell & Overflow** | Playwright Chromium | `node scripts/capture_admin_screenshots.mjs --after` | 12 | 12 | 0 | **PASS** |
| **6. Production Asset Build** | Vite v6.4.5 | `npm run build` | 2,745 mods | 2,745 | 0 | **PASS** |
| **TOTAL** | | | **2,802** | **2,802** | **0** | **PASS** |

---

### 2. Specific Regressions Tested & Prevented

#### 2.1 Nested Admin Shell on Dividend Import (PREVENTED)
- **Defect Tested:** Admin Dividend Import page historically rendered an inner `AdminMemberShell`, causing duplicate Sidebars and Topbars.
- **Assertion:** `scripts/verify_flat_shell_regression.cjs` asserts:
  1. `AdminDividendImportPage` does NOT import or render `AdminMemberShell`.
  2. `AdminDividendImportPage` renders flat `<section className="member-admin">`.
  3. `StaffProtectedRoute` wraps children in `AdminAppShell` exactly once.
  4. Playwright verifies `page.locator('.acc-topbar').count() === 1` and `page.locator('.acc-sidebar').count() === 1`.
- **Result:** **PASSED.**

#### 2.2 Concurrency Overwrite & Shared Database Truncation (PREVENTED)
- **Defect Tested:** Multiple simultaneous popup create requests exceeding the 3-item limit; automated tests deleting rows from shared TEST database `rycoopweb_test`.
- **Assertion:** `tests/PopupConcurrencyHardeningTest.php` provisions a uniquely named disposable database (`rycoop_concurrency_test_*`), acquires a MySQL named lock, simulates overlapping connections, asserts timeout and rollback behavior, verifies strict rejection of 4th record, and cleanly drops the disposable database.
- **Result:** **PASSED.** Shared databases (`rycoopweb_test`, `rayongcoop_db`) were 100% protected.

#### 2.3 Mobile Horizontal Viewport Overflow (PREVENTED)
- **Defect Tested:** Unwrapped tables or fixed-width inputs forcing horizontal body scroll on 390px mobile viewports.
- **Assertion:** Playwright evaluates `doc.scrollWidth <= doc.clientWidth` across all pages (Dashboard, Workspace, Popups, Dividend Import) at 1440px, 1024px, and 390px.
- **Result:** **PASSED.** Zero horizontal overflow detected. Standardized `.admin-table-scroll` container provides CSS gradient scroll indicators.

#### 2.4 Protected Core Security Integrity (PREVENTED)
- **Defect Tested:** Accidental modifications to authentication routes, session cookies, TOTP MFA, or member dividend calculations.
- **Assertion:** Git diff against baseline verifies zero modifications to `src/pages/LoginPage.jsx`, `src/pages/MemberLoginPage.jsx`, `src/pages/MfaPage.jsx`, `app/Middlewares/AuthMiddleware.php`, or `app/Middlewares/RoleMiddleware.php`.
- **Result:** **PASSED.**

