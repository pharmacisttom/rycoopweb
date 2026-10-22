# RYCOOPWEB 2.1 — ทะเบียนประเมินความเสี่ยง (RISK REGISTER)
**ระบบบริหารจัดการข้อมูลและบริการดิจิทัล สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด**  
**วันที่ประเมิน:** 21 ตุลาคม 2569  
**มาตรฐานการประเมิน:** การตรวจสอบหลักฐานเชิงประจักษ์จาก Source Code จริง (Verified Findings vs Suspected Issues)

---

## 1. เกณฑ์การจัดระดับความเสี่ยง (Risk Severity Matrix)

- **CRITICAL:** ช่องโหว่หรือความผิดพลาดที่เปิดโอกาสให้เกิด Remote Code Execution, Authentication Bypass สมบูรณ์, หรือการสูญเสียข้อมูลสมาชิก/การเงินทันที
- **HIGH:** ความผิดพลาดของระบบที่ทำให้ Build ไม่ผ่าน, การรั่วไหลของ Credential หรือ TOTP Secret สู่ภายนอก, การขาดระบบความปลอดภัยสำคัญ (Replay, Session Fixation), หรือความเสียหายของฟังก์ชันหลัก
- **MEDIUM:** ปัญหาความไม่เข้ากันของการทำงาน, ข้อความ Error ไม่สอดคล้องกัน, ขาดการควบคุมขีดจำกัดแบบเด็ดขาด (Timeouts / Limits), หรือปัญหา Configuration ปานกลาง
- **LOW:** ปัญหาด้านการแสดงผล, การเข้ารหัสภาษาไทยในบาง Controller, หรือความแตกต่างเล็กน้อยของเวอร์ชันสภาพแวดล้อม

---

## 2. รายการความเสี่ยงระดับสูง (HIGH RISKS: H01 – H11)

### [H01] Frontend Build ล้มเหลวเนื่องจากการ Import Component ที่ไม่มีอยู่จริง
- **หมวดหมู่:** Frontend / Build System
- **สถานะ:** **Verified** (ยืนยันจากคำสั่ง `npm run build`)
- **หลักฐาน:** `src/App.jsx` บรรทัดที่ 32–33 มีการประกาศ Import:
  ```javascript
  const AdminAccessPage = lazy(() => import('./pages/AdminAccessPage'));
  const AdminWorkspacePage = lazy(() => import('./pages/AdminWorkspacePage'));
  ```
  โดยที่ไม่มีไฟล์ `src/pages/AdminAccessPage.jsx` และ `src/pages/AdminWorkspacePage.jsx` อยู่จริงในโปรเจกต์
- **ผลกระทบ:** ไม่สามารถทำการ Build Production/TEST Bundle ได้เลย ทำให้กระบวนการ Deployment ถูกบล็อกทั้งหมด
- **แนวทางแก้ไข:** สร้าง Component `AdminAccessPage.jsx` และ `AdminWorkspacePage.jsx` ที่จำเป็น หรือจัดทำหน้าจอ Fallback/Placeholder ที่สมบูรณ์ตามสิทธิ์ของระบบ

### [H02] หน้าจอ `auth.two_factor` ขาดหายไป ส่งผลให้ PHP Controller Render ไม่สำเร็จ
- **หมวดหมู่:** 2FA / Backend View
- **สถานะ:** **Verified** (ยืนยันจากโค้ดและโครงสร้างไฟล์)
- **หลักฐาน:** `AuthController::showTwoFactor()` เรียก `$this->render('auth.two_factor', ...)` แต่ในระบบ **ไม่มีโฟลเดอร์ `views/`** ส่งผลให้ `View::render()` โยน RuntimeException `View file not found`
- **ผลกระทบ:** ผู้ใช้ที่ติดสถานะ Pending 2FA เมื่อเปิดเข้าหน้านี้ผ่าน Web Browser จะพบกับ Error 500 ทันที
- **แนวทางแก้ไข:** ออกแบบและจัดเตรียมหน้าจอ Two-Factor Verification ที่ทำงานร่วมกับ React SPA หรือจัดเตรียม View Template ที่สมบูรณ์ในกรณีที่ต้องรองรับ Direct PHP Fallback

### [H03] การส่ง TOTP Secret ในรูปแบบ Plaintext ไปยังบริการภายนอกเพื่อสร้าง QR Code
- **หมวดหมู่:** 2FA / Information Disclosure
- **สถานะ:** **Verified** (ยืนยันจากโค้ด `TwoFactorService.php`)
- **หลักฐาน:** ใน `TwoFactorService.php` บรรทัดที่ 45:
  ```php
  return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . rawurlencode($otpAuthUrl);
  ```
  ซึ่ง `$otpAuthUrl` บรรจุ Plaintext Secret ของผู้ใช้และส่งออกไปยังเซิร์ฟเวอร์ภายนอกผ่านอินเทอร์เน็ต
