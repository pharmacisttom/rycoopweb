# RYCOOPWEB — FULL DATA SOURCE AUDIT REPORT

**Repository:** `pharmacisttom/rycoopweb`
**Baseline:** `2bf75982e5b0d1a328fc184c7a84d4fdf41210c2`
**Environment:** TEST ONLY (`https://test.rayongcoop.org`)
**Target Database:** `rycoopweb_test`
**Production Status:** STRICTLY FROZEN
**Audit Status:** READ-ONLY DISCOVERY COMPLETE (Zero mutations during audit)

---

## 1. Confirmed Database Inventory (`rycoopweb_test`)

A direct schema and row-count verification of `rycoopweb_test` reveals the following verified baseline state:

| Table Name | Actual Count | Status / Notes |
|:---|:---:|:---|
| `boards` | 0 | Empty in baseline; public relies on `legacyReference.json` fallback |
| `staff` | 0 | Empty in baseline; public relies on `legacyReference.json` fallback |
| `loan_products` | 10 | Present with 10 records, but contains schema/slug inverted mappings |
| `deposit_products` | 0 | Empty; public relies on `legacyReference.json` |
| `eservice_links` | 0 | Empty in baseline; public displays static hardcoded cards |
| `news` | 0 | Empty in baseline |
| `faqs` | 0 | Empty in baseline; public displays static mock array |
| `documents` | 0 | Empty in baseline; public displays mock downloads & fake sizes |
| `document_categories` | 0 | Empty in baseline |
| `hero_slides` | 0 | Empty in baseline |
| `popups` | 0 | Empty in baseline |
| `complaints` | 0 | Empty; public operates client-side only via `localStorage` |
| `public_content_revisions` | 0 | Empty; content governance not yet initialized |
| `site_settings` | 8 | 8 system keys present (details below) |
| `financial_statistics` | 0 | Empty; statistics page operates on fallback |
| `welfare_types` | **TABLE_NOT_FOUND** | Does not exist in MySQL schema |
| `welfare` | **TABLE_EXISTS** | Real table created in migrations, schema verified |
| `public_content_catalog` | 0 | Empty / Unset |
| `members` | 2,508 | **IMMUTABLE CORE DATA** |
| `users` | 2,510 | **IMMUTABLE CORE DATA** |
| `member_dividends` | 4,855 | **IMMUTABLE FINANCIAL DATA** |

---

## 2. Inspection of the 8 `site_settings` Keys

Inspection of the 8 active configuration keys in `site_settings` without exposing any secrets, tokens, or credential values:

| Key | Group | Public Flag | Value Length | Functional Description | Sanitized Content / Inspection Note |
|:---|:---|:---:|:---:|:---|:---|
| `site_title` | `general` | 1 | 103 chars | Official cooperative entity title | สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด |
| `site_subtitle` | `general` | 1 | 59 chars | Entity tagline & operating slogan | มั่นคง โปร่งใส ใส่ใจบริการ เพื่อสวัสดิการสมาชิก |
| `site_phone` | `contact` | 1 | 22 chars | Primary telephone contact | Contains official cooperative telephone lines |
| `site_fax` | `contact` | 1 | 10 chars | Facsimile line | Contains facsimile number |
| `site_email` | `contact` | 1 | 22 chars | Official contact email | Official Rayong Cooperative email address |
| `site_address` | `contact` | 1 | 194 chars | Physical office headquarters address | อาคารสำนักงานสาธารณสุขจังหวัดระยอง ต.เนินพระ อ.เมือง จ.ระยอง |
| `office_hours` | `contact` | 1 | 169 chars | Public operating hours | วันจันทร์ - วันศุกร์ เวลา 08.30 - 16.30 น. (เว้นวันหยุดราชการ) |
| `facebook_url` | `social` | 1 | 31 chars | Social media page | Official public Facebook page URL |

> [!NOTE]
> All eight keys contain strictly organizational metadata. No secret tokens, database credentials, API private keys, or passwords reside in `site_settings`.

---

## 3. Detailed Reconciliation: 10 Existing `loan_products` vs 8 Legacy Catalog Products

A rigorous comparison between the 10 database rows in `loan_products` and the 8 products in the legacy catalog (`legacyReference.json`) exposes critical field and identity mismatches:

