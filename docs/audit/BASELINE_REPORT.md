# RYCOOPWEB 2.1 — รายงานการตรวจสอบสถานะระบบและแผนกู้คืน (BASELINE REPORT)
**ระบบบริหารจัดการข้อมูลและบริการดิจิทัล สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด**  
**วันที่ตรวจสอบ:** 21 ตุลาคม 2569  
**ผู้ตรวจสอบ:** Principal Full-Stack & Application Security Engineer  
**สภาพแวดล้อมที่ตรวจสอบ:** Local Workspace (`D:\rycoopweb`), Git Branch: `codex/member-portal-integration`  
**สถานะการทำงาน:** PHASE 0 — Baseline Discovery and Recovery Plan (ไม่แก้ไข Source Code ของระบบ)

---

## 1. บทสรุปผู้บริหาร (Executive Summary)

จากการตรวจสอบ Source Code, Architecture, Dependencies, Database Migrations, Route Declarations, และชุดทดสอบอัตโนมัติทั้งหมดของระบบ **RYCOOPWEB** พบว่าระบบมีรากฐานสถาปัตยกรรมที่ดีและมีโมดูลหลักที่ทำงานได้จริงหลายส่วน (Authentication, Member Management, LED Check, Rich Text News/FAQ, Financial Calculations) 

อย่างไรก็ตาม **ระบบใน Working Tree ปัจจุบันยัง "ไม่พร้อมสำหรับการ Deployment ขึ้น TEST หรือ Production" และยังไม่ควรเริ่มการ Refactor ความปลอดภัยในส่วนอื่น จนกว่าจะจัดการปัญหา Baseline ที่ตกค้างให้ผ่านการทดสอบเสียก่อน**

### สรุปสถานะสำคัญที่ตรวจพบ:
1. **Frontend Build ล้มเหลว (`npm run build` FAIL):** มีการประกาศ Import หน้า `AdminAccessPage` และ `AdminWorkspacePage` ใน `src/App.jsx` แต่องค์ประกอบไฟล์ทั้งสองยังไม่มีอยู่ในระบบ ทำให้ Rollup ไม่สามารถ Resolve Module ได้
2. **การเข้ารหัสภาษาไทยผิดเพี้ยน (Mojibake/Encoding Corruption):** การแก้ไขที่ยังไม่ได้ Commit ในไฟล์ `app/Controllers/Api/SpaApiController.php` และ `app/Controllers/Admin/MemberManagementController.php` ถูกบันทึกด้วย Encoding ที่ผิดพลาด ทำให้ข้อความแจ้งเตือนภาษาไทยกลายเป็นอักขระเสีย
3. **ระบบ 2FA ขาดความสมบูรณ์และมีความเสี่ยงสูง (High Risk):**
   - มีฟังก์ชันตรวจสอบ OTP พื้นฐาน แต่ **ขาด Workflow การลงทะเบียน (Enrollment)** และ **ขาดระบบ Recovery Codes**
   - Template หน้าจอ `views/auth/two_factor.php` **ไม่มีอยู่จริง** ส่งผลให้การเปิดหน้า 2FA ผ่าน PHP Controller จะล้มเหลว (View not found)
   - ฟังก์ชันสร้าง QR Code (`TwoFactorService::getQrCodeUrl`) ส่ง Secret ในรูปแบบ Plaintext ไปยังภายนอกผ่านบริการ `api.qrserver.com`
   - ขาดระบบป้องกันการวนซ้ำรหัส (OTP Replay Attack) และไม่มี Dedicated Rate Limiter สำหรับ 2FA