- **ผลกระทบ:** ผู้ให้บริการภายนอก (`api.qrserver.com`) และเครือข่ายระหว่างทางสามารถล่วงรู้รหัสลับ 2FA ของผู้ดูแลระบบสหกรณ์ได้
- **แนวทางแก้ไข:** เปลี่ยนมาใช้ไลบรารีสร้าง QR Code ภายในเซิร์ฟเวอร์ (เช่น SVG Generator ในเครื่อง) โดยไม่ส่งข้อมูลลับออกนอกระบบโดยเด็ดขาด

### [H04] ขาดระบบป้องกัน OTP Replay และขาด Dedicated Rate Limiting สำหรับ 2FA
- **หมวดหมู่:** 2FA / Authentication Security
- **สถานะ:** **Verified** (ยืนยันจากโค้ด `TwoFactorService.php` และ `AuthController.php`)
- **หลักฐาน:** `verifyCode()` ยอมรับรหัสภายใน Time-window 90 วินาที โดยไม่มีการบันทึก Time-slice ที่ใช้แล้ว ทำให้รหัสเดียวกันสามารถส่งซ้ำได้หลายครั้งภายในหน้าต่างเวลา และไม่มี Rate Limit สกัดกั้นการเดารหัส 6 หลัก
- **ผลกระทบ:** มีความเสี่ยงต่อการเดารหัสแบบ Brute-force และการถูกดักฟังนำรหัสมาใช้ซ้ำ (Replay Attack)
- **แนวทางแก้ไข:** เพิ่มตารางจัดเก็บ Time-slice ที่ยืนยันสำเร็จล่าสุดของผู้ใช้เพื่อป้องกัน Replay และติดตั้ง Rate Limit เฉพาะสำหรับการยืนยัน OTP (เช่น สูงสุด 5 ครั้งต่อ 10 นาที)

### [H05] การตัดช่องว่างรหัสผ่าน (Password Whitespace Trimming) ใน Request Sanitizer
- **หมวดหมู่:** Authentication / Data Integrity
- **สถานะ:** **Verified** (ยืนยันจากโค้ด `Request.php`)
- **หลักฐาน:** ใน `Request::sanitizeArray()` มีคำสั่ง `$clean[$cleanKey] = trim($val);` ซึ่งทำงานกับทุก String Parameter รวมถึงช่อง Password ที่ส่งเข้ามาทาง POST/JSON
- **ผลกระทบ:** ผู้ใช้งานที่ตั้งรหัสผ่านมีอักขระช่องว่าง (Space) ที่หัวหรือท้าย จะไม่สามารถเข้าสู่ระบบได้ เพราะรหัสผ่านถูกแปลงก่อนส่งเข้า `password_verify()`
- **แนวทางแก้ไข:** เพิ่มเมธอด `rawInput()` ใน `Request.php` เพื่อดึงค่าดิบของรหัสผ่านโดยไม่ผ่านการ Trim สำหรับใช้ใน Authentication Controller

### [H06] ไม่มีการ Regenerate Session ID ภายหลังการยืนยัน 2FA สำเร็จ
- **หมวดหมู่:** Session Management / Fixation
- **สถานะ:** **Verified** (ยืนยันจากโค้ด `Auth.php`)
- **หลักฐาน:** ใน `Auth::verify2FA()`:
  ```php
  public static function verify2FA(): void {
      Session::start();
      Session::set('2fa_verified', true);
  }
  ```
  ไม่มีการเรียก `Session::regenerate()` แตกต่างจาก `Auth::login()` ที่มีการเรียกใช้งาน
- **ผลกระทบ:** เสี่ยงต่อการโจมตีแบบ Session Fixation โดยผู้ไม่ประสงค์ดีอาจกำหนด Session ID ค้างไว้ล่วงหน้า
- **แนวทางแก้ไข:** เพิ่มคำสั่ง `Session::regenerate()` ใน `Auth::verify2FA()` ทันทีที่มีการยืนยันรหัสถูกต้อง

### [H07] ชุดทดสอบความปลอดภัยล้มเหลว 10 รายการหลังปรับเปลี่ยน Route ไปใช้ PermissionMiddleware
- **หมวดหมู่:** Authorization / Regression
- **สถานะ:** **Verified** (ยืนยันจากการรัน `php tests/SecurityRegressionTest.php`)
- **หลักฐาน:** การทดสอบความปลอดภัยเดิม Assert ว่า Route ของระบบจัดการสมาชิกและปันผลต้องมี `RoleMiddleware` แต่ใน `config/routes.php` มีการเปลี่ยนไปใช้ `PermissionMiddleware` เดี่ยวๆ
- **ผลกระทบ:** ส่งผลให้ชุดทดสอบ Regression Fail และการตรวจสอบสิทธิ์ระดับ Role ดั้งเดิมอาจไม่ครอบคลุมครบถ้วน
- **แนวทางแก้ไข:** กำหนดสถาปัตยกรรมแบบ Hybrid โดยให้ Route สำคัญได้รับการปกป้องทั้งระดับ Role และ Granular Permission (Defense-in-depth) พร้อมปรับชุดทดสอบให้ตรวจสอบความเทียบเท่าของสิทธิ์

