# RYCOOPWEB — FULL DATA SOURCE AUDIT REPORT

**Repository:** `pharmacisttom/rycoopweb`  
**Branch:** `feature/full-data-consistency-recovery`  
**Environment:** TEST ONLY (`https://test.rayongcoop.org`)  
**Production Status:** STRICTLY FROZEN  
**Audit Date:** 2026-11-07  

---

## 1. Executive Summary

A system-wide audit of the RYCOOPWEB platform was conducted across all **18 public route groups**, React frontend components, PHP backend controllers, API endpoints, and MySQL database tables.

The audit identified three major categories of data discrepancies:
1. **Client-side Silos (Critical):** Features operating entirely in the browser using `localStorage` (such as the complaint submission and tracking system in `ContactPage.jsx`), completely bypassing the backend database and admin inbox.
2. **Hardcoded Mock Datasets (High):** Public pages rendering static mock data or fabricated metrics rather than published database records (such as `DocumentsPage.jsx` with fabricated file sizes and download counters, and `WelfarePage.jsx` importing `mockData.js`).
3. **Database Schema Mismatches (High):** API endpoints querying non-existent table names (such as `SpaApiController::welfare()` querying `welfare_types` when the actual table is `welfare`).

---

## 2. Machine-Readable Data Source Audit Matrix

| Public Route | Frontend Component | Displayed Data | Current Data Source | Backend Route | Backend Database Table | Existing Record Count | Mismatch Type | Severity | Recommended Correction |
|---|---|---|---|---|---|---|---|---|---|
| `/` | `HomePage.jsx` | Hero slides, stats, popups, quick links | `PublicContentContext`, `/api/public/popups`, `/api/public/news` | `/api/public/content`, `/api/public/popups` | `site_settings`, `hero_slides`, `popups`, `financial_statistics` | `hero_slides`: 3, `popups`: 0, `financial_statistics`: 4 | Missing fallback indicator | Medium | Connect to published hero slides and popup campaigns; indicate when fallback reference is active. |
| `/about` | `AboutPage.jsx` | History, vision, cooperative profile | `PublicContentContext` (`catalog.coopInfo`) | `/api/public/content` | `site_settings`, `public_content_revisions` | `site_settings`: 24, `public_content_revisions`: 6 | Direct sync | Low | Verified connected to `PublicContentService::catalog()`. |
| `/board`, `/board_committee` | `BoardPage.jsx` | Committee directors, subgroups, advisors, auditors | `BoardTermService` overlay in `PublicContentService` | `/api/public/content`, `/api/public/board-members` | `board_terms`, `board_term_members`, `board_term_groups`, `board_term_group_members` | `board_terms`: 1, `board_term_members`: 21, `board_term_groups`: 3, `board_term_group_members`: 15 | Unified | Resolved | Term 38 reconciled and published; Term 39 draft isolated. |
| `/board_officer` | `BoardOfficerPage.jsx` | Cooperative staff, managers, officers | `PublicContentContext` (`STAFF_MEMBERS`) | `/api/public/content` | `legacyReference.json`, `staff` table | `staff`: 0 | Admin disconnected | Medium | Provide bidirectional sync between `staff` table and `public_content_revisions` staff module. |
| `/contact`, `/complaints` | `ContactPage.jsx` | Complaint submission & tracking | `localStorage` (`coop_member_complaints`) | None (client-side only) | `complaints`, `complaint_logs` | `complaints`: 0, `complaint_logs`: 0 | **Client-side Silo (Fake Submission)** | **Critical** | Replace `localStorage` with `POST /api/public/complaints` and `GET /api/public/complaints/track`, persisting to MySQL `complaints` and `complaint_logs`. |
| `/faqs` | `ContactPage.jsx` | FAQs by category | `mockData.js` / Static array | `/api/public/faqs` | `faqs` | `faqs`: 4 | Hardcoded array override | High | Connect `ContactPage.jsx` to `/api/public/faqs` querying `faqs` table where `status = 'active'`. |
| `/documents` | `DocumentsPage.jsx` | Downloadable forms, regulations, reports | `OFFICIAL_DOCUMENTS` (hardcoded array with fake sizes & downloads) | `/api/public/documents` | `documents`, `document_categories` | `documents`: 0, `document_categories`: 6 | **Fabricated Metrics & Disconnected Data** | **High** | Seed `documents` with verified official forms; connect `DocumentsPage.jsx` to `/api/public/documents`; eliminate fake downloads. |
| `/welfare`, `/associations`, `/mutual-aid` | `WelfarePage.jsx` | Welfare programs, eligibility, grant amounts | `mockData.js` (`WELFARE_ITEMS`) | `/api/public/welfare` | `welfare` | `welfare`: 3 | **Broken API Mapping & Hardcoded Mock Data** | **High** | Fix `SpaApiController::welfare()` to query `welfare` table instead of non-existent `welfare_types`; connect `WelfarePage.jsx` to API. |
| `/deposits`, `/rates` | `DepositsPage.jsx` | Savings & fixed deposit rates, conditions | `PublicContentContext` (`INTEREST_RATES.deposits`) | `/api/public/content`, `/api/public/interest-rates` | `site_settings`, `public_content_revisions`, `deposit_products` | `deposit_products`: 4, `interest_rates`: 16 | Dual product tables | Medium | Reconcile `deposit_products` with `PublicContentService` publication workflow. |
| `/loans` | `LoansPage.jsx` | Loan products, interest rates, borrowing limits | `PublicContentContext` (`LOAN_PRODUCTS`, `INTEREST_RATES.loans`) | `/api/public/content`, `/api/public/loan-products` | `site_settings`, `public_content_revisions`, `loan_products` | `loan_products`: 10, `interest_rates`: 16 | Field naming mismatch | Medium | Reconcile `loan_products` schema (`max_loan_limit` vs `maxLimit`) with public API. |
| `/loan-schedule` | `LoanSchedulePage.jsx` | Annual loan submission and disbursement schedule | Static schedule component (`pay2569.png`) | None | `loan_schedules` | `loan_schedules`: 0 | Static image reference | Low | Verify official image `pay2569.png` existence and provide database fallback. |
| `/calculator`, `/dividend-estimator` | `CalculatorPage.jsx` | Loan calculation & dividend estimation | `PublicContentContext` interest rates | `/api/public/content` | `site_settings`, `financial_statistics` | `financial_statistics`: 4 | Dynamic calculation | Low | Calculations accurately bind to published rates from `PublicContentService`. |
| `/loan-checklist` | `LoanChecklistPage.jsx` | Loan application document readiness checklist | Static checklist | None | None | N/A | Static guidance | Low | Verify accuracy against cooperative loan regulations. |
| `/eservice` | `EServicePage.jsx` | Online services, external links, mobile app | Static links / `eservice_links` | `/api/admin/eservices` | `eservice_links` | `eservice_links`: 5 | Missing public endpoint | Medium | Add public endpoint `/api/public/eservices` reading active `eservice_links`. |
| `/news`, `/announcements`, `/events` | `NewsPage.jsx` | News articles, announcements, events | `/api/public/news`, `/api/public/announcements` | `/api/public/news`, `/api/public/announcements` | `news`, `news_categories`, `important_announcements` | `news`: 2, `news_categories`: 4, `important_announcements`: 0 | Empty announcements table | Medium | Ensure fallback handles empty announcements without broken layout. |
| `/statistics`, `/assets` | `StatisticsPage.jsx` | Financial trends, assets, member statistics | `/api/public/coop-info`, `/api/public/statistics` | `/api/public/statistics` | `financial_statistics` | `financial_statistics`: 4 | Connected | Low | Renders verified statistics from `financial_statistics` table. |
| `/visitor-stats`, `/analytics` | `VisitorStatsPage.jsx` | Visitor counts, pageviews | `/api/public/visitors/analytics` | `/api/public/visitors/analytics` | `visitor_counters`, `website_visits` | `visitor_counters`: 2 | Connected | Low | Real visitor counter telemetry active. |
| `/privacy/policy`, `/privacy/cookies` | `PrivacyPolicyPage.jsx` | PDPA privacy policy & cookie notice | `site_settings` / `PublicContentService` | `/api/public/content` | `site_settings`, `cookie_categories` | `cookie_categories`: 5 | Connected | Low | Approved PDPA policy displayed. |
| `/verify-receipt` | `VerifyReceiptPage.jsx` | Receipt cryptographic verification | `/api/receipts/verify` | `/api/receipts/verify` | `receipts` | N/A | Connected | Low | Cryptographic hash verification verified. |