4. **ความไม่เข้ากันของ Middleware สิทธิ์การใช้งาน (RBAC Regression):** การเปลี่ยนแปลงที่ยังไม่ Commit ได้สลับการใช้ `RoleMiddleware` ไปเป็น `PermissionMiddleware` ในบาง Route ส่งผลให้การทดสอบความปลอดภัยเดิม (`tests/SecurityRegressionTest.php`) ล้มเหลว 10 รายการ
5. **ความเสี่ยงในระบบสำรองข้อมูล (Backup & Restore Risk):** `BackupController.php` มีการ Import Class `App\Services\Logger` ที่ไม่มีอยู่จริง (ตำแหน่งจริงคือ `App\Core\Logger`) ซึ่งจะเกิด Fatal Error เมื่อเรียกใช้งาน และกระบวนการ Restore ดำเนินการ Execute `DROP TABLE` และ SQL Dump โดยตรงโดยไม่มีระบบ Pre-validation, Transaction หรือ Checksum Verification
6. **ปัญหาการทดสอบนำเข้าข้อมูลปันผล (`tests/DividendImportTest.php` FAIL):** แม่แบบ `member-dividends-template.xlsx` ถูกปรับเป็นแม่แบบว่างเปล่า ทำให้ระบบตรวจไม่พบข้อมูลตัวอย่างและ Throw Exception
7. **สถาปัตยกรรม Front-end Build และ Path รองรับ Subdomain:** ค่า Config ของ Vite ปัจจุบันตั้งค่า Production Base เป็น `/app/` และ Output ไปยัง `public/app` ซึ่งไม่ตรงกับข้อกำหนดเป้าหมายของ TEST Subdomain (`https://test.rayongcoop.org/` Base `/`)

---

## 2. สถานะ Git และงานที่ยังไม่ได้ Commit (Uncommitted Work Inventory)

### 2.1 ข้อมูล Git Branch
- **Current Branch:** `codex/member-portal-integration`
- **Current HEAD Commit:** `6bd0451` (*"Add member portal request APIs and scoped authorization bridge"*)
- **Target / Working Base Branch:** `Test` (Commit `1ab6bf9` *"Add searchable emoji and symbol picker to text editor"*)
- **ความต่างจาก Branch Test:** มี 1 Commit เพิ่มเติม (`6bd0451`) และการแก้ไขค้างใน Working Copy 8 ไฟล์

### 2.2 รายการไฟล์ที่มีการแก้ไขแต่ยังไม่ Stage/Commit (Modified Files)
| ไฟล์ | รายละเอียดการเปลี่ยนแปลง | ผลกระทบและความเสี่ยง |
|---|---|---|
| `app/Controllers/Admin/AuthController.php` | ปรับ Default Redirect ไปยัง `/admin/workspace`, เชื่อมโยงสิทธิ์ผ่าน `AccessService::permissions()` | กระทบ `tests/AuthRedirectRegressionTest.php` (FAIL) เนื่องจากคาดหวัง Redirect ไปยัง `/staff/dashboard` |
| `app/Controllers/Admin/MemberManagementController.php` | เชื่อมต่อสิทธิ์ผ่าน `PermissionMiddleware` และเกิดข้อความภาษาไทยเสีย (Mojibake) | ข้อความ Error แสดงผลเป็นภาษาต่างดาวใน API Response |
| `app/Controllers/Api/SpaApiController.php` | เพิ่ม Scoped Permissions และเกิดข้อความภาษาไทยเสีย (Mojibake) ในส่วน News/FAQs | ข้อความ Error แสดงผลเป็นภาษาต่างดาวใน API Response |
| `app/Core/Session.php` | เพิ่มการอ่านค่า `domain` จาก Config `security.session.domain` | ช่วยเรื่อง Cookie Scope แต่ต้องระวังกรณีทดสอบข้าม Subdomain |
| `config/routes.php` | ปรับ Route กลุ่มสมาชิก ข่าว และ FAQ ให้ใช้ `PermissionMiddleware` และเพิ่ม Route กลุ่ม `/api/admin/access/*` | ทำให้ `SecurityRegressionTest.php` ล้มเหลว 10 รายการ |
| `config/security.php` | เพิ่ม Config `'domain' => env('SESSION_DOMAIN', '')` | ไม่มีผลกระทบเชิงลบ |
| `src/App.jsx` | Import `AdminAccessPage` และ `AdminWorkspacePage`, เพิ่ม Route `/admin/access` และ `/admin/workspace`, สลับ Guards มาเช็ก `permission` | **ทำให้ Vite Build ล้มเหลวทันที (Rollup Error)** เนื่องจากไฟล์ Page ไม่มีอยู่จริง |
| `src/context/AuthContext.jsx` | เพิ่มฟังก์ชัน `can(permission)` และเก็บ Permissions ใน State ของ User | ใช้งานได้ตามปกติ แต่ผูกติดกับการที่ Backend ส่ง Permissions มา |