### [H08] ช่องโหว่ความปลอดภัยในแพ็กเกจ NPM Dependencies (11 Advisories)
- **หมวดหมู่:** Third-party Dependencies
- **สถานะ:** **Verified** (ยืนยันจากผล `npm audit`)
- **หลักฐาน:** พบช่องโหว่ High 7 รายการ และ Moderate 4 รายการ ใน Vite, PostCSS, และ Tailwind toolchain
- **ผลกระทบ:** อาจก่อให้เกิด ReDoS หรือช่องโหว่ในระหว่างกระบวนการ Local Development และ Build
- **แนวทางแก้ไข:** ตรวจสอบและอัปเดต Dependencies ที่ได้รับผลกระทบอย่างระมัดระวัง โดยทดสอบการ Build ซ้ำเพื่อป้องกัน Breaking Changes

### [H09] ความคลาดเคลื่อนของข้อมูลอัตราดอกเบี้ยและผลิตภัณฑ์เงินกู้ในฐานข้อมูล
- **หมวดหมู่:** Financial Master Data
- **สถานะ:** **Verified** (ยืนยันจากคำสั่ง `php tests/LoanMasterDataTest.php`)
- **หลักฐาน:** ในฐานข้อมูล Local พบอัตราดอกเบี้ย 12 รายการ เกินจาก Master Data ที่คาดหวัง 10 รายการ (มีชื่อซ้ำซ้อนในตาราง `interest_rates`)
- **ผลกระทบ:** อัตราดอกเบี้ยที่แสดงผลในหน้าเว็บอาจไม่ตรงกับประกาศทางการของสหกรณ์
- **แนวทางแก้ไข:** จัดทำ Migration ปรับปรุงข้อมูล Master Data ให้ตรงกับประกาศฉบับล่าสุดที่ได้รับอนุมัติจากสหกรณ์

### [H10] ข้อผิดพลาดร้ายแรงในระบบกู้คืนฐานข้อมูล (Backup & Restore Controller)
- **หมวดหมู่:** Backup / Database Recovery
- **สถานะ:** **Verified** (ยืนยันจากโค้ด `BackupController.php`)
- **หลักฐาน:** 
  1. มีคำสั่ง `use App\Services\Logger;` ซึ่ง Class นี้ไม่มีอยู่จริง (ตำแหน่งจริงคือ `App\Core\Logger`) ทำให้เกิด Fatal Error เมื่อ Logger ทำงาน
  2. เมธอด `restoreBackup()` รันคำสั่ง `DROP TABLE` และ SQL Dump ลงในฐานข้อมูลจริงโดยตรง โดยไม่มีการตรวจสอบ Checksum และไม่มีการสร้างจุดย้อนกลับ (Rollback Point)
- **ผลกระทบ:** หากไฟล์สำรองข้อมูลชำรุด ฐานข้อมูลจะถูกล้างตารางทิ้งบางส่วนทันที และไม่สามารถกู้คืนได้
- **แนวทางแก้ไข:** แก้ไข Class Namespace ของ Logger และปรับปรุงกระบวนการ Restore ให้มี Pre-flight Check, ตรวจสอบ Checksum, และสร้าง Backup สำรองอัตโนมัติก่อนเริ่มเขียนทับ

### [H11] ข้อจำกัดพื้นที่ดิสก์และข้อผิดพลาดในการสร้างตารางชั่วคราว
- **หมวดหมู่:** Environment & Capacity
- **สถานะ:** **Verified** (ยืนยันจากข้อความระบบ OS error 112 และ MySQL errno 135)
- **หลักฐาน:** การทดสอบระบบสมาชิกแบบ Multi-table บางครั้งไม่สามารถสร้างตารางชั่วคราวได้เนื่องจาก Disk Space ขัดข้อง
- **ผลกระทบ:** การรัน Automated Test ชุดใหญ่หรือการนำเข้าข้อมูล Excel ขนาดใหญ่อาจสะดุดกลางคัน
- **แนวทางแก้ไข:** กำหนดให้การทดสอบฐานข้อมูลต้องตรวจสอบความพร้อมของพื้นที่และ Environment ก่อนทำงาน และล้างไฟล์ Temporary Logs อย่างเป็นระบบ

