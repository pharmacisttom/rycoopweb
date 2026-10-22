# RYCOOPWEB 2.1 — แผนการปรับปรุงและเกณฑ์การตรวจรับ (CHANGE & RECOVERY PLAN)
**ระบบบริหารจัดการข้อมูลและบริการดิจิทัล สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด**  
**แผนกลยุทธ์การปรับปรุงระบบและการทดสอบย้อนกลับ (Phased Modernization & Acceptance Gates)**

---

## 1. ลำดับขั้นการดำเนินงานแบบมีเกณฑ์ควบคุม (Phased Implementation Strategy)

เพื่อความปลอดภัยสูงสุดและไม่กระทบต่อการทำงานเดิมของระบบ การดำเนินงานจะแบ่งออกเป็น 10 ลำดับขั้น โดย **แต่ละลำดับขั้นต้องผ่านการทดสอบ (Pass Acceptance Criteria) ก่อนที่จะเริ่มลำดับถัดไปโดยเด็ดขาด**:

```
[ Phase 0: Baseline Audit ] ──► (ผ่านเกณฑ์เอกสารและการสำรวจ)
            │
            ▼
[ Phase 0.5: Git Checkpoint & Build Recovery ] ──► (npm run build PASS & แก้ Mojibake)
            │
            ▼
[ Phase 1: Authentication & Session Refactoring ] ──► (Login/Session/Regression PASS)
            │
            ▼
[ Phase 2: Complete Secure 2FA System ] ──► (Enrollment, Local QR, Replay, Recovery PASS)
            │
            ▼
[ Phase 3: RBAC & API Security Matrix ] ──► (SecurityRegressionTest PASS, Deny-by-default)
            │
            ▼
[ Phase 4: Financial Data Integrity ] ──► (DividendImportTest PASS, Exact Cents Arithmetic)
            │
            ▼
[ Phase 5: React & UI/UX Stabilization ] ──► (2FA UI, Responsive, Error Codes 401/403/419)
            │
            ▼
[ Phase 6: Database & Migration Safety ] ──► (Additive Migrations, No Data Loss)
            │
            ▼
[ Phase 7: Backup, Audit & Observability ] ──► (Pre-flight Restore Drill, Logger Fixed)
            │
            ▼
[ Phase 8: Automated QA & Full Regression Suite ] ──► (100% Core Test Suites PASS)
            │
            ▼
[ Phase 9: TEST Deployment Preparation ] ──► (Artifact Packaging, Nginx/FPM Verified)
            │
            ▼
[ Phase 10: Production Readiness Review ] ──► (UAT Sign-off, Backup Ready, Rollback Ready)
```

---

## 2. รายละเอียดแต่ละขั้นตอน (Detailed Phase Specifications)

### ลำดับที่ 0.5: การจัดทำ Git Checkpoint และกู้คืน Baseline Build
- **วัตถุประสงค์:** สำรองงานค้างที่ยังไม่ Commit, กู้คืน Encoding ภาษาไทย, และสร้างหน้าจอ Component ที่ขาดหายไปเพื่อให้ `npm run build` ผ่าน
- **ไฟล์ที่ได้รับผลกระทบ:**
  - `src/pages/AdminAccessPage.jsx` (สร้างใหม่เพื่อรองรับ Route `/admin/access`)
  - `src/pages/AdminWorkspacePage.jsx` (สร้างใหม่เพื่อรองรับ Route `/admin/workspace`)
  - `app/Controllers/Api/SpaApiController.php` (กู้คืนข้อความภาษาไทย UTF-8)
  - `app/Controllers/Admin/MemberManagementController.php` (กู้คืนข้อความภาษาไทย UTF-8)
  - `public/templates/member-dividends-template.xlsx` (ฟื้นฟูแถวตัวอย่างเพื่อแก้ `DividendImportTest.php`)
- **เกณฑ์การตรวจรับ (Acceptance Criteria):**
  1. `npm run build` รันผ่านสมบูรณ์ 100% ไม่มี Rollup/Vite Error
  2. ข้อความแจ้งเตือนภาษาไทยแสดงผลถูกต้อง ไม่เป็น Mojibake
  3. `php tests/DividendImportTest.php` รันผ่านสมบูรณ์

---

