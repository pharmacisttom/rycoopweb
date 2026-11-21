# RC17 — RYCOOPWEB Admin CRUD Audit & Handbook Update

**วันที่:** 21 พฤศจิกายน 2569
**Baseline Source:** `429f09ea5ed0d26c98737f61774f5bb5289e9b73` (production cookie-portal source line)
**ขอบเขต:** Frontend → API → Service/Database → RBAC/CSRF/MFA → Audit → UI feedback → คู่มือ `/app/admin/manual`

> **หมายเหตุสำคัญ:** รายงานนี้จำแนก *หลักฐานการทดสอบอัตโนมัติ* ออกจาก *การตรวจรับกับระบบ TEST/Production จริง* ไม่อ้างว่า CRUD ทุกหน้าใช้งานได้จริงจากการมี Route หรือ HTTP 200 เท่านั้น ห้ามทดสอบคำสั่งแก้ไขข้อมูลสมาชิก/การเงินบน Production

## 1. รายการ 23 กลุ่มจาก `docs/audit/CRUD_COVERAGE_MATRIX.md`

| # | กลุ่มงาน | รูปแบบที่ควรตรวจ | หลักฐาน Source/CI | ข้อจำกัดสำหรับ UAT |
|---|---|---|---|---|
| 1 | ผู้ใช้/เจ้าหน้าที่ | สร้าง/เชิญ, อ่าน, เปลี่ยนสิทธิ์, ระงับ, เพิกถอน Session | `AdminCrudMySqlTest.php`, `StaffPageIntegrationTest.mjs` | ต้องตรวจ Super Admin + MFA กับ TEST |
| 2 | Roles | เพิ่ม/ดู/แก้/ปิดบทบาท | `AdminCrudMySqlTest.php`, `AdminUIBrowserTest.mjs` | ต้องตรวจความขัดแย้ง Version และ Audit |
| 3 | Permissions | อ่าน/กำหนดผ่าน Role (ไม่ใช่เปิดแก้ Catalog โดยตรง) | `PermissionBehaviorTest.php` | ตรวจ Least Privilege |
| 4 | Departments | รายงานอ่านอย่างเดียว | Source matrix: PARTIAL | **ยังไม่มี Department CRUD อิสระ** |
| 5 | Staff Invitations | Invite/Read/Revoke/Resend | `PrOfficerWorkflowTest.php`, `PrOfficerBrowserTest.mjs` | ตรวจลิงก์เชิญหมดอายุใน TEST |
| 6 | Google Account Linking | OAuth Link/Unlink + MFA | `GoogleAuthIntegrationTest.php`, `GoogleMfaIdentityMappingRegressionTest.php` | ต้องตรวจ OAuth/TOTP กับบัญชี TEST จริง |
| 7 | News | เพิ่ม/ดู/แก้/ปิด/เผยแพร่ | `NewsWorkflowTest.php`, `AdminCrudBrowserTest.mjs` | ตรวจหน้าบ้านและรูปภาพหลัง Publish |
| 8 | Activity Gallery | เพิ่ม/แก้ภาพข่าว/อัลบั้ม | `NewsGalleryTest.php`, `AdminUIBrowserTest.mjs` | ตรวจไฟล์/Thumbnail URLs |
| 9 | Slideshow | Read/Preview | `WorkflowFrontendTest.mjs` | ไม่ใช่ Entity CRUD อิสระ |
| 10 | Announcements | เพิ่ม/แก้/เผยแพร่ | `AdminCrudMySqlTest.php`, `PrOfficerWorkflowTest.php` | ตรวจเวลามีผลและการยกเลิก |
| 11 | FAQ | เพิ่ม/อ่าน/แก้/ปิด | `AdminCrudMySqlTest.php`, `AdminCrudBrowserTest.mjs` | ทดสอบ 409/race/rollback |
| 12 | Public Content | Read/Draft/Publish/Revision | `PublicContentWorkflowTest.php`, `AdminCrudMySqlTest.php` | ตรวจ Draft แยก Published |
| 13 | Cooperative Profile | Revision Workflow | `PublicContentWorkflowTest.php` | ตรวจข้อมูลจริงหน้าบ้านใน TEST |
| 14 | Board/Officers | Revision Workflow | `PublicContentReferenceTest.mjs` | ชุด 38/39 ต้องไม่แก้ทับกัน |
| 15 | Documents | เพิ่ม/อ่าน/แก้/ปิดเอกสาร | `AdminCrudMySqlTest.php`, `AdminCrudBrowserTest.mjs` | ตรวจ Storage/ACL/Download |
| 16 | Members | อ่าน/แก้ไข/รับรองคำขอ | `SecurityRegressionTest.php`, `PermissionBehaviorTest.php` | **ยังต้องทำ UAT กับข้อมูลสังเคราะห์ใน TEST** |
| 17 | Dividend Import | Preview/Confirm/Reconcile | `DividendImportTest.php` | **ห้ามยืนยัน Import จริงใน Production ระหว่าง QA** |
| 18 | Financial Content | Draft/Publish อัตราและผลิตภัณฑ์ | `PublicContentWorkflowTest.php` | ตรวจวันที่มีผล/ตัวเลขย้อนหลัง |
| 19 | Settings | อ่าน/แก้เฉพาะ Whitelist | `AdminCrudMySqlTest.php`, `AdminCrudBrowserTest.mjs` | ตรวจ Step-up และ Secret Redaction |
| 20 | Visitor Analytics | อ่าน/Export | `VisitorAnalytics*`/Control Center routes | ไม่ใช่ CRUD |
| 21 | Audit Logs | Read-only | `SecurityRegressionTest.php` | ห้ามให้ผู้ใช้แก้หรือลบ Audit |
| 22 | Backups | Request/Approve/Verify | `DualControlSecurityTest.php` | Restore Execution เป็น **BLOCKED BY POLICY** |
| 23 | Reports | Read/Export ตามสิทธิ์ | Control Center route/contracts | ไม่ใช่ CRUD |