---

## 3. รายการความเสี่ยงระดับปานกลาง (MEDIUM RISKS: M01 – M08)

| ID | หัวข้อความเสี่ยง | สถานะ | หลักฐาน / ผลกระทบ | แนวทางแก้ไข |
|---|---|---|---|---|
| **M01** | ค่า Cookie Security เริ่มต้นเป็น `false` | Verified | `config/security.php` บรรทัดที่ 10 ตั้งค่า `SESSION_SECURE` เริ่มต้นเป็น `false` หากไม่มีการตั้งใน `.env` | บังคับให้เป็น `true` โดยอัตโนมัติเมื่อ Request เข้ามาทาง HTTPS |
| **M02** | `AuthMiddleware` ส่ง HTTP 302 Redirect ต่อคำขอแบบ AJAX เมื่อติด 2FA | Verified | ใน `AuthMiddleware.php` มีการตรวจ 2FA ก่อนตรวจสอบ `$request->isAjax()` ทำให้ SPA ได้รับ 302 แทนที่จะเป็น JSON `TWO_FACTOR_REQUIRED` | ย้ายเงื่อนไขการส่ง JSON 401/403 ขึ้นมาก่อนคำสั่ง Redirect |
| **M03** | การคิวรีผู้ใช้หลาย Role ไม่มีการจัดลำดับความสำคัญที่ชัดเจน (Determinism) | Verified | SQL ใน `Auth::user()` ใช้ `LEFT JOIN user_roles` และ `LIMIT 1` โดยไม่มีการ `ORDER BY` ความสำคัญของ Role | กำหนดลำดับความสำคัญของ Role (เช่น `super_admin` > `staff` > `member`) ให้ชัดเจน |
| **M04** | การทดสอบนำเข้าปันผลล้มเหลวเพราะแม่แบบไม่มีข้อมูลตัวอย่าง | Verified | `DividendImportTest.php` ล้มเหลวที่บรรทัด 31 เนื่องจาก `member-dividends-template.xlsx` ไม่มีแถวข้อมูล | ฟื้นฟูแม่แบบให้มีตัวอย่าง 1 แถว พร้อมรหัสบัตรประชาชนตัวอย่าง |
| **M05** | ความซ้ำซ้อนระหว่าง PHP View Handlers (253 Routes) และ React SPA | Verified | มี Route สาธารณะใน PHP มากมายที่ไม่มี View รองรับจริง ขณะที่หน้าเว็บจริงให้บริการผ่าน React | ตรวจสอบและระบุ Route ให้ชัดเจนว่าเป็น Web API หรือ Web Page |
| **M06** | Vite Base Path ตั้งเป็น `/app/` ไม่ตรงกับเป้าหมาย TEST Subdomain (`/`) | Verified | ใน `vite.config.js` บรรทัดที่ 14 กำหนด `base: '/app/'` | ปรับแต่ง Vite Config ให้รองรับ Multi-environment Build Profile |
| **M07** | Integration Tests บางตัวเชื่อมต่อ Database โดยไม่มี Guard ป้องกัน | Verified | Script ทดสอบบางตัวพยายาม Clone Schema บนการเชื่อมต่อหลัก | เพิ่ม Environment Guard (`APP_ENV === 'testing'`) ก่อนอนุญาตให้ทดสอบ |
| **M08** | ขาดการพิสูจน์การเข้ารหัสและการสำรองข้อมูลภายนอก (Off-site Backup) | Suspected | ยังไม่พบหลักฐานว่าระบบ Backup มีการส่งไฟล์ออกนอก VPS หรือมีการเข้ารหัสไฟล์ Dump | วางแผนระบบสำรองข้อมูลพร้อมการเข้ารหัส GPG/OpenSSL |

---

## 4. รายการความเสี่ยงระดับต่ำ (LOW RISKS: L01 – L02)

| ID | หัวข้อความเสี่ยง | สถานะ | รายละเอียด |
|---|---|---|---|
| **L01** | ความต่างของ PHP CLI ใน Local (8.2.12) และเซิร์ฟเวอร์เป้าหมาย (8.3) | Verified | การพัฒนาในเครื่องใช้ PHP 8.2 แต่เซิร์ฟเวอร์ TEST ใช้ PHP 8.3 ต้องระวังฟีเจอร์เฉพาะของเวอร์ชัน |
| **L02** | ข้อความภาษาไทยเสียหาย (Mojibake) ในไฟล์ที่ยังไม่ได้ Commit | Verified | ใน `SpaApiController.php` และ `MemberManagementController.php` มีข้อความสตริงภาษาไทยกลายเป็นอักขระผิดเพี้ยน ต้องกู้คืนการเข้ารหัสเป็น UTF-8 |