### ลำดับที่ 1: การปรับปรุงระบบ Authentication และ Session (Phase 1)
- **วัตถุประสงค์:** แก้ไขปัญหาการตัดช่องว่างรหัสผ่าน, บังคับใช้ Session Expiration และ Cookie Security, และปรับปรุง Response สำหรับ API
- **ไฟล์ที่ได้รับผลกระทบ:**
  - `app/Core/Request.php` (เพิ่ม `rawInput()` สำหรับรหัสผ่าน)
  - `app/Core/Session.php` (เพิ่ม Absolute Session Lifetime, ตรวจสอบ HTTPS อัตโนมัติสำหรับ Secure Cookie)
  - `app/Controllers/Admin/AuthController.php` (ใช้รหัสผ่านดิบในการยืนยัน, ส่ง Error Response ที่เป็นมาตรฐาน)
  - `config/security.php` (รองรับการตั้งชื่อ Session แยกตาม Environment)
  - `tests/AuthRedirectRegressionTest.php` (ปรับให้ครอบคลุมการ Redirect ทั้งสองสถาปัตยกรรม)
- **เกณฑ์การตรวจรับ (Acceptance Criteria):**
  1. รหัสผ่านที่มีช่องว่างหัว/ท้าย สามารถล็อกอินผ่านได้อย่างถูกต้อง
  2. เซสชันถูกทำลายทันทีเมื่อหมดเวลา Idle Timeout หรือ Absolute Lifetime
  3. คุกกี้เซสชันมีแฟล็ก `HttpOnly`, `SameSite=Lax`, และ `Secure` (บน HTTPS)
  4. การทดสอบ `php tests/AuthRedirectRegressionTest.php` ผ่านสมบูรณ์

---

### ลำดับที่ 2: ระบบยืนยันตัวตนสองขั้นตอนที่สมบูรณ์และมั่นคงปลอดภัย (Phase 2)
- **วัตถุประสงค์:** พัฒนา Enrollment Workflow, เข้ารหัส Secret ในฐานข้อมูล, สร้าง QR Code ในเครื่อง, ป้องกัน Replay Attack, และจัดเตรียม Recovery Codes
- **ไฟล์ที่ได้รับผลกระทบ:**
  - `app/Services/TwoFactorService.php` (เข้ารหัส Secret, ตัดการเรียก External API, เพิ่ม Replay Store)
  - `app/Controllers/Admin/AuthController.php` (เพิ่ม API Endpoint: Setup, Confirm, Recovery)
  - `app/Middlewares/AuthMiddleware.php` (ส่ง JSON `TWO_FACTOR_REQUIRED` 403 เมื่อคำขอเป็น AJAX/API)
  - `database/migrations/019_two_factor_security_enhancements.sql` (สร้างคอลัมน์เก็บ Replay Timestamp และ Recovery Codes Hash)
  - `tests/TwoFactorSecurityTest.php` (ชุดทดสอบความปลอดภัย 2FA ใหม่)
- **เกณฑ์การตรวจรับ (Acceptance Criteria):**
  1. การสร้าง QR Code ทำงานสมบูรณ์โดยไม่ส่ง Request ออกสู่อินเทอร์เน็ต
  2. Secret ถูกเข้ารหัสก่อนบันทึกลง MySQL
  3. รหัส OTP เดิมไม่สามารถใช้งานซ้ำได้ภายใน Time-window (Replay Protection)
  4. มี Rate Limiting สำหรับการป้อนรหัสผิด
  5. Recovery Codes ใช้งานได้จริงและใช้ได้เพียงครั้งเดียวต่อหนึ่งรหัส

---

### ลำดับที่ 3: ระบบควบคุมสิทธิ์การเข้าถึงและความปลอดภัยของ API (Phase 3)
- **วัตถุประสงค์:** สร้าง RBAC Matrix ที่ชัดเจน, ปรับปรุง Route ให้มี Defense-in-depth, และแก้ปัญหา `SecurityRegressionTest.php` ล้มเหลว
- **ไฟล์ที่ได้รับผลกระทบ:**
  - `config/routes.php` (ผสมผสาน `RoleMiddleware` และ `PermissionMiddleware`)
  - `app/Middlewares/PermissionMiddleware.php` (ปรับปรุง Error Handling)
  - `tests/SecurityRegressionTest.php` (ปรับปรุง Assertion ให้รองรับทั้ง Role และ Permission)
  - `docs/security/RBAC_MATRIX.md` (จัดทำตารางสิทธิ์การใช้งานครบทุก Endpoint)