### 2.3 รายการไฟล์ที่ยังไม่ได้ Track (Untracked Files)
1. `app/Controllers/Admin/AccessController.php` — คอนโทรลเลอร์จัดการ Roles & Permissions และ Users
2. `app/Middlewares/PermissionMiddleware.php` — Middleware สำหรับตรวจสิทธิ์ระดับ Module/Action
3. `app/Services/AccessService.php` — Service ตรวจสอบและบันทึกสิทธิ์ (มี Optimistic Locking และ Transaction ครบถ้วน)
4. `database/migrations/017_access_management_permissions.sql` — ไฟล์ SQL Seed ข้อมูล Permissions เริ่มต้น
5. `ปันผลเฉลี่ยคืนปี2568ลงเว็บ.xlsx` และ `เงินปันผลเฉลี่ยคืนปี2567ลงเว็บ.xlsx` — ไฟล์ข้อมูลทางการเงินจริง (ห้าม Commit, ห้ามนำเข้า Git)
6. `outputs/` — โฟลเดอร์เก็บผลลัพธ์การทดสอบและ Worktree แยก

---

## 3. การตรวจสอบสถาปัตยกรรมและโครงสร้างโค้ด (Source Code & Module Inventory)

| กลุ่มงาน / องค์ประกอบ | จำนวนไฟล์ | รายละเอียด |
|---|---:|---|
| **PHP Controllers** | 59 | แยกเป็น Admin (10), Member (6), Public (16), Api (3), และ Controllers ทั่วไป |
| **PHP Core Classes** | 12 | `App`, `Auth`, `Controller`, `Database`, `Helpers`, `Logger`, `Model`, `Request`, `Response`, `Router`, `Session`, `View` |
| **PHP Services** | 23 | บริการธุรกิจ: `AccessService`, `DividendImportService`, `MemberAdminService`, `MemberPortalService`, `TwoFactorService`, `RichTextService`, `Led*Services`, ฯลฯ |
| **PHP Middlewares** | 6 | `AuthMiddleware`, `RoleMiddleware`, `PermissionMiddleware`, `CsrfMiddleware`, `RateLimitMiddleware`, `FeatureFlagMiddleware` |
| **Views Template** | **0** | **ไม่พบไดเรกทอรี `views/` ในโครงการ** (แต่มีการเรียก `$this->render()` รวม 96 จุดใน Controllers) |
| **React Pages** | 36 | หน้าจอหลักสำหรับสมาชิก, บุคคลทั่วไป และระบบหลังบ้าน |
| **React Components** | 37 | Component ส่วนกลาง เช่น Header, Footer, Modals, Forms, RichTextEditor |
| **React Contexts & Hooks** | 4 | `AuthContext`, `ToastContext`, `ThemeContext`, `useDebounce` |
| **Frontend API Clients** | 3 | `src/services/api.js`, `memberAdminApi.js`, `ledApi.js` |
| **Database Migrations** | 19 | Migrations `001` ถึง `018` (มี `006b` และ `017` ที่ยังไม่ Commit) |
| **Automated Test Files** | 13 | ไฟล์ทดสอบใน `tests/` ทั้ง PHP และ JS |
| **PHP Registered Routes** | 253 | ประกาศไว้ใน `config/routes.php` (ตรวจสอบผ่าน Router Reflection) |

---

## 4. ผลการรันชุดทดสอบที่มีอยู่จริง (Test Execution Baseline)

