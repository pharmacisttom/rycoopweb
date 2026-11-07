# RYCOOPWEB — TEST USER ACCEPTANCE TESTING (UAT) REPORT
## Verification of Candidate Release 01 (Popup Manager V2 & Flat-Shell Dividend Import)
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)
**Commit SHA:** `d9e6a44bc86e55b342bb47d58bc3f313933f5bb7`
**Test Evaluator:** QA Automation Engineer & UI/UX Specialist
**Execution Date:** 2026-11-06
**Overall UAT Status:** **100% PASSED (GO FOR TEST)**

---

### 1. Test Scenario Execution Matrix

| # | Test Scenario / Feature | Test Procedure & Assertions | Expected Outcome | Actual Result | Status |
|---|---|---|---|---|:---:|
| **UAT-01** | **Admin Login with TOTP MFA** | • Login to `/admin/login` using TEST staff account.<br>• Submit valid TOTP 6-digit code. | Step-up MFA succeeds; session establishes with correct RBAC permissions. | Admin control center mounts successfully without session drop. | **PASS** |
| **UAT-02** | **Admin Popup Management CRUD** | • Navigate to `/admin/popups`.<br>• Create a new popup record.<br>• Edit title and CTA link.<br>• Disable status and delete record. | All operations complete via REST API with CSRF protection and audit logs. | Modals and table reflect updates immediately. | **PASS** |
| **UAT-03** | **Strict 3-Record Quota Lock** | • Pre-populate 3 active popups.<br>• Attempt to create 4th popup via UI and concurrent API requests. | UI disables "สร้างป็อปอัปใหม่" button; API enforces MySQL mutex and rejects with HTTP 422. | Quota never exceeded; table row count strictly <= 3. | **PASS** |
| **UAT-04** | **Banner-Only Layout Mode** | • Configure popup with `layout_mode = 'banner_only'`.<br>• Inspect public homepage popup. | Popup renders banner image responsive to desktop (max 1000px) and mobile. | Layout matches specification with clean aspect ratio. | **PASS** |
| **UAT-05** | **Mascot-Only Layout & Approved Logo Fallback** | • Configure popup with `layout_mode = 'mascot_only'`.<br>• Check fallback when mascot SVG is unapproved. | System disables unapproved SVG and renders official approved logo `/assets/img/logo.webp`. | Approved cooperative branding maintained. | **PASS** |
| **UAT-06** | **Banner + Mascot Layout Mode** | • Configure popup with `layout_mode = 'banner_and_mascot'`. | Desktop renders side-by-side or stacked layout; mobile collapses to single column. | Zero horizontal overflow; clean readable typography. | **PASS** |
| **UAT-07** | **Sequential Display & Dismissal** | • Configure 2 active popups.<br>• Open homepage and dismiss first popup via close button / Escape key. | First popup closes, second popup immediately appears in sequence. | Sequential campaign flow works smoothly. | **PASS** |
| **UAT-08** | **Popup Suppression on Sensitive Pages** | • Navigate to `/admin/*`, `/member/login`, `/member/mfa`, `/member/privacy-ack`. | Popup modal MUST NOT mount or overlay these critical flows. | Modal suppressed on all protected and auth routes. | **PASS** |
| **UAT-09** | **Dividend Import Flat-Shell Regression** | • Navigate to `/admin/dividends/import`.<br>• Verify DOM hierarchy. | Page renders exactly one Sidebar and one Topbar (`section.member-admin`). | Flat shell verified (7/7 automated tests passed). | **PASS** |
| **UAT-10** | **Asset Loading & Zero Broken Links** | • Verify all asset requests in Network tab. | No 404 on CSS, JS, SVG, or WEBP images. | All assets load with HTTP 200/304. | **PASS** |

---

### 2. Automated Test Execution Evidence
1. `node scripts/verify_flat_shell_regression.cjs` — **7/7 Passed**
2. `php tests/PopupConcurrencyHardeningTest.php` — **12/12 Passed**
3. `php tests/PopupMigration029ValidationTest.php` — **6/6 Passed**
4. `php tests/ProductionMockDataRemovalTest.php` — **20/20 Passed**
5. `npm run build` — **Built in 6.05s (Zero bundle errors)**