- **เกณฑ์การตรวจรับ (Acceptance Criteria):**
  1. `php tests/SecurityRegressionTest.php` รันผ่าน 100% (ไม่มี Fail)
  2. การเข้าถึง API ทุกตัวใช้หลักการ Deny-by-default
  3. สมาชิกทั่วไปไม่สามารถเข้าถึงข้อมูลของสมาชิกรายอื่นได้ (No IDOR)

---

### ลำดับที่ 4: ความสมบูรณ์ของข้อมูลการเงินและเงินปันผล (Phase 4)
- **วัตถุประสงค์:** รับรองความถูกต้องของสูตรการคำนวณปันผล, ปรับปรุง Master Data ดอกเบี้ย, และป้องกัน Duplicate Imports
- **ไฟล์ที่ได้รับผลกระทบ:**
  - `app/Services/DividendImportService.php` (ยืนยันสูตรและการปัดเศษทศนิยม)
  - `database/migrations/020_repair_interest_rates_duplicates.sql` (ลบรายการดอกเบี้ยซ้ำซ้อนในตาราง)
  - `tests/LoanMasterDataTest.php` (รันผ่าน 100%)
- **เกณฑ์การตรวจรับ (Acceptance Criteria):**
  1. `php tests/LoanMasterDataTest.php` รันผ่าน 100%
  2. `php tests/DividendImportTest.php` รันผ่าน 100%
  3. การนำเข้าไฟล์เงินปันผลไม่เกิดการทับซ้อนหรือคำนวณยอดผิดพลาด

---

### ลำดับที่ 5: การปรับปรุง UI/UX และ React Stabilization (Phase 5)
- **วัตถุประสงค์:** พัฒนาหน้าจอตั้งค่า 2FA ใน Admin Dashboard, ปรับปรุงการจัดการ Error Codes (401, 403, 419), และปรับแต่ง Vite Build ให้รองรับ Root Subdomain
- **ไฟล์ที่ได้รับผลกระทบ:**
  - `src/App.jsx`
  - `src/pages/LoginPage.jsx` (รองรับหน้าจอใส่ OTP สวยงาม)
  - `src/pages/AdminAccessPage.jsx` (หน้าจัดการสิทธิ์และผู้ใช้งาน)
  - `src/pages/AdminWorkspacePage.jsx` (หน้าพื้นที่ทำงานของเจ้าหน้าที่)
  - `vite.config.js` (เพิ่ม Profile Build สำหรับ TEST Subdomain)
- **เกณฑ์การตรวจรับ (Acceptance Criteria):**
  1. หน้าจอ OTP และ 2FA Enrollment ใช้งานง่าย สวยงาม เข้ากับดีไซน์เดิมของสหกรณ์
  2. Frontend จัดการ Response 401/403/419 ได้อย่างนุ่มนวล ไม่มี Redirect Loop

---

### ลำดับที่ 6 ถึง 10: ฐานข้อมูล, ระบบสำรองข้อมูล, QA และความพร้อมสำหรับ Deployment
- **Phase 6:** ตรวจสอบ Migration ทั้งหมด, ห้ามรันคำสั่งทำลายข้อมูลบน LIVE DB
- **Phase 7:** แก้ไข `BackupController.php` (เปลี่ยน Namespace Logger, เพิ่ม Pre-flight Validation)
- **Phase 8:** รวมชุดทดสอบทั้งหมดเป็น One-click Automation Test Runner
- **Phase 9:** จัดทำคู่มือและ Artifact สำหรับ TEST Deployment (`https://test.rayongcoop.org`)
- **Phase 10:** สรุป Production Readiness Checklist เพื่อส่งมอบให้คณะกรรมการสหกรณ์พิจารณา

---

## 3. แผนการย้อนกลับเมื่อเกิดปัญหา (Rollback Strategy)

1. **Local Git Rollback:** ทุกขั้นตอนจะมีการทำ Commit แยกเป็นส่วนๆ หากพบปัญหา สามารถใช้คำสั่ง `git checkout <checkpoint-tag>` หรือ `git revert <commit-hash>` ได้ทันที โดยไม่ต้องใช้ `git reset --hard`
2. **Database Schema Rollback:** Migration ทุกไฟล์จะมีสคริปต์ย้อนกลับ (Down Migration) กำกับไว้ในเอกสาร `docs/database/MIGRATION_PLAN.md`
3. **Deployment Artifact Rollback:** เซิร์ฟเวอร์ TEST จะคงโฟลเดอร์ Release เดิมไว้เสมอเพื่อพร้อมสลับ Symlink กลับหากเกิดปัญหาบนเซิร์ฟเวอร์