---

## 3. Discrepancy Breakdown by Category

### Category 1: Client-Side Silos (Critical)
- **Component:** `ContactPage.jsx`
- **Issue:** Random ticket IDs generated client-side (`TKT-${Math.floor(100000 + Math.random() * 900000)}`) and stored in `localStorage.setItem('coop_member_complaints')`.
- **Impact:** Submissions are never transmitted to the server or received by cooperative officers. The tracking system looks up records strictly from the submitting browser's local storage.
- **Remediation:** Replace with `POST /api/public/complaints` and `GET /api/public/complaints/track`, inserting into MySQL tables `complaints` and `complaint_logs` with admin dashboard visibility.

### Category 2: Fabricated Metrics & Unverified Files (High)
- **Component:** `DocumentsPage.jsx`
- **Issue:** `OFFICIAL_DOCUMENTS` array contains 22 entries with invented file sizes (e.g., `540 KB`, `15.2 MB`) and fictitious download counts (e.g., `downloads: 3410`, `downloads: 2150`).
- **Impact:** Misleads members and fails to serve actual verified downloadable files.
- **Remediation:** Connect `DocumentsPage.jsx` to `/api/public/documents`, seed `documents` table with verified approved forms, remove fictitious download numbers, and provide verified storage paths.

### Category 3: Broken API Table Mapping (High)
- **Component:** `WelfarePage.jsx` / `SpaApiController.php`
- **Issue:** `SpaApiController::welfare()` queries `welfare_types` which does not exist in MySQL. The actual table created in Migration 001 is `welfare` (containing 3 active approved records). Due to SQL exception handling, the API returned empty array `[]`, causing `WelfarePage.jsx` to fall back to hardcoded `mockData.js`.
- **Remediation:** Fix `SpaApiController::welfare()` query to read from `welfare` table; update `WelfarePage.jsx` to fetch and render from `/api/public/welfare`.
