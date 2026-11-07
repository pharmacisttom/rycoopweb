# RYCOOPWEB — COMPLETE ROUTE INVENTORY

**Project:** RYCOOPWEB
**Branch:** `fix/frontend-backend-data-consistency`
**Generated Date:** 6 พฤศจิกายน 2569
**Status:** Verified against `src/App.jsx` and `config/routes.php`

---

## 1. Public Frontend Routes (หน้าสาธารณะ)

| Path | Component | Data Source / API | Access Policy | Cache / Refresh Behavior |
| :--- | :--- | :--- | :--- | :--- |
| `/` | `HomePage` | `/api/public/home`, `usePublicInformation()` | Public | Dynamic fetch on mount / fallback |
| `/about` | `AboutPage` | `usePublicInformation()` (`coopInfo`) | Public | Context catalog / focus reload |
| `/board` | `BoardPage` | `usePublicInformation()` (`boardMembers`) | Public | Context catalog / focus reload |
| `/board_committee` | `BoardPage` | `usePublicInformation()` (`boardMembers`) | Public | Context catalog / focus reload |
| `/board_officer` | `BoardOfficerPage` | `usePublicInformation()` (`staffMembers`) | Public | Context catalog / focus reload |
| `/news` | `NewsPage` | `fetchNews(page)` (`/api/public/news`) | Public | Dynamic pagination, AbortController |
| `/announcements` | `NewsPage` | `fetchAnnouncements()` (`/api/public/announcements`) | Public | Dynamic fetch |
| `/events` | `NewsPage` | `fetchNews()` | Public | Dynamic fetch |
| `/calendar` | `NewsPage` | `fetchNews()` | Public | Dynamic fetch |
| `/documents` | `DocumentsPage` | Currently Hardcoded (`OFFICIAL_DOCUMENTS`) | Public | Client-side filter |
| `/deposits` | `DepositsPage` | `usePublicInformation()` (`interestRates.deposits`) | Public | Context catalog |
| `/rates` | `DepositsPage` | `usePublicInformation()` (`interestRates.deposits`) | Public | Context catalog |
| `/loans` | `LoansPage` | `usePublicInformation()` (`loanProducts`, `interestRates.loans`) | Public | Context catalog |
| `/loan-schedule` | `LoanSchedulePage`| Static calculation & schedules | Public | Client-side |
| `/loans/schedule` | `LoanSchedulePage`| Static calculation & schedules | Public | Client-side |
| `/loan-calendar` | `LoanSchedulePage`| Static calculation & schedules | Public | Client-side |
| `/calculator` | `CalculatorPage` | Loan calculator formula | Public | Client-side |
| `/loan-checklist` | `LoanChecklistPage`| Checklist criteria | Public | Client-side |
| `/loan-readiness` | `LoanChecklistPage`| Checklist criteria | Public | Client-side |
| `/loans/checklist` | `LoanChecklistPage`| Checklist criteria | Public | Client-side |
| `/dividend-estimator` | `CalculatorPage` | Formula calculation | Public | Client-side |
| `/welfare` | `WelfarePage` | Currently Hardcoded (`WELFARE_ITEMS` from mockData) | Public | Static array |
| `/associations` | `WelfarePage` | Hardcoded associations | Public | Static array |
| `/mutual-aid` | `WelfarePage` | Hardcoded mutual aid | Public | Static array |
| `/eservice` | `EServicePage` | Currently Hardcoded (`SERVICES`) | Public (Disabled if static) | Feature flag gate |
| `/contact` | `ContactPage` | `fetchFaqs()`, `usePublicInformation()` | Public | FAQs fetch, form in localStorage |
| `/complaints` | `ContactPage` | `usePublicInformation()` | Public | LocalStorage queue |
| `/faqs` | `ContactPage` | `fetchFaqs()` (`/api/public/faqs`) | Public | Dynamic fetch / fallback |
| `/statistics` | `StatisticsPage` | `/api/public/statistics` | Public | Live database query |
| `/visitor-stats` | `VisitorStatsPage` | `/api/public/visitors/analytics` | Public | Blocked in static mode |
| `/analytics` | `VisitorStatsPage` | `/api/public/visitors/analytics` | Public | Blocked in static mode |
| `/assets` | `StatisticsPage` | `/api/public/statistics` | Public | Live database query |
| `/financial-assets`| `StatisticsPage` | `/api/public/statistics` | Public | Live database query |
| `/privacy/policy` | `PrivacyPolicyPage`| Static text, `COOP_INFO` | Public | Static |
| `/privacy/cookies` | `PrivacyPolicyPage`| Static text | Public | Static |
| `/verify-receipt` | `VerifyReceiptPage`| `/api/verify-receipt` | Public | Verification token query |
| `/service-unavailable` | `ServiceUnavailablePage` | Static warning | Public | Static |

---

## 2. Protected Member Frontend Routes (พอร์ทัลสมาชิก — PROTECTED CORE)