| คำสั่งทดสอบ | ผลการทดสอบ | รายละเอียดและสาเหตุที่แท้จริง |
|---|---|---|
| `php tests/SecurityRegressionTest.php` | **FAIL (10 checks)** | ล้มเหลวในส่วน `assertRouteHasRoles` เนื่องจาก `config/routes.php` ถูกเปลี่ยนไปใช้ `PermissionMiddleware` แทน `RoleMiddleware` |
| `php tests/DividendImportTest.php` | **FAIL (Line 31)** | ผ่าน 12 Checks แรก แต่ Exception ตอน Parse ไฟล์ `member-dividends-template.xlsx` เพราะไฟล์แม่แบบถูกแก้เป็นไฟล์ว่างเปล่า ไม่มีแถวตัวอย่าง |
| `php tests/AuthRedirectRegressionTest.php` | **FAIL (1 check)** | ไม่พบข้อความ `default => '/staff/dashboard'` ใน `AuthController.php` เพราะถูกแก้เป็น `default => '/admin/workspace'` |
| `php tests/LoanMasterDataTest.php` | **FAIL (Interest Rates)** | ในฐานข้อมูล Local ตรวจพบประเภทอัตราดอกเบี้ย 12 รายการ เกินจาก Master Data ที่คาดหวัง 10 รายการ (มีอัตราดอกเบี้ยซ้ำซ้อนใน Seed) |
| `npm run build` | **FAIL (Vite Build)** | เกิด Rollup Error: ไม่สามารถ Resolve Module `./pages/AdminAccessPage` จาก `src/App.jsx` |
| `node tests/NavigationRedirectTest.mjs` | **PASS (8 cases)** | ฟังก์ชันการคำนวณและ Redirect Navigation ทำงานถูกต้อง |
| `php tests/MediaUrlTest.php` | **PASS (11 cases)** | ฟังก์ชัน Resolve Media URL ทำงานถูกต้องสมบูรณ์ |
| `php tests/RichTextServiceTest.php` | **PASS (6 cases)** | การ Sanitize และจัดรูปแบบ Rich Text HTML ทำงานถูกต้องปลอดภัย |
| `php tests/MemberPortalRequestsTest.php` | **PASS (8 cases)** | ระบบยื่นคำร้องสมาชิกและการอนุมัติข้อมูลทำงานได้ถูกต้องตาม Isolation |
| `php tests/MemberAdministrationTest.php` | **PASS (17 cases)** | ระบบจัดการสมาชิก นำเข้าปันผล และประวัติการแก้ไขทำงานผ่านทุกเงื่อนไข |
| `npm audit` | **FAIL (11 advisories)** | พบช่องโหว่ใน NPM Dependencies: High 7 รายการ, Moderate 4 รายการ (ส่วนใหญ่ใน Vite, Tailwind, และ PostCSS toolchain) |

---

## 5. การเจาะลึก 10 ประเด็นสำคัญตาม Master Prompt (Deep-Dive Analysis)

### 5.1 การเปลี่ยนแปลงที่ยังไม่ได้ Commit (Uncommitted Git Changes)
- **สถานะ:** มีการพัฒนาระบบ Granular Access Control (RBAC 2.0) ค้างไว้ โดยสร้าง Service และ Controller ใหม่ แต่ไม่ได้สร้างหน้า React UI (`AdminAccessPage.jsx`, `AdminWorkspacePage.jsx`) มารองรับ
- **ปัญหา Encoding:** มีการบันทึกไฟล์ PHP 2 ไฟล์ด้วยการเข้ารหัสที่ไม่ใช่ UTF-8 ที่ถูกต้อง ทำให้ข้อความแจ้งเตือนภาษาไทยเสียหาย

### 5.2 การทำงานของระบบ Login และ Session ในปัจจุบัน
- **Session Lifecycle:** `Session.php` มีการตรวจสอบ Idle Timeout (30 นาที) และมี Age Rotation (รีเซ็ต Session ID ทุก 30 นาที) แต่ `Auth::verify2FA()` **ไม่ได้สั่ง Regenerate Session ID หลัง 2FA ผ่าน** (เสี่ยง Session Fixation)
- **Session Expiration:** ขาดการบังคับใช้อายุสูงสุดของ Session แบบเด็ดขาด (Absolute Lifetime)
- **การตัดช่องว่างรหัสผ่าน (Password Whitespace Trimming):** `Request::sanitizeArray()` ดำเนินการ `trim()` กับทุก String Input ส่งผลให้รหัสผ่านที่มีช่องว่างหน้าหรือหลังถูกตัดออกก่อนส่งไปให้ `password_verify()`

### 5.3 ความไม่สมบูรณ์ของระบบ TOTP 2FA (Incomplete TOTP Enrollment)
- ปัจจุบันระบบมีเพียงการตรวจรหัส OTP ตอนเข้าสู่ระบบสำหรับบัญชีที่เปิดใช้งาน 2FA ไว้ล่วงหน้า
- **ไม่มี Endpoint และหน้า UI สำหรับการเปิดใช้งาน 2FA (Setup / Enrollment Workflow)**
- ไม่มีการยืนยันรหัสผ่านเดิมก่อนลงทะเบียน และไม่มีการบันทึกสถานะรอดำเนินการ (Pending Confirmation)
- ขาดระบบกู้คืนบัญชี (One-Time Recovery Codes)

