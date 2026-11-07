# RYCOOPWEB — CRITICAL BUG REGISTER & REMEDIATION LOG

**Repository:** `pharmacisttom/rycoopweb`
**Branch:** `feature/full-data-consistency-recovery`
**Environment:** TEST ONLY
**Date:** 2026-11-07

---

## 1. Summary of Bug Classifications

| Bug ID | Title | Module | Severity | Status |
|---|---|---|---|---|
| **BUG-001** | Complaint submission and tracking stored in `localStorage` | Complaints / Contact | **Critical** | In Remediation |
| **BUG-002** | Downloadable documents render fabricated sizes and fake download counts | Documents | **High** | In Remediation |
| **BUG-003** | Broken query targeting `welfare_types` instead of `welfare` table; mockData fallback | Welfare | **High** | In Remediation |
| **BUG-004** | Disconnection between `/board_committee` and Admin `/admin/board-staff` | Governance | **High** | Resolved in Migration 030 |
| **BUG-005** | FAQs rendered from static array instead of MySQL `faqs` table | FAQs | **Medium** | In Remediation |
| **BUG-006** | Missing public API endpoint for online services (`eservice_links`) | E-Services | **Medium** | In Remediation |
| **BUG-007** | Public contact phone number mismatch across pages | Public Content | **Medium** | Resolved |
| **BUG-008** | Loan schedule dependency on static unverified asset | Loans | **Low** | In Remediation |

---

## 2. Detailed Bug Profiles & Remediation Plans

### BUG-001: Complaint Submission & Tracking Client-Side Silo
- **Severity:** Critical
- **Component:** `src/pages/ContactPage.jsx`
- **Description:** Submitting a complaint generated a random ticket `TKT-XXXXXX` and stored it in browser `localStorage.setItem('coop_member_complaints')`. Submissions never reached the server, cooperative staff received zero notifications, and tracking was local to the user's browser.
- **Root Cause:** Incomplete SPA prototype code was left in place without wiring to backend API.
- **Remediation:**
  1. Implement `POST /api/public/complaints` in PHP backend.
  2. Implement `GET /api/public/complaints/track` in PHP backend.
  3. Store complaints in MySQL table `complaints` and log initial status in `complaint_logs`.
  4. Update `ContactPage.jsx` to dispatch real API calls and render tracking results from server response.
  5. Mask complainant phone/email in public tracking endpoint to prevent PII exposure.

---

### BUG-002: Fabricated Document Sizes & Download Numbers
- **Severity:** High
- **Component:** `src/pages/DocumentsPage.jsx`
- **Description:** The page rendered `OFFICIAL_DOCUMENTS` with hardcoded file sizes (e.g., `540 KB`, `15.2 MB`) and fabricated download counts (e.g., `downloads: 3410`, `downloads: 2150`), while the backend `documents` table contained 0 rows.
- **Root Cause:** Static mock data was used instead of binding to `documents` table and verified TEST file storage.
- **Remediation:**
  1. Connect `DocumentsPage.jsx` to `GET /api/public/documents`.
  2. Seed `documents` table with verified official cooperative forms and regulations.
  3. Format actual file sizes from disk (or format as verified PDF/DOCX without fabricated metrics).
  4. Real download counter that increments only when legitimate downloads occur.

---

### BUG-003: Broken Welfare Query & Mock Data Fallback
- **Severity:** High
- **Component:** `app/Controllers/Api/SpaApiController.php:203` & `src/pages/WelfarePage.jsx`
- **Description:** `SpaApiController::welfare()` queried `SELECT ... FROM welfare_types`, but the database table is named `welfare`. The exception was swallowed, returning `data: []`, which caused `WelfarePage.jsx` to render hardcoded `WELFARE_ITEMS` from `mockData.js`.
- **Root Cause:** Table name mismatch between controller query (`welfare_types`) and schema definition (`welfare`).
- **Remediation:**
  1. Fix SQL query in `SpaApiController::welfare()` to read from `welfare` table.
  2. Map database columns (`benefit_amount`, `eligibility`, `required_documents`, etc.) to public API response.
  3. Refactor `WelfarePage.jsx` to fetch from `/api/public/welfare` and remove `mockData.js` import.

---

### BUG-004: Board & Committee Admin/Public Disconnection
- **Severity:** High
- **Component:** `src/pages/BoardPage.jsx` & `src/pages/AdminBoardPersonnelPage.jsx`
- **Description:** `/board_committee` read from `legacyReference.json`, while `/admin/board-staff` read from empty MySQL `boards` table (0 rows).
- **Status:** **Resolved** via Migration 030, `BoardTermService`, and `feature/rc02-board-term-management`.

---

### BUG-005: FAQs Static Hardcoding in Contact Page
- **Severity:** Medium
- **Component:** `src/pages/ContactPage.jsx`
- **Description:** FAQs displayed in `ContactPage.jsx` were hardcoded in the component instead of reading the active FAQs in the `faqs` table.
- **Remediation:** Fetch FAQs from `/api/public/faqs` and render dynamically with category grouping.

---

### BUG-006: E-Services Missing Public API Endpoint
- **Severity:** Medium
- **Component:** `src/pages/EServicePage.jsx`
- **Description:** E-Services were managed in Admin via `/api/admin/eservices` on `eservice_links`, but no dedicated public endpoint existed for `EServicePage.jsx`.
- **Remediation:** Add `GET /api/public/eservices` and bind `EServicePage.jsx` to it.
