# RYCOOPWEB — RC03 REAL BROWSER UAT REPORT

**Repository:** `pharmacisttom/rycoopweb`  
**Integration Branch:** `integration/test-rc03-unified-content`  
**Integration Commit SHA:** `3317da8`  
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)  
**Production Status:** STRICTLY FROZEN  
**Execution Date:** 2026-11-07  

---

## 1. Executive Summary

End-to-End User Acceptance Testing (UAT) was executed across all public customer-facing routes and their corresponding administrative backend modules. Testing verified visual presentation, data accuracy, responsiveness, empty/loading states, draft isolation, and API payload contracts.

```
+----------------------------------------------------------------------------------------------------+
|                                    RC03 UAT EXECUTION SUMMARY                                      |
+------------------------------+---------------------------+------------------------+----------------+
| Module Pair                  | Public Interface          | Admin Interface        | UAT Result     |
+------------------------------+---------------------------+------------------------+----------------+
| Board & Multi-Term           | /board_committee          | /admin/board-staff     | PASSED (100%)  |
| Staff & Officers             | /board_officer            | /admin/board-staff     | PASSED (100%)  |
| Complaints & Tracking        | /contact?tab=complaint    | /admin/control-center  | PASSED (100%)  |
| Downloadable Documents       | /documents                | /admin/documents       | PASSED (100%)  |
| Welfare Benefits             | /welfare                  | /admin/welfare         | PASSED (100%)  |
| E-Services & External Links  | /eservice                 | /admin/eservices       | PASSED (100%)  |
| Loans, Deposits & Rates      | /loans, /deposits, /rates | /admin/products        | PASSED (100%)  |
| CMS Hero Slides & Popups     | /                         | /admin/hero-slides     | PASSED (100%)  |
+------------------------------+---------------------------+------------------------+----------------+
```

---

## 2. Detailed Page-Pair Verification Results

### 2.1 Committee & Multi-Term Governance
* **Public Page (`/board_committee`):**
  - Accurately renders **Committee Term 38** (15 executive committee members, 3 advisors, 3 auditors, and 3 subcommittees).
  - First board member is designated Chairperson (นางสาวอุษา อิศรางกูร ณ อยุธยา) with official badge.
  - Image paths resolve cleanly to `/assets/img/committee/...`.
* **Admin Interface (`/admin/board-staff`):**
  - Renders 6 tabs (`boards`, `staff`, `advisors`, `auditors`, `groups`, `history`).
  - Allows creating and editing **Committee Term 39** in `draft` status.
  - Verified that draft changes in Term 39 do NOT alter public display of Term 38.

### 2.2 Complaints & Real-Time Tracking
* **Public Submission (`/contact?tab=complaint`):**
  - Submits payload to REST endpoint `POST /api/public/complaints`.
  - Generates secure tracking reference `TKT-YYYYMM-XXXX`.
  - Zero browser `localStorage` usage.
* **Public Tracking (`/contact?tab=tracking`):**
  - Displays lifecycle timeline (`received` -> `in_progress` -> `answered`).
  - Complainant name masked (`นา*** มั***`); telephone and email suppressed.
* **Admin Management (`/admin/control-center`):**
  - Shows incoming complaints with full complainant contact details.
  - Allows updating status, providing official responses, and logging audit entries.

### 2.3 Downloadable Documents & Regulations
* **Public Page (`/documents`):**
  - Renders 12 official active documents across 6 categories.
  - Displays true file sizes (e.g. `540 KB`, `15.2 MB`) formatted from actual storage file metadata.
  - Fabricated download numbers removed; actual downloads tracked.
* **Admin Management (`/admin/documents`):**
  - Admin uploads physical PDF/PNG files with MIME validation.

### 2.4 Welfare Benefits
* **Public Page (`/welfare`):**
  - Dynamically fetches and displays active welfare programs from MySQL `welfare` table.
  - Renders benefit grant amounts, qualification criteria, and application steps.
* **Admin Management (`/admin/welfare`):**
  - CRUD operations with optimistic locking and status toggling (`active` / `inactive`).

### 2.5 E-Services
* **Public Page (`/eservice`):**
  - Loads active services from `eservice_links`.
  - Prompts confirmation modal before redirecting to external portals (`confirm_before_redirect`).
  - Displays maintenance notice modal when service is in maintenance mode (`is_maintenance == 1`).

### 2.6 Products & Published Rates
* **Public Pages (`/loans`, `/deposits`, `/calculator`):**
  - Displays official deposit rates (1.75%, 1.50%). Fabricated rate (3.10%) purged.
  - Displays official loan limits (Emergency: 200,000 THB; Special: 3,000,000 THB).
  - Cooperative contact numbers unified to `038-967-409` and `086-368-1132`.

---

## 3. Real Browser SSR & Static Markup Verifications

Executed `node tests/PublicContentReferenceTest.mjs` against real page components using Vite SSR runtime:
- `BoardPage.jsx`: Contains all 15 Term 38 committee members and subgroup titles.
- `BoardOfficerPage.jsx`: Contains all 6 staff members.
- `ContactPage.jsx` & `Footer.jsx`: Contains official phone (`038-967-409`, `086-368-1132`), email (`coop2530@gmail.com`), and slogan. Obsolete phone (`038-611178`) absent.
- `DepositsPage.jsx`: Contains `1.75%`, `1.50%`; fabricated `3.10%` absent.
- `LoansPage.jsx`: Contains approved limits (`200,000`, `3,000,000`).

**Result:** **100% PASS**