### 5.4 ปัญหาหน้าจอ `auth.two_factor` หายไป (Missing Two-Factor View)
- ใน `AuthController::showTwoFactor()` มีคำสั่ง `$this->render('auth.two_factor', [...])`
- อย่างไรก็ตาม ไดเรกทอรี `views/` **ไม่มีอยู่ในโปรเจกต์** ส่งผลให้เมื่อเข้าหน้า `/admin/2fa` ผ่าน Web Browser โดยตรง ระบบจะเกิด Exception `View file not found: .../views/auth/two_factor.php` ทันที

### 5.5 การรั่วไหลของ TOTP Secret สู่ภายนอกผ่านบริการสร้าง QR Code (External QR Leak)
- ใน `TwoFactorService::getQrCodeUrl()`:
  ```php
  $otpAuthUrl = sprintf('otpauth://totp/%s:%s?secret=%s...', rawurlencode($issuer), rawurlencode($email), $secret, ...);
  return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($otpAuthUrl);
  ```
- **ข้อบกพร่อง:** โค้ดส่ง URL `otpauth://` ที่มี Secret ในรูปแบบตัวอักษรธรรมดา (Plaintext) ไปยังผู้ให้บริการภายนอก (`api.qrserver.com`) ซึ่งขัดกับหลักความมั่นคงปลอดภัยอย่างร้ายแรง ต้องเปลี่ยนมาสร้าง QR Code ภายในเครื่องแบบ Standalone โดยไม่เรียก External API ใดๆ

### 5.6 การป้องกัน Brute-force และ OTP Replay
- ฟังก์ชัน `TwoFactorService::verifyCode()` ใช้เวลาปัจจุบัน ± 1 Time-step (รวมช่วงเวลาตรวจ 90 วินาที)
- **ไม่มีการบันทึก Time-slice ที่เคยใช้งานแล้ว:** ผู้ใช้สามารถนำรหัส 6 หลักเดิมมาใช้ซ้ำได้ภายในช่วงเวลา 90 วินาที (OTP Replay Attack)
- **ไม่มี Dedicated Rate Limiter:** หน้า `/admin/2fa` ไม่มี Rate Limit แยกเฉพาะสำหรับป้องกันการสุ่มรหัส OTP 6 หลัก

### 5.7 ข้อขัดแย้งของ Route ระหว่าง React และ PHP (React vs PHP Route Conflicts)
- มีการประกาศ Route สาธารณะใน PHP (`config/routes.php`) ถึง 253 Routes ซึ่งคอนโทรลเลอร์ฝั่ง PHP พยายามเรียก `$this->render()` ไปยัง View ที่ไม่มีอยู่
- ในขณะเดียวกัน หน้าเว็บจริงถูกให้บริการผ่าน React Router SPA
- ใน `AuthMiddleware.php` เมื่อตรวจพบ Session ที่ค้าง 2FA (`Auth::checkPending2FA()`) ระบบสั่ง HTTP 302 Redirect ไปยัง `/admin/2fa` ทันที แม้คำขอจะเป็น AJAX API ทำให้ Single Page Application ไม่สามารถจัดการ Response ในรูปแบบ JSON ได้

### 5.8 การแยก Configuration ระหว่าง TEST และ Production
- ไฟล์ `.env.example` ยังคงอ้างอิงโดเมนและพอร์ตเก่า (`https://rayongcoop.tomvisolution.tech`)
- ค่า Default ของ `SESSION_SECURE` ใน `config/security.php` เป็น `false` (หากลืมตั้งใน `.env` คุกกี้จะไม่ได้รับแฟล็ก Secure)
- ชื่อ Session Cookie ถูกกำหนดตายตัวเป็น `rayongcoop_session` โดยไม่เปิดให้ตั้งค่าแยกสำหรับ TEST Environment (`rayongcoop_test_session`) เพื่อป้องกัน Session ชนกัน
- `vite.config.js` มีการกำหนดค่า Production Base ไว้อย่างตายตัวที่ `/app/` และ Output ไปยัง `public/app` ขัดกับข้อกำหนดของ TEST Subdomain ที่ต้องการ Base `/`

