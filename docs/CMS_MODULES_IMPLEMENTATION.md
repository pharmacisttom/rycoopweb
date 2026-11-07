# RYCOOPWEB — CMS Modules Implementation Architecture

**Document Version:** 1.0.0
**Target Environment:** TEST (`https://test.rayongcoop.org/`)
**Application Base:** `pharmacisttom/rycoopweb`

---

## 1. Banner Management CMS Module

### 1.1 Backend Controller & Routes
- Registered routes in `config/routes.php`:
  ```php
  // Banner & Hero Slider Admin API
  $router->get('/api/admin/banners', 'Api\SpaApiController@adminBanners', [AuthMiddleware::class, RoleMiddleware::class]);
  $router->post('/api/admin/banners', 'Api\SpaApiController@createAdminBanner', [AuthMiddleware::class, RoleMiddleware::class, CsrfMiddleware::class]);
  $router->post('/api/admin/banners/{id}/update', 'Api\SpaApiController@updateAdminBanner', [AuthMiddleware::class, RoleMiddleware::class, CsrfMiddleware::class]);
  $router->post('/api/admin/banners/{id}/delete', 'Api\SpaApiController@deleteAdminBanner', [AuthMiddleware::class, RoleMiddleware::class, CsrfMiddleware::class]);
  $router->post('/api/admin/banners/reorder', 'Api\SpaApiController@reorderAdminBanners', [AuthMiddleware::class, RoleMiddleware::class, CsrfMiddleware::class]);
  ```
- Role authorization permits `super_admin`, `pr_officer`, `manager`, and `it_admin`.
- Supported media: Desktop image (1920x650 recommended), mobile image (750x900 recommended), JPEG, PNG, WebP with a 10MB limit.

### 1.2 Frontend Admin UI (`/admin/banners`)
- Integrated within `AdminControlCenterPage.jsx` navigation drawer.
- Provides visual banner card preview, live status toggle (active/draft/inactive), drag-or-number reordering, start/end scheduling date pickers, and desktop/mobile asset file uploader.

---

## 2. Document Download Center Module

### 2.1 11 Standardized Document Categories
1. **เงินกู้ของสหกรณ์ (`loans`):** แบบคำขอกู้สามัญ, กู้ฉุกเฉิน, กู้พิเศษ, สัญญากู้, สัญญาค้ำประกัน, ตารางงวดชำระ
2. **เงินฝาก (`deposits`):** ใบเปิดบัญชี, ใบคำขอถอนเงิน, หนังสือมอบอำนาจ, เงื่อนไขดอกเบี้ยเงินฝาก
3. **สมาชิกและหุ้น (`membership`):** ใบสมัครสมาชิกสามัญ/สมทบ, แบบแจ้งขอเปลี่ยนแปลงค่าหุ้นรายเดือน, หนังสือตั้งผู้รับโอนประโยชน์
4. **สวัสดิการสมาชิก (`welfare`):** คำขอสวัสดิการสมรส, สงเคราะห์โสด, สงเคราะห์บำนาญ, สงเคราะห์กรณีเสียชีวิต
5. **สมาคมฌาปนกิจสงเคราะห์ (`associations`):** แบบคำขอ สสธท., กสธท., สส.ชสอ., ใบแจ้งเปลี่ยนผู้รับเงินสงเคราะห์
6. **การคุ้มครองข้อมูลส่วนบุคคล (`pdpa`):** นโยบายคุ้มครองข้อมูลส่วนบุคคล (Privacy Policy), แบบฟอร์มขอใช้สิทธิ (DSAR)
7. **ระเบียบและข้อบังคับ (`regulations`):** ข้อบังคับสหกรณ์ฉบับแก้ไขเพิ่มเติม, ระเบียบเงินกู้, ระเบียบเงินฝาก
8. **รายงานประจำปี (`annual_reports`):** รายงานกิจการประจำปี 2560 - 2568, งบการเงินตรวจสอบแล้ว
9. **แผนกลยุทธ์ (`strategic`):** แผนยุทธศาสตร์ 2566-2570, 2568-2570, 2569-2573
10. **แบบฟอร์มคำขอทั่วไปและเรื่องร้องเรียน (`forms_complaints`):** คำขอทั่วไป, หนังสือร้องเรียน, คำขอหนังสือรับรอง
11. **ประกาศและเอกสารอื่นๆ (`announcements_misc`):** ประกาศประชุมใหญ่, ประกาศผลการเลือกตั้ง, เอกสารประชาสัมพันธ์

### 2.2 Functional Capabilities
- Real-time instant search filtering across Thai titles, document codes, and tags.
- Download tracker incrementing file download statistics.
- File badge indicators (`PDF`, `DOCX`, `XLSX`) with human-readable file sizes.

---

## 3. Loan Application Calendar Module (`/loan-schedule`)

### 3.1 Overview
Cooperative members require clear visibility into monthly loan submission deadlines, committee review cycles, and disbursement transfer dates.

### 3.2 Features
- Interactive 12-month calendar matrix for Year 2569 (2026).
- High-resolution download of the official schedule poster (`img/pay2569.png`).
- Monthly breakdown:
  - **วันสุดท้ายของการยื่นคำขอกู้สามัญ / กู้พิเศษ**
  - **วันประชุมคณะกรรมการเงินกู้ / คณะกรรมการดำเนินการ**
  - **วันโอนเงินกู้เข้าบัญชีสมาชิก**

---

## 4. Member Welfare & Mutual Aid Associations Module

### 4.1 Internal Welfare Benefits
Covers all internal member benefits provided directly by the cooperative:
- สวัสดิการมงคลสมรส (Marriage Welfare)
- สวัสดิการสงเคราะห์สมาชิกโสด (Single Member Welfare)
- สวัสดิการสงเคราะห์สมาชิกเมื่อเกษียณอายุ / สูงอายุ (Pension / Senior Welfare)
- สวัสดิการสงเคราะห์ศพสมาชิกและครอบครัว (Death Benefit)

### 4.2 Partner Mutual Aid Associations
Direct links, guidance, and downloadable application kits for:
- **สสธท.** (สมาคมฌาปนกิจสงเคราะห์สมาชิกสหกรณ์ออมทรัพย์สาธารณสุขไทย)
- **กสธท.** (กองทุนสวัสดิการสมาชิกสหกรณ์ออมทรัพย์สาธารณสุขไทย)
- **สส.ชสอ.** (สมาคมฌาปนกิจสงเคราะห์สมาชิกชุมนุมสหกรณ์ออมทรัพย์แห่งประเทศไทย)