| Path | Component | Guard / Middleware |
| :--- | :--- | :--- |
| `/dashboard` | `MemberDashboardPage` | `MemberProtectedRoute` (requires login, role `member`, PDPA ack) |
| `/member` | `MemberDashboardPage` | `MemberProtectedRoute` |
| `/member/dashboard` | `MemberDashboardPage` | `MemberProtectedRoute` |
| `/member/shares` | `MemberDashboardPage` | `MemberProtectedRoute` |
| `/member/deposits` | `MemberDashboardPage` | `MemberProtectedRoute` |
| `/member/loans` | `MemberDashboardPage` | `MemberProtectedRoute` |
| `/member/loan-requests` | `MemberDashboardPage` | `MemberProtectedRoute` |
| `/member/complaints` | `MemberDashboardPage` | `MemberProtectedRoute` |
| `/member/receipts` | `MemberDashboardPage` | `MemberProtectedRoute` |
| `/member/dividends` | `MemberDividendsPage` | `MemberProtectedRoute` |
| `/member/privacy-ack` | `MemberPrivacyAckPage` | `MemberProtectedRoute` (PDPA Session gate) |
| `/member/profile` | `ProfilePage` | `MemberProtectedRoute` |
| `/settings` | `ProfilePage` | `MemberProtectedRoute` |
| `/edit-profile` | `ProfilePage` | `MemberProtectedRoute` |

---

## 3. Protected Staff & Admin Frontend Routes (ระบบเจ้าหน้าที่และผู้บริหาร — PROTECTED CORE)

| Path | Component | Guard / Middleware |
| :--- | :--- | :--- |
| `/admin/login` | `LoginPage` | Public form -> Staff auth |
| `/admin/mfa/enroll` | `MfaPage` | Pending session -> TOTP QR |
| `/admin/mfa/verify` | `MfaPage` | Pending session -> TOTP Code |
| `/admin` | `AdminControlCenterPage` | `StaffProtectedRoute` |
| `/admin/dashboard` | `AdminControlCenterPage` | `StaffProtectedRoute` |
| `/admin/control-center` | `AdminControlCenterPage` | `StaffProtectedRoute` |
| `/admin/approvals` | `AdminControlCenterPage` (section: approvals) | `StaffProtectedRoute` (Dual Control) |
| `/admin/workspace` | `AdminWorkspacePage` | `StaffProtectedRoute` |
| `/admin/access` | `AdminAccessPage` | `StaffProtectedRoute` (permission: `roles:view`) |
| `/admin/members` | `AdminMembersPage` | `StaffProtectedRoute` (permission: `members:view`) |
| `/admin/members/dashboard` | `AdminMembersDashboardPage` | `StaffProtectedRoute` (permission: `members:view`) |
| `/admin/members/health` | `AdminMembersHealthPage` | `StaffProtectedRoute` (permission: `members:view`) |
| `/admin/members/:id` | `AdminMemberDetailPage` | `StaffProtectedRoute` (permission: `members:view`) |
| `/admin/dividends/import` | `AdminDividendImportPage` | `StaffProtectedRoute` (permission: `dividends:import`) |
| `/admin/board-staff` | `AdminBoardPersonnelPage` | `StaffProtectedRoute` (permission: `people:view`) |
| `/admin/hero-slides` | `AdminHeroSlidesPage` | `StaffProtectedRoute` (permission: `content:view`) |
| `/admin/welfare` | `AdminWelfarePage` | `StaffProtectedRoute` (permission: `welfare:view`) |
| `/admin/eservices` | `AdminEServicesPage` | `StaffProtectedRoute` (permission: `eservices:view`) |
| `/admin/products` | `AdminProductsPage` | `StaffProtectedRoute` (permission: `products:view`) |
| `/admin/document-categories` | `AdminDocumentCategoriesPage` | `StaffProtectedRoute` (permission: `documents:view`) |
| `/admin/content` | `ContentAdminPage` | `StaffProtectedRoute` (permission: `content:view`) |
| `/admin/public-content` | `PublicContentAdminPage` | `StaffProtectedRoute` (permission: `coop_info:view`) |
| `/admin/led/*` | `AdminLed*` Pages | `StaffProtectedRoute` (permission: `led:view`) |

---

## 4. Backend Public REST APIs (`config/routes.php`)

| Method | Endpoint | Controller Action | Target Table / Service |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/public/home` | `SpaApiController@home` | `hero_slides`, `news`, `financial_statistics`, `PublicContentService` |
| `GET` | `/api/public/coop-info` | `SpaApiController@coopInfo` | `PublicContentService`, `financial_statistics` |
| `GET` | `/api/public/interest-rates` | `SpaApiController@interestRates` | `PublicContentService` |
| `GET` | `/api/public/loan-products` | `SpaApiController@loanProducts` | `PublicContentService` |
| `GET` | `/api/public/news` | `SpaApiController@news` | `news` JOIN `news_categories` |
| `GET` | `/api/public/announcements` | `SpaApiController@announcements` | `important_announcements` |
| `GET` | `/api/public/documents` | `SpaApiController@documents` | `documents` |
| `GET` | `/api/public/welfare` | `SpaApiController@welfare` | *BUG:* queries `welfare_types` instead of `welfare` |
| `GET` | `/api/public/faqs` | `SpaApiController@faqs` | `faqs` |
| `GET` | `/api/public/board-members` | `SpaApiController@boardMembers` | `PublicContentService::catalog()` |
| `GET` | `/api/public/statistics` | `SpaApiController@statistics` | `financial_statistics` |
| `GET` | `/api/public/visits/stats` | `SpaApiController@visitorStats` | `visitor_counters` |
| `GET` | `/api/public/visitors/summary`| `SpaApiController@visitorStats` | `visitor_counters` |
| `GET` | `/api/public/visits/analytics`| `SpaApiController@visitorAnalytics`| `website_visits` |
| `GET` | `/api/public/visitors/analytics`| `SpaApiController@visitorAnalytics`| `website_visits` |
| `POST`| `/api/visits/record` | `SpaApiController@recordVisit` | `visitor_counters`, `website_visits` |
| `GET` | `/api/public/content` | `PublicContentController@publicCatalog`| `PublicContentService::catalog()` |