| DB ID | DB Slug | DB Loan Name | DB Interest Rate | DB Max Limit | DB Max Term | Catalog Product ID | Catalog Official Name | Catalog Rate | Catalog Max Limit | Catalog Max Term | Reconciliation Finding |
|:---:|:---|:---|:---:|:---:|:---:|:---:|:---|:---:|:---:|:---:|:---|
| 1 | `emergency-loan` | **เงินกู้สามัญ** | 6.150% | 3,000,000.00 | 180 mo | `ordinary` | เงินกู้สามัญ | 6.15% | 3,000,000 | 180 mo | **Slug Mismatch**: Slug is named `emergency-loan` but row data and name represent Ordinary Loan (`ordinary`). |
| 2 | `general-loan` | **เงินกู้ฉุกเฉิน** | 4.750% | 100,000.00 | 12 mo | `emergency` | เงินกู้ฉุกเฉิน | 4.75% | 200,000 | 12 mo | **Slug & Limit Mismatch**: Slug is named `general-loan` but represents Emergency Loan (`emergency`); DB limit is 100k whereas approved limit is 200k. |
| 3 | `housing-loan` | **เงินกู้พิเศษ** | 5.250% | 5,000,000.00 | 360 mo | `special` | เงินกู้พิเศษ | 5.25% | 3,000,000 | 360 mo | **Slug & Limit Divergence**: Slug is `housing-loan` representing Special Loan (`special`); DB limit is 5M vs approved catalog limit 3M. |
| 4 | `goods-installment-loan` | เงินกู้ผ่อนชำระสินค้า ฯ | 5.500% | 200,000.00 | 36 mo | `goods_installment` | เงินกู้ผ่อนชำระสินค้า ฯ | 5.50% | 300,000 | 84 mo | **Term & Limit Divergence**: DB limit is 200k / 36 mo vs catalog 300k / 84 mo. |
| 5 | `education-loan` | เงินกู้เพื่อการศึกษา | 2.000% | 500,000.00 | 60 mo | `education` | เงินกู้เพื่อการศึกษา | 2.00% | 400,000 | 36 mo | **Term & Limit Divergence**: DB limit is 500k / 60 mo vs catalog 400k / 36 mo. |
| 6 | `life-development-loan` | เงินกู้เพื่อพัฒนาคุณภาพชีวิต | 5.500% | 1,000,000.00 | 120 mo | `life_development` | เงินกู้เพื่อพัฒนาคุณภาพชีวิต | 5.50% | 1,000,000 | 120 mo | **Match**: Identity, rate, limit and term agree with approved catalog. |
| 7 | `car-purchase-loan` | เงินกู้เพื่อจัดซื้อรถยนต์ | 4.000% | 1,500,000.00 | 84 mo | `car_purchase` | เงินกู้เพื่อจัดซื้อรถยนต์ | 4.00% | 1,500,000 | 84 mo | **Match**: Identity, rate, limit and term agree with approved catalog. |
| 8 | `life-security-special-loan` | เงินกู้พิเศษเพื่อความมั่นคงในชีวิต | 5.250% | 3,000,000.00 | 240 mo | `life_security` | เงินกู้พิเศษเพื่อความมั่นคงในชีวิต | 5.25% | 3,000,000 | 360 mo | **Term Divergence**: DB term is 240 mo vs catalog 360 mo. |
| 9 | `debt-restructuring-loan` | เงินกู้เพื่อปรับปรุงโครงสร้างหนี้ | 4.750% | 2,500,000.00 | 180 mo | *None* | *Not in Catalog* | - | - | - | **Extra Product**: DB contains debt-restructuring loan not listed in public catalog. |
| 10 | `guaranteed-loan` | เงินกู้รับการค้ำประกัน | 2.000% | 1,000,000.00 | 60 mo | *None* | *Not in Catalog* | - | - | - | **Extra Product**: DB contains guaranteed loan not listed in public catalog. |

---

## 4. Investigation of Actual Welfare Database Schema

The codebase controllers previously attempted to query `welfare_types`, which resulted in `SQLSTATE[42S02]: Table 'welfare_types' doesn't exist`.

Inspection of the actual MySQL schema confirms the authorized table is **`welfare`**:

