# RYCOOPWEB — COMPLETE SYSTEM AUDIT REPORT (PHASE 1)

**Project:** RYCOOPWEB — สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด
**Branch:** `fix/frontend-backend-data-consistency`
**Base Commit:** `c39d8e4897473db3a58f184782067b399587c74f`
**Auditor / Roles:** Principal Full-Stack Architect, Senior PHP 8.3 Engineer, Senior Frontend Engineer, MySQL 8 DBA, DevSecOps Lead
**Audit Date:** 6 พฤศจิกายน 2569
**Execution Order:** STEP 1 (Read-Only Discovery) & STEP 2 (Protected File Inventory)

---

## 1. Executive Summary

จากการตรวจสอบโครงสร้างระบบเชิงลึก (Deep Read-Only System Audit) ทั่วทั้งระบบ Frontend (React 18 + Vite), Backend (PHP 8.3 REST APIs & Router), Database Schema (MySQL 8) และ Data Flow ในการนำเสนอข้อมูลสู่สาธารณะ พบปัญหาความไม่สอดคล้องเชิงสถาปัตยกรรม (Architectural Disconnect) ที่สำคัญ 4 ประการ:

1. **การแยกสายข้อมูลระหว่าง Admin Management และ Public Website (Backend Disconnect):**
   - หน้าจัดการข้อมูลหลังบ้าน (Admin CRUD) เช่น `/admin/board-staff`, `/admin/welfare`, `/admin/products`, `/admin/eservices` บันทึกและแก้ไขข้อมูลลงในตารางฐานข้อมูลโดยตรง (`boards`, `staff`, `welfare`, `loan_products`, `eservice_links`)
   - แต่หน้าเว็บสาธารณะ (Public Frontend) กลับดึงข้อมูลผ่าน `PublicContentService` ซึ่งอ่านจากตาราง `public_content_revisions` และ fallback ไปยังไฟล์คงที่ `src/data/legacyReference.json`
   - ผลลัพธ์: เมื่อผู้ดูแลระบบเพิ่ม/แก้ไขข้อมูลกรรมการ หรือสวัสดิการในระบบ Admin ข้อมูลในหน้าสาธารณะจะไม่เปลี่ยนแปลงตาม
