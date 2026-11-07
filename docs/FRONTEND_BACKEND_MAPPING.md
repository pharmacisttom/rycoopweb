# RYCOOPWEB — FRONTEND–BACKEND INTEGRATION MAPPING

**Project:** RYCOOPWEB
**Branch:** `fix/frontend-backend-data-consistency`
**Generated Date:** 6 พฤศจิกายน 2569
**Specification:** Master Prompt Section 5.4

---

## 1. Public Content Modules Integration Matrix

| Module Name | Frontend Route | Frontend Component | API Endpoint | Backend Controller | Business Service / Engine | Database Table | Admin Management Interface | Status Classification |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **1. คณะกรรมการดำเนินการ (Board Committee)** | `/board`, `/board_committee` | `BoardPage.jsx` | `/api/public/content` (Uncalled: `/api/public/board-members`) | `PublicContentController` (Uncalled: `SpaApiController`) | `PublicContentService` | `public_content_revisions` (Broken) vs `boards` (Admin) | `/admin/board-staff` (`AdminBoardPersonnelPage`) | **BROKEN** |
| **2. ฝ่ายจัดการและเจ้าหน้าที่ (Management & Staff)** | `/board_officer` | `BoardOfficerPage.jsx` | `/api/public/content` | `PublicContentController` | `PublicContentService` | `public_content_revisions` (Broken) vs `staff` (Admin) | `/admin/board-staff` (`AdminBoardPersonnelPage`) | **BROKEN** |
| **3. หน้าหลักและแบนเนอร์ (Home & Hero Slider)** | `/` | `HomePage.jsx` | `/api/public/home` | `SpaApiController@home` | `Database`, `PublicContentService` | `hero_slides`, `news`, `financial_statistics` | `/admin/hero-slides` (`AdminHeroSlidesPage`) | **PARTIALLY_CONNECTED** |
| **4. ข่าวสารและกิจกรรม (News & Activities)** | `/news`, `/announcements`, `/events` | `NewsPage.jsx` | `/api/public/news?page=X` | `SpaApiController@news` | `Database` | `news`, `news_categories` | `/admin/content` (`ContentAdminPage`) | **CONNECTED** |
| **5. เอกสารและแบบฟอร์ม (Documents & Downloads)** | `/documents` | `DocumentsPage.jsx` | *None* (Backend has `/api/public/documents`) | *None* (Backend has `SpaApiController@documents`) | *None* (Backend has `Database`) | `documents`, `document_categories` | `/admin/document-categories` (`AdminDocumentCategoriesPage`) | **HARDCODED** |
| **6. สวัสดิการสมาชิก (Member Welfare)** | `/welfare`, `/associations`, `/mutual-aid` | `WelfarePage.jsx` | *None* (Backend has `/api/public/welfare` with SQL bug) | *None* (Backend has `SpaApiController@welfare`) | *None* (Backend has `Database`) | `welfare` | `/admin/welfare` (`AdminWelfarePage`) | **HARDCODED** & **BROKEN** |
| **7. บริการออนไลน์ (E-Services)** | `/eservice` | `EServicePage.jsx` | *None* (Backend `/api/public/home` has eservices) | *None* (Backend has `SpaApiController@home`) | *None* | `eservice_links` | `/admin/eservices` (`AdminEServicesPage`) | **HARDCODED** |
| **8. ผลิตภัณฑ์เงินฝากและอัตราดอกเบี้ย (Deposits & Rates)** | `/deposits`, `/rates` | `DepositsPage.jsx` | `/api/public/content`, `/api/public/interest-rates` | `PublicContentController`, `SpaApiController` | `PublicContentService` | `deposit_products`, `interest_rates`, `site_settings` | `/admin/public-content`, `/admin/products` | **PARTIALLY_CONNECTED** |
| **9. ผลิตภัณฑ์เงินกู้และสินเชื่อ (Loans & Loan Products)** | `/loans`, `/loan-schedule`, `/calculator` | `LoansPage.jsx`, `CalculatorPage.jsx` | `/api/public/content`, `/api/public/loan-products` | `PublicContentController`, `SpaApiController` | `PublicContentService` | `loan_products`, `interest_rates` | `/admin/products` (`AdminProductsPage`) | **PARTIALLY_CONNECTED** |
| **10. ติดต่อและข้อร้องเรียน (Contact & Complaints)** | `/contact`, `/complaints`, `/faqs` | `ContactPage.jsx` | `/api/public/faqs` (Form saves to localStorage) | `SpaApiController@faqs` | `Database` | `faqs`, `contact_messages`, `complaints` | `/admin/control-center` | **PARTIALLY_CONNECTED** |
| **11. สถิติทางการเงิน (Financial Statistics)** | `/statistics`, `/assets` | `StatisticsPage.jsx` | `/api/public/statistics` | `SpaApiController@statistics` | `Database` | `financial_statistics` | `/admin/dashboard` | **CONNECTED** |
| **12. สถิติผู้เข้าชมเว็บไซต์ (Web Analytics)** | `/visitor-stats`, `/analytics` | `VisitorStatsPage.jsx` | `/api/public/visitors/analytics` | `SpaApiController@visitorAnalytics` | `Database` | `website_visits`, `visitor_counters` | `/admin/analytics` | **CONNECTED** |
| **13. ข้อมูลองค์กรและประวัติ (About Cooperative)** | `/about` | `AboutPage.jsx` | `/api/public/content` | `PublicContentController` | `PublicContentService` | `site_settings`, `legacyReference.json` | `/admin/public-content` | **CONNECTED** |
| **14. นโยบายความเป็นส่วนตัวและคุกกี้ (Privacy & Cookies)** | `/privacy/policy`, `/privacy/cookies` | `PrivacyPolicyPage.jsx` | *None* (Static text, `COOP_INFO` from mockData) | *None* | *None* | `cookie_categories`, `privacy_policy_versions` | `/admin/control-center` | **PARTIALLY_CONNECTED** |