### 5.9 ความสมบูรณ์ของการนำเข้าข้อมูลการเงินและเงินปันผล (Financial Import Integrity)
- โค้ด `DividendImportService.php` มีมาตรการด้าน Data Integrity ที่ดีมาก (ใช้ Database Transaction, Database Row Locking `FOR UPDATE`, ตรวจสอบผลรวมทศนิยมระดับสตางค์, และมี Snapshot Concurrency Check)
- อย่างไรก็ตาม การทดสอบ `DividendImportTest.php` ล้มเหลวเพราะตัวอย่างในไฟล์แม่แบบขาดหายไป ต้องฟื้นฟูไฟล์แม่แบบให้มีตัวอย่างข้อมูล 1 แถวเพื่อรองรับทั้งการใช้งานของเจ้าหน้าที่และการทดสอบอัตโนมัติ

### 5.10 การตรวจสอบระบบสำรองข้อมูลและการกู้คืน (Backup & Recovery Verification)
- ใน `BackupController.php` บรรทัดที่ 12 มีการเรียกใช้ `use App\Services\Logger;` ซึ่ง Class ดังกล่าวไม่มีอยู่จริง (ตำแหน่งจริงคือ `App\Core\Logger`) ส่งผลให้เกิด Fatal Error ทุกครั้งที่มีการบันทึก Log ใน Controller นี้
- เมธอด `restoreBackup()` ใน `BackupController.php` ทำการอ่านไฟล์ SQL Dump และสั่ง `exec()` ตรงเข้าสู่ฐานข้อมูลที่มีการใช้งานอยู่ โดยไม่มีการสำรองข้อมูลปัจจุบันก่อน, ไม่มีการทดสอบ Schema, และไม่มีการจำกัดสิทธิ์ในระดับ Transaction ซึ่งมีความเสี่ยงสูงที่จะทำให้ฐานข้อมูลเสียหายหากไฟล์ Dump ไม่สมบูรณ์

---

## 6. ความเสี่ยงด้านความมั่นคงปลอดภัย (Security Dependencies Audit)

จากการรัน `npm audit --json` พบช่องโหว่ความปลอดภัยใน Dependency ทั้งสิ้น 11 รายการ (High 7, Moderate 4):
- **Vite (CWE-1333, CWE-22):** ช่องโหว่ Denial of Service และ Path Traversal ใน Vite Dev Server
- **TailwindCSS / PostCSS Chain:** ช่องโหว่ Regular Expression ReDoS ใน Tooling สำหรับ Build

*หมายเหตุ: ช่องโหว่เหล่านี้อยู่ใน Development / Build Toolchain ยังไม่ปรากฏหลักฐานการ Exploit ใน Production Runtime แต่จำเป็นต้องได้รับการอัปเกรดแบบควบคุมในการดำเนินงานระยะถัดไป*

---

## 7. เกณฑ์การผ่านและขั้นตอนต่อไป (Acceptance Gates for Phase 0)

1. [x] ตรวจสอบ Inventory โครงสร้างระบบ, Controllers, Services, Middlewares, Routes, และ UI Components ครบถ้วน
2. [x] ตรวจสอบสถานะ Git และงานที่ยังไม่ได้ Commit ทั้งหมดโดยละเอียด
3. [x] ตรวจสอบปัญหา 10 ประเด็นสำคัญตาม Master Prompt ครบทุกข้อ
4. [x] ไม่มีการแก้ไข Source Code ใดๆ ระหว่างการทำ Phase 0 Baseline Audit
5. [x] จัดทำเอกสารครบถ้วนตามข้อกำหนดใน `docs/audit/`:
   - `BASELINE_REPORT.md`
   - `ARCHITECTURE.md`
   - `ROUTE_INVENTORY.md`
   - `RISK_REGISTER.md`
   - `CHANGE_PLAN.md`

**สรุปคำแนะนำ:** ขออนุมัติปิด **PHASE 0** และเสนอเริ่มดำเนินการกู้คืนระบบตามลำดับความสำคัญใน **PHASE 1** โดยเริ่มจากการจัดการ Checkpoint งานค้าง, การคืน Baseline Build ของ React และการแก้ไข Encoding ภาษาไทย ก่อนดำเนินการด้าน Authentication และ 2FA เป็นลำดับถัดไป