## 2. Automated Verification Gates

- **Backend:** PHP 8.3 + 8.5, `AdminCrudMySqlTest.php` บน Disposable MySQL 8, `SecurityRegressionTest.php`, `PublicContentWorkflowTest.php`, `NewsWorkflowTest.php`, `PermissionBehaviorTest.php`, `DividendImportTest.php`, `GoogleMfaIdentityMappingRegressionTest.php`
- **Frontend:** React Production Build, `AdminCrudBrowserTest.mjs`, `AdminUIBrowserTest.mjs`, `AdminUserManualRC03Test.mjs`, `CookieSweetAlertBrowserTest.mjs`
- **Release:** ตรวจ Source SHA, CSS/JS, Route/CSRF/RBAC/MFA, Nginx + HTTP Smoke ก่อน Cutover โดยห้ามแทนหลักฐาน Browser E2E
- **ผล CI สำหรับ Branch RC17:** ตรวจใน GitHub Actions ของ Commit ล่าสุดเท่านั้น; ห้ามคัดลอก PASS จาก Baseline มาเป็นผล Commit ใหม่

## 3. ข้อจำกัดและเกณฑ์ก่อนปิดงาน

1. การตรวจ Source/CI เป็นหลักฐานเฉพาะกรณีที่ Test ครอบคลุม ไม่ใช่การทดสอบทุกปุ่มทุก Record กับ Database จริง
2. ต้องมี TEST UAT ที่บันทึกผล Read/Create/Update/Disable/Conflict/Permission/Audit ตามความสามารถของแต่ละโมดูล
3. สมาชิก, เงินปันผล, การเงิน, PDPA, OAuth, Step-Up MFA และ Backup Restore ต้องใช้ข้อมูลสังเคราะห์/บัญชีทดสอบและแยก Database; การตรวจ Production ใช้ Read-only + Browser Acceptance เท่านั้น
4. ฟีเจอร์ที่ถูกปิดโดย Policy จะไม่เปิดเพื่อให้ Test PASS
5. เอกสารคู่มือที่ `/app/admin/manual` มีบท CRUD, Cookie Consent, SweetAlert2, MFA, Troubleshooting 401/403/409/419 และ Print/PDF

**สถานะปัจจุบัน:** Source/QA documentation updated; CI และ TEST UAT ต้องอ้างผลที่เกิดขึ้นจริงก่อนระบุว่าเสร็จสมบูรณ์