---

## 2. Supporting Evidence & Root Causes

### 1. Board Committee (`/app/board_committee` & `/board`) — Status: BROKEN
- **Evidence 1:** `src/pages/AdminBoardPersonnelPage.jsx` บันทึก แก้ไข และลบข้อมูลผ่าน `/api/admin/board-staff` (`Admin\BoardStaffController.php`) ซึ่งเขียนลงตาราง `boards` และ `staff`
- **Evidence 2:** `src/pages/BoardPage.jsx` เรียกใช้ `usePublicInformation()` ซึ่งอ่านข้อมูลผ่าน `src/context/PublicContentContext.jsx` จากเอนด์พอยต์ `/api/public/content`
- **Evidence 3:** `/api/public/content` เรียก `App\Services\PublicContentService::catalog()` ซึ่งพยายามคิวรีตาราง `public_content_revisions` (ซึ่งไม่มีอยู่ในฐานข้อมูลจริง) ทำให้เกิด HTTP 503 และตกลงไปใช้ `src/data/legacyReference.json`
- **Evidence 4:** `BoardPage.jsx` นำ `legacyReference.boardGroups` มาจับคู่กับ `BOARD_MEMBERS` ด้วยคำสั่ง:
  ```javascript
  BOARD_MEMBERS.find(member => member.id === id).name
  ```
  หากโครงสร้าง ID ไม่ตรงกัน จะทำให้เกิด `TypeError: Cannot read properties of undefined (reading 'name')` พังทั้งหน้า
- **Evidence 5:** เอนด์พอยต์ `/api/public/board-members` ใน `SpaApiController.php:225` มีอยู่จริง แต่เรียก `PublicContentService::catalog()` แทนที่จะคิวรีตาราง `boards` และ `staff`

### 2. Documents & Downloads (`/documents`) — Status: HARDCODED
- **Evidence 1:** `src/pages/DocumentsPage.jsx:22` บรรจุค่าอาร์เรย์ `OFFICIAL_DOCUMENTS` แบบคงที่ 23 รายการ
- **Evidence 2:** เอนด์พอยต์ `/api/public/documents` ใน `SpaApiController.php:185` คิวรีตาราง `documents` แต่หน้า `DocumentsPage.jsx` ไม่มีการเรียกใช้ fetch API นี้เลย
- **Evidence 3:** หน้าจัดการเอกสารของผู้ดูแลระบบ `/admin/document-categories` (`AdminDocumentCategoriesPage.jsx`) จัดการตาราง `document_categories` และ `documents` แต่การเปลี่ยนแปลงไม่มีผลต่อหน้าดาวน์โหลดสาธารณะ

### 3. Welfare (`/welfare`) — Status: HARDCODED & BROKEN
- **Evidence 1:** `src/pages/WelfarePage.jsx:4` นำเข้า `import { WELFARE_ITEMS } from '../data/mockData'` และแสดงผลเฉพาะข้อมูลจำลอง
- **Evidence 2:** ใน `SpaApiController.php:203` เอนด์พอยต์ `/api/public/welfare` คิวรี:
  ```php
  Database::query("SELECT id, name, description, max_amount, icon, requirements FROM welfare_types WHERE status = 'active' ORDER BY id ASC");
  ```
  เกิดข้อผิดพลาดเนื่องจากตาราง `welfare_types` ไม่มีอยู่จริงในฐานข้อมูล (ชื่อตารางจริงในระบบคือ `welfare`)

### 4. E-Services (`/eservice`) — Status: HARDCODED
- **Evidence 1:** `src/pages/EServicePage.jsx:8` มีอาร์เรย์ `SERVICES` คงที่ 5 รายการ
- **Evidence 2:** มีการ Hardcode ค่าจำลองในแบบฟอร์ม (บรรทัด 56-58):
  `memberId: user?.memberId || '04892'`, `name: user?.name || 'นายเกียรติศักดิ์ พูลเพิ่ม'`, `phone: '081-234-5678'`
- **Evidence 3:** ฐานข้อมูลมีตาราง `eservice_links` และ Admin มีหน้า `/admin/eservices` (`AdminEServicesPage.jsx`) จัดการลิงก์บริการออนไลน์ แต่หน้าสาธารณะไม่ได้เชื่อมต่อ