```sql
CREATE TABLE `welfare` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `short_description` varchar(255) DEFAULT NULL,
  `full_description` longtext DEFAULT NULL,
  `benefit_amount` varchar(255) DEFAULT NULL,
  `eligibility` text DEFAULT NULL,
  `required_documents` text DEFAULT NULL,
  `application_process` text DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `welfare_slug_unique` (`slug`),
  KEY `welfare_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> [!IMPORTANT]
> **Resolution:** Do NOT create a duplicate `welfare_types` table. Modify backend endpoints to read from and write to the existing `welfare` table.

---

## 5. System-Wide Discrepancy Matrix (18 Public Route Groups)

| # | Public Route | Frontend Component | Displayed Data | Current Source | Backend Route / Table | Actual DB Count | Discrepancy & Security Risk | Severity |
|:---:|:---|:---|:---|:---|:---|:---:|:---|:---:|
| 1 | `/board`, `/board_committee` | `BoardPage.jsx` | Committee Term 38 (15 members, 5 groups, advisors, auditors) | `PublicContentContext` / `legacyReference.json` | `BoardStaffController` / `boards`, `staff` | `boards`: 0, `staff`: 0 | Admin shows 0 records while public renders term 38; no support for preparing term 39. | **Critical** |
| 2 | `/board_officer` | `BoardOfficerPage.jsx` | Cooperative personnel (6 staff) | `STAFF_MEMBERS` / `legacyReference.json` | `BoardStaffController` / `staff` | `staff`: 0 | Admin disconnected from public display. | **High** |
| 3 | `/contact`, `/complaints` | `ContactPage.jsx` | Complaint submission & tracking | `localStorage` (`coop_member_complaints`) | None | `complaints`: 0 | **Client-side Silo**: Complaints never reach the cooperative; tracking relies on browser storage. | **Critical** |
| 4 | `/documents` | `DocumentsPage.jsx` | Forms & regulations | Hardcoded `OFFICIAL_DOCUMENTS` | `SpaApiController` / `documents` | `documents`: 0, `categories`: 0 | **Fabricated Metrics**: Fake download counts (`downloads: 3410`) and unverified file links. | **High** |
| 5 | `/welfare` | `WelfarePage.jsx` | Welfare benefits & eligibility | `mockData.js` (`WELFARE_ITEMS`) | `SpaApiController` / `welfare_types` | `welfare_types`: NOT FOUND | **Broken Mapping**: Queries non-existent table, returns empty, falls back to mock data. | **High** |
| 6 | `/deposits` | `DepositsPage.jsx` | Savings & fixed deposit rates | `PublicContentContext` (`INTEREST_RATES`) | `SpaApiController` / `deposit_products` | `deposit_products`: 0 | Public uses legacy reference; admin `deposit_products` table is empty. | **High** |
| 7 | `/loans` | `LoansPage.jsx` | Loan products & borrowing limits | `PublicContentContext` (`LOAN_PRODUCTS`) | `SpaApiController` / `loan_products` | `loan_products`: 10 | DB slugs inverted (`emergency-loan` = Ordinary Loan); public and DB limits diverge. | **High** |
| 8 | `/faqs` | `ContactPage.jsx` | Frequently Asked Questions | Static array in `mockData.js` | `SpaApiController` / `faqs` | `faqs`: 0 | Public does not consume database FAQs; admin cannot update live FAQs. | **Medium** |
| 9 | `/eservices` | `EServicePage.jsx` | Digital services & portals | Hardcoded `SERVICES` array | `SpaApiController` / `eservice_links` | `eservice_links`: 0 | **Fake Form & Identity**: Fake member defaults (`04892`, `นายเกียรติศักดิ์ พูลเพิ่ม`) and random ticket numbers. | **High** |
| 10 | `/` (Home) | `HomePage.jsx` | Hero slides, stats, popups | `PublicContentContext`, `mockData` | `hero_slides`, `popups`, `financial_statistics` | `hero_slides`: 0, `popups`: 0, `stats`: 0 | Disconnected from backend CMS. | **Medium** |
| 11 | `/calculator` | `CalculatorPage.jsx` | Loan & dividend calculator | `PublicContentContext` rates | None | `interest_rates` | Published catalog overlay | Public calculates using published rates; verified. | **Low** |
| 12 | `/loan-schedule` | `LoanSchedulePage.jsx` | Loan round schedule 2569 | Static image `pay2569.png` | None | `loan_schedules`: 0 | Static image presentation without dynamic admin management. | **Low** |
| 13 | `/loan-checklist` | `LoanChecklistPage.jsx` | Document readiness checklist | Static JSON checklist | None | N/A | Static guidance content; no backend dependency. | **Low** |
| 14 | `/about` | `AboutPage.jsx` | Cooperative history, vision | `PublicContentContext` (`coopInfo`) | `public_content_revisions` | `revisions`: 0 | Relies on fallback reference catalog. | **Medium** |
| 15 | `/news` | `NewsPage.jsx` | Public news & gallery | `/api/public/news` | `news`, `news_categories` | `news`: 0, `categories`: 0 | Empty database table renders empty list cleanly. | **Low** |
| 16 | `/statistics` | `StatisticsPage.jsx` | Multi-year financial metrics | `/api/public/statistics` | `financial_statistics` | `financial_statistics`: 0 | Empty table renders fallback chart cleanly. | **Low** |
| 17 | `/visitor-stats` | `VisitorStatsPage.jsx` | Visitor counters & analytics | `/api/public/visitors/analytics` | `visitor_counters`, `website_visits` | Real counter active | Telemetry functional and PDPA-compliant. | **Low** |
| 18 | `/verify-receipt` | `VerifyReceiptPage.jsx` | Receipt hash verification | `/api/receipts/verify` | None | `receipts` | Public cryptographic validator functional. | **Low** |