2. **ปัญหาตารางฐานข้อมูล `public_content_revisions` ไม่พบในระบบ (Missing Table & 503 HTTP Error):**
   - ตาราง `public_content_revisions` ถูกสร้างใน Migration 023 แต่ในฐานข้อมูลจริง Migration 023 ยังไม่ได้ถูกรัน ส่งผลให้เมธอด `PublicContentService::catalog()` ใน Backend โยนข้อยกเว้น PDOException (Table doesn't exist)
   - ปลายทาง API `/api/public/content` และ `/api/public/board-members` จึงตอบกลับเป็น HTTP 503 `CONTENT_UNAVAILABLE` ทำให้ Frontend ใน `PublicContentContext` ตกกลับไปใช้ `legacyReference.json` เสมอ
3. **การ Hardcode ข้อมูลใน Component หน้าสาธารณะ (Hardcoded Public Frontend):**
   - หน้าเอกสารดาวน์โหลด (`DocumentsPage.jsx`) มีชุดข้อมูล `OFFICIAL_DOCUMENTS` แบบ Hardcoded ในโค้ด โดยไม่ได้เชื่อมต่อ API `/api/public/documents`
   - หน้าสวัสดิการ (`WelfarePage.jsx`) ใช้ `WELFARE_ITEMS` นำเข้าจาก `mockData.js` ขณะที่ API `/api/public/welfare` ใน Backend มีข้อผิดพลาดใน SQL Query ที่อ้างอิงตาราง `welfare_types` (ซึ่งไม่มีอยู่จริง ตารางจริงคือ `welfare`)
   - หน้าบริการออนไลน์ (`EServicePage.jsx`) มีการ Hardcode รายการบริการ `SERVICES` และยังมีข้อมูลจำลอง (Mock Data) สมาชิกตัวอย่าง เช่น `"04892"`, `"นายเกียรติศักดิ์ พูลเพิ่ม"`, `"081-234-5678"` หลงเหลืออยู่ในฟอร์ม
4. **ความปลอดภัยของระบบหลัก (Protected Core Integrity):**
   - ระบบสำคัญด้านความมั่นคงปลอดภัยทั้งหมด ได้แก่ Member Login, Admin Login, Staff Login, TOTP MFA 2FA, PDPA Session Gate, Dual-Control Maker-Checker, RBAC และข้อมูลทางการเงินของสมาชิก **คงสภาพเดิม 100% ไม่ถูกแตะต้องและถูกกำหนดเป็น Protected Read-Only Files**

---

## 2. Protected Core Inventory (รายการไฟล์และระบบที่ห้ามแก้ไข)

ตามข้อกำหนดความปลอดภัยขั้นสูงสุด ไฟล์และโมดูลต่อไปนี้ถูกขึ้นทะเบียนเป็น **PROTECTED CORE** ห้ามแก้ไข ดัดแปลง หรือ Refactor โดยเด็ดขาด:

| หมวดหมู่ | รายการไฟล์ / ออบเจกต์ | วัตถุประสงค์และความเสี่ยง |
| :--- | :--- | :--- |
| **Authentication Controllers** | `app/Controllers/Admin/AuthController.php`<br>`app/Controllers/Member/MemberPortalController.php`<br>`app/Controllers/Member/MemberRegistrationController.php` | ควบคุมการยืนยันตัวตนสมาชิก เจ้าหน้าที่ และผู้ดูแลระบบ |
| **Financial Services** | `app/Services/MemberPortalService.php`<br>`app/Controllers/Member/DividendEstimatorController.php`<br>`app/Services/TaxCertificateService.php` | ควบคุมการประมวลผลหุ้น เงินฝาก เงินกู้ ปันผลเฉลี่ยคืน และหนังสือรับรอง |
| **Security & Dual Control** | `app/Services/DualControlSecurityService.php`<br>`app/Services/AccessService.php`<br>`app/Services/AuditService.php`<br>`app/Services/SessionTracker.php` | ระบบ Maker-Checker, Step-Up MFA, Two-Phase Restore, และ Session Invalidation |
| **Middlewares** | `app/Middlewares/AuthMiddleware.php`<br>`app/Middlewares/PermissionMiddleware.php`<br>`app/Middlewares/CsrfMiddleware.php`<br>`app/Middlewares/PdpaSessionGateMiddleware.php`<br>`app/Middlewares/RateLimitMiddleware.php` | ตัวคัดกรองสิทธิ์และประตูความปลอดภัยเซสชัน |
| **Core Security Engines** | `app/Core/Auth.php`<br>`app/Core/Session.php`<br>`app/Core/Csrf.php` | กลไกความปลอดภัยแกนกลางของเฟรมเวิร์ก |
| **Member Protected Frontend** | `src/pages/LoginPage.jsx`<br>`src/pages/MfaPage.jsx`<br>`src/pages/MemberDashboardPage.jsx`<br>`src/pages/MemberDividendsPage.jsx`<br>`src/pages/MemberPrivacyAckPage.jsx`<br>`src/pages/AdminControlCenterPage.jsx`<br>`src/context/AuthContext.jsx`<br>`src/services/memberAdminApi.js`<br>`src/services/memberAuthApi.js` | หน้าต่างพอร์ทัลสมาชิกและการจัดการสิทธิ์เจ้าหน้าที่ |
| **Protected Database Tables** | `users`, `members`, `member_dividends`, `member_data_changes`, `member_profile_requests`, `user_roles`, `roles`, `permissions`, `role_permissions`, `login_logs`, `security_audit_logs`, `audit_logs`, `user_google_identities`, `user_mfa_recovery_codes`, `rate_limit_attempts`, `led_*` | ฐานข้อมูลสมาชิก บัญชีผู้ใช้ สิทธิ์ และประวัติการเงิน (ห้ามแก้ไขโครงสร้าง) |

---

## 3. สภาพแวดล้อมระบบและการตั้งค่า (Environment Baseline)

- **Local Development / TEST Host:** Windows (OS Build), PHP 8.3, MySQL 8 (Daemon 127.0.0.1:3306), Node.js v20+
- **Current Database Context:** `rayongcoop_db` (Local development replica with 61 tables, 2,508 members, 4,855 dividends)
- **Target TEST Database:** `rycoopweb_test`
- **Target Production Database:** `rayongcoop_db` (บนเซิร์ฟเวอร์จริง — **STRICTLY READ-ONLY**)
- **Git Branch:** `fix/frontend-backend-data-consistency` (แตกแขนงจาก `hotfix/production-mock-data-removal`)

---

## 4. แผนงานการแก้ไขตามลำดับขั้นตอน (Action Plan)

1. **Step 2 — เอกสาร Route Inventory & Mapping:** จัดทำ `ROUTE_INVENTORY.md`, `FRONTEND_BACKEND_MAPPING.md`, `DATABASE_RELATIONSHIP_AUDIT.md`, `API_CONTRACT.md`, `PUBLIC_MODULE_BUG_REGISTER.md`
2. **Step 5 — โมดูลนำร่อง: คณะกรรมการดำเนินการ (`/app/board_committee` & `/board`):**
   - เชื่อมต่อให้ `BoardPage.jsx` และ `BoardOfficerPage.jsx` ดึงข้อมูลจาก API บุคลากรจริงที่สะท้อนข้อมูลจากตาราง `boards` และ `staff`
   - ปรับปรุงการแสดงผลลำดับโครงสร้างกรรมการ ประธาน คณะกรรมการย่อย และวาระดำเนินงาน
   - จัดการ Fallback รูปภาพและกรณีไม่พบรูปโปรไฟล์
3. **Step 6 — โมดูลสาธารณะอื่นๆ (News, Documents, Welfare, E-Services, Rates):**
   - แก้ไข SQL Query ของ `welfare` ใน `SpaApiController.php` ให้เรียกตาราง `welfare` จริง
   - เชื่อมต่อ `DocumentsPage.jsx` ให้ดึงข้อมูลเอกสารจากฐานข้อมูล
   - ลบ Hardcoded Mock Data ใน `EServicePage.jsx` และเชื่อมโยงรายการบริการ
4. **Step 8 — UI/UX & SEO:** ตรวจสอบ Responsive Layout, Thai Font, Accessibility, Metadata และ Robots directives
5. **Step 9 — Automated Testing:** ทดสอบความสอดคล้องของข้อมูล และรัน Protected Core Regression Test ทุกชุด
