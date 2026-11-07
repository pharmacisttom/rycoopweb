# RYCOOPWEB — PHASED IMPLEMENTATION PLAN

**Repository:** `pharmacisttom/rycoopweb`  
**Branch:** `feature/full-data-consistency-recovery`  
**Environment:** TEST ONLY  
**Target:** Incremental Module-by-Module Remediation with Dedicated Commits and Automated Tests  

---

## Phase Overview

| Module | Focus Area | Key Changes | Test Strategy |
|---|---|---|---|
| **Module 1** | **Complaints & Member Voice** | Eliminate `localStorage` in `ContactPage.jsx`; implement `POST /api/public/complaints` and `GET /api/public/complaints/track` persisting to `complaints` & `complaint_logs` with admin inbox | `ComplaintWorkflowTest.php` |
| **Module 2** | **Welfare Programs** | Fix `SpaApiController::welfare()` table target from missing `welfare_types` to `welfare`; remove `mockData.js` in `WelfarePage.jsx` and connect to real API | `WelfareWorkflowTest.php` |
| **Module 3** | **Downloadable Documents** | Eliminate hardcoded `OFFICIAL_DOCUMENTS` with fabricated sizes and fake downloads; connect `DocumentsPage.jsx` to `GET /api/public/documents` | `DocumentsWorkflowTest.php` |
| **Module 4** | **E-Services & FAQs** | Implement `GET /api/public/eservices`; bind `EServicePage.jsx`; bind `ContactPage.jsx` FAQ section to `GET /api/public/faqs` querying `faqs` table | `EservicesFaqsConsistencyTest.php` |
| **Module 5** | **Products & Rates Reconciliation** | Verify alignment between `loan_products`, `deposit_products`, and `PublicContentService::catalog()` | `ProductsRatesConsistencyTest.php` |
| **Module 6** | **Final Quality Gates & Verification** | Run all CI Quality Gates (PHP 8.3/8.5, SSR tests, security regressions, Vite build, whitespace check); produce UAT and release reports | Full CI suite run |

---

## Detailed Step-by-Step Execution

### Module 1: Complaints Workflow Remediation
1. **Backend API:**
   - Add `POST /api/public/complaints` in `SpaApiController.php`: validates input (`name`, `phone`, `email`, `topic`, `message`), generates ticket `TKT-YYYYMM-XXXXX`, saves to `complaints`, adds initial entry to `complaint_logs`.
   - Add `GET /api/public/complaints/track` in `SpaApiController.php`: fetches complaint status, sanitized officer reply, timeline logs without exposing complainant phone/email.
   - Register routes in `config/routes.php`.
2. **Frontend UI:**
   - Update `src/pages/ContactPage.jsx`: replace `localStorage.getItem/setItem('coop_member_complaints')` with `fetch('/api/public/complaints')` and tracking lookup via `/api/public/complaints/track`.
3. **Automated Testing:**
   - Create `tests/ComplaintWorkflowTest.php`: tests valid submission, ticket generation, database persistence, log creation, tracking response, validation errors, and PII masking.
4. **Commit:** `feat(complaints): replace client-side localstorage with secure server-side complaints workflow`

---

### Module 2: Welfare Programs Remediation
1. **Backend API:**
   - Update `SpaApiController::welfare()`: query `welfare` table instead of non-existent `welfare_types`. Format columns: `id, category, title, slug, short_description, full_description, benefit_amount, eligibility, required_documents, application_process, contact_info, sort_order, status`.
2. **Frontend UI:**
   - Update `src/pages/WelfarePage.jsx`: fetch from `/api/public/welfare`; remove `import { WELFARE_ITEMS } from '../data/mockData'`.
3. **Automated Testing:**
   - Create `tests/WelfareWorkflowTest.php`: tests API returns 3 active programs, database fields present, inactive programs excluded.
4. **Commit:** `feat(welfare): connect welfare page to active database records and eliminate mock data`

---

### Module 3: Downloadable Documents Remediation
1. **Backend Database:**
   - Seed `documents` table with approved official documents categorized matching `document_categories`, with legitimate paths and clean metadata (no fake download counts).
2. **Frontend UI:**
   - Update `src/pages/DocumentsPage.jsx`: fetch from `/api/public/documents`, render verified documents, show actual file types and sizes.
3. **Automated Testing:**
   - Create `tests/DocumentsWorkflowTest.php`: tests document listing, categorization, real download tracking.
4. **Commit:** `feat(documents): eliminate fabricated download counts and connect documents to verified backend storage`

---

### Module 4: E-Services & FAQs Remediation
1. **Backend API:**
   - Add `GET /api/public/eservices` in `SpaApiController.php` querying `eservice_links` where `status = 'active'`.
   - Ensure `GET /api/public/faqs` returns categorized FAQs.
2. **Frontend UI:**
   - Update `src/pages/EServicePage.jsx` to fetch from `/api/public/eservices`.
   - Update `src/pages/ContactPage.jsx` FAQ accordion to fetch from `/api/public/faqs`.
3. **Commit:** `feat(eservices-faqs): connect e-services and faqs to authoritative database tables`

---

### Module 5: Products, Rates & Governance Finalization
1. Verify `PublicContentService::catalog()` alignment with `loan_products` and `deposit_products`.
2. Ensure consistent official contact phone numbers across all footers and headers (`038-967-409`, `086-368-1132`).
3. Commit: `feat(products): reconcile loan and deposit products with authoritative publication catalog`

---

### Module 6: End-to-End Verification & Documentation
1. Run all test suites.
2. Produce `REAL_BROWSER_UAT_REPORT.md` and `RELEASE_READINESS.md`.
3. Push to `pharmacisttom/rycoopweb` on `feature/full-data-consistency-recovery`.