---

## 6. Prioritized Remediation Plan (P0 / P1 / P2)

### Priority P0 — Critical Security & Public Trust (Zero Tolerance)
1. **Complaints Subsystem (BUG-001):**
   - Replace client-side `localStorage` with `POST /api/public/complaints` and `GET /api/public/complaints/track`.
   - Store complaints in `complaints` and status events in `complaint_logs`.
   - Implement PII masking (`นา*** มั***`) on public tracking responses.
   - Enforce CSRF token validation and rate limiting.
2. **Downloadable Documents Subsystem (BUG-002):**
   - Remove fabricated download counts and fake file sizes.
   - Connect `DocumentsPage.jsx` to `GET /api/public/documents` joining `document_categories`.
   - Create real download increment endpoint `GET /api/public/documents/{id}/download`.
   - Verify all file paths resolve to physical approved files in `storage/uploads/documents/`.
3. **Welfare Subsystem (BUG-003):**
   - Correct backend query from `welfare_types` to active `welfare` table.
   - Connect `WelfarePage.jsx` to `/api/public/welfare` and eliminate `mockData.js` imports.

### Priority P1 — People, Governance & Financial Alignment
1. **Multi-Term Committee Governance (Phase 2.D):**
   - Deploy Migration `030_create_board_term_management_tables.sql`.
   - Reconcile and publish Committee Term 38 for the public (`/board_committee`).
   - Enable Admin `/admin/board-staff` to create, clone, and edit Committee Term 39 in `draft` status with full optimistic locking.
2. **Loans, Deposits & Products Reconciliation (Phase 2.E):**
   - Correct the inverted slug/name mapping in `loan_products` (`emergency-loan` vs `ordinary`).
   - Align borrowing limits and interest rates with approved cooperative financial documents.
   - Synchronize contact phone numbers across all components to official numbers: `038-967-409`, `086-368-1132`.
   - Purge fabricated interest rate (3.10%) from deposit listings.

### Priority P2 — CMS Modules & Operational Admin
1. **E-Services Subsystem:**
   - Correct column selection in `SpaApiController::eservices()` (`name`, `description`, `url`, `icon`, `is_maintenance`).
   - Eliminate hardcoded fake member IDs and random ticket generators.
   - Implement external redirect security confirmation modal (`confirm_before_redirect`).
2. **CMS Data Alignment:**
   - Connect FAQs, News, Hero Slides, and Popups to database-backed admin workflows.
   - Ensure comprehensive audit logging (`audit_logs`) on all state modifications.

---

## 7. Security Invariants & Testing Strategy

- **Production Freeze:** Strict zero-commit policy to `main`. All work conducted in `feature/full-data-consistency-recovery`.
- **Financial Immutability:** `member_dividends = 4,855`, `members = 2,508`, `users = 2,510` are strictly preserved without alteration.
- **Fail-Closed Enforcement:** All admin routes require active session, RBAC permission, and CSRF token.
- **Automated Quality Gates:**
  - 100% green execution across all PHP regression suites, Node SSR reference test, and Vite production builds.
