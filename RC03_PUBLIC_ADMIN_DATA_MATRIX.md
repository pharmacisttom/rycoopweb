# RYCOOPWEB — RC03 PUBLIC / ADMIN END-TO-END DATA MATRIX

**Repository:** `pharmacisttom/rycoopweb`
**Integration Branch:** `integration/test-rc03-unified-content`
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)
**Production Status:** STRICTLY FROZEN

---

## 1. End-to-End Traceability Matrix

Every public customer-facing interface is mapped to its authoritative backend API, PHP controller, domain service, database table, administrative management interface, and RBAC permission requirements.

| # | Public Route | Public Component | Public API Endpoint | PHP Controller & Service | Authoritative MySQL Table(s) | Admin Route | Admin Component | Required Admin Permission |
|:---:|:---|:---|:---|:---|:---|:---|:---|:---|
| 1 | `/board`, `/board_committee` | `BoardPage.jsx` | `GET /api/public/content`, `GET /api/public/board-members` | `SpaApiController` / `BoardTermService` | `board_terms`, `board_term_members`, `board_term_groups` | `/admin/board-staff` | `AdminBoardPersonnelPage.jsx` | `people.view`, `boards.update` |
| 2 | `/board_officer` | `BoardOfficerPage.jsx` | `GET /api/public/content` | `SpaApiController` / `PublicContentService` | `public_content_revisions` (`staff`), `staff` | `/admin/board-staff?tab=staff` | `AdminBoardPersonnelPage.jsx` | `people.view`, `staff.update` |
| 3 | `/contact?tab=complaint` | `ContactPage.jsx` | `POST /api/public/complaints` | `SpaApiController` | `complaints`, `complaint_logs` | `/admin/control-center` / `/admin/complaints` | `AdminControlCenterPage.jsx` | `complaints.view`, `complaints.update` |
| 4 | `/contact?tab=tracking` | `ContactPage.jsx` | `GET /api/public/complaints/track` | `SpaApiController` | `complaints`, `complaint_logs` | `/admin/control-center` | `AdminControlCenterPage.jsx` | Public masked; Admin full view |
| 5 | `/documents` | `DocumentsPage.jsx` | `GET /api/public/documents`, `GET /api/public/documents/{id}/download` | `SpaApiController` / `DocumentController` | `documents`, `document_categories` | `/admin/documents`, `/admin/document-categories` | `AdminStoredContentPage.jsx`, `AdminDocumentCategoriesPage.jsx` | `documents.view`, `documents.update` |
| 6 | `/welfare`, `/associations` | `WelfarePage.jsx` | `GET /api/public/welfare` | `SpaApiController` / `WelfareController` | `welfare` | `/admin/welfare` | `AdminWelfarePage.jsx` | `welfare.view`, `welfare.update` |
| 7 | `/eservice` | `EServicePage.jsx` | `GET /api/public/eservices` | `SpaApiController` / `EServiceController` | `eservice_links` | `/admin/eservices` | `AdminEServicesPage.jsx` | `eservices.view`, `eservices.update` |
| 8 | `/loans` | `LoansPage.jsx` | `GET /api/public/loan-products`, `GET /api/public/interest-rates` | `SpaApiController` / `PublicContentService` | `loan_products`, `interest_rates`, `public_content_revisions` | `/admin/products`, `/admin/public-content` | `AdminProductsPage.jsx`, `PublicContentAdminPage.jsx` | `products.view`, `rates.view` |
| 9 | `/deposits` | `DepositsPage.jsx` | `GET /api/public/interest-rates` | `SpaApiController` / `PublicContentService` | `deposit_products`, `interest_rates` | `/admin/products`, `/admin/public-content` | `AdminProductsPage.jsx` | `products.view`, `rates.view` |
| 10 | `/calculator` | `CalculatorPage.jsx` | `GET /api/public/content`, `GET /api/public/interest-rates` | `SpaApiController` / `PublicContentService` | `public_content_revisions`, `interest_rates` | `/admin/public-content` | `PublicContentAdminPage.jsx` | `rates.update` |
| 11 | `/faqs` | `ContactPage.jsx` | `GET /api/public/faqs` | `SpaApiController` / `AdminCrudService` | `faqs` | `/admin/content?tab=faqs` | `ContentAdminPage.jsx` | `faqs.view`, `faqs.update` |
| 12 | `/news` | `NewsPage.jsx` | `GET /api/public/news` | `SpaApiController` / `AdminCrudService` | `news`, `news_categories` | `/admin/content?tab=news` | `ContentAdminPage.jsx` | `news.view`, `news.update` |
| 13 | `/announcements` | `HomePage.jsx` / `NewsPage.jsx` | `GET /api/public/announcements` | `SpaApiController` / `AdminCrudService` | `important_announcements` | `/admin/content?tab=announcements` | `ContentAdminPage.jsx` | `announcements.view` |
| 14 | `/` (Hero Slides) | `HomePage.jsx` | `GET /api/public/hero-slides` | `SpaApiController` | `hero_slides` | `/admin/hero-slides` | `AdminHeroSlidesPage.jsx` | `hero_slides.view`, `hero_slides.update` |
| 15 | `/` (Popups) | `HomePage.jsx` | `GET /api/public/popups` | `SpaApiController` / `PopupService` | `popups`, `popup_pages`, `popup_events` | `/admin/popups` | `AdminPopupsPage.jsx` | `popups.view`, `popups.update` |
| 16 | `/contact` (Coop Info) | `ContactPage.jsx`, `Footer.jsx` | `GET /api/public/coop-info`, `GET /api/public/content` | `SpaApiController` / `PublicContentService` | `site_settings`, `public_content_revisions` | `/admin/public-content` | `PublicContentAdminPage.jsx` | `coop_info.view`, `coop_info.update` |
| 17 | `/statistics` | `StatisticsPage.jsx` | `GET /api/public/statistics` | `SpaApiController` | `financial_statistics` | `/admin/dashboard` | `AdminDashboardPage.jsx` | `dashboard.view` |
| 18 | `/visitor-stats` | `VisitorStatsPage.jsx` | `GET /api/public/visitors/analytics`, `POST /api/visits/record` | `SpaApiController` / `VisitorCounterService` | `visitor_counters`, `website_visits` | `/admin/workspace` | `VisitorStatsPage.jsx` | `analytics.view` |

---

## 2. Key Data Consistency Guarantees

1. **Zero Disconnected Silos:** Every public component renders directly from its corresponding API endpoint backed by real database records.
2. **Zero Fabricated Fallbacks:** Where data is not yet approved in the database, the UI displays a clean, truthful "อยู่ระหว่างการปรับปรุงข้อมูล" empty state rather than fabricated numbers or mock files.
3. **Draft Isolation:** In multi-term committee governance, Term 39 draft changes remain strictly isolated until published by an authorized administrator.
