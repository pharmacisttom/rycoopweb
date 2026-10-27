# บันทึกผลการทดสอบการถดถอยของระบบ (Regression Test Results)

**ระบบ**: RYCOOPWEB 2.3
**วันที่ทดสอบ**: 2026-10-27
**Branch**: `codex/dependency-moderate-fixes`
**สภาพแวดล้อม**: Local Build & Automated CI Fixtures
**ผู้ทดสอบ**: QA Automation Lead & Application Security Engineer

---

## 1. ผลการทดสอบ Automated Test Suites (100% Passed)

| ชื่อชุดแบบทดสอบ | ประเภท | คำสั่งที่ใช้รัน | จำนวนเคส | ผลการทดสอบ |
| :--- | :--- | :--- | :--- | :--- |
| **npm Dependency Audit** | Dependency Security | `npm audit --audit-level=moderate` | 149 packages | **PASS** (0 vulnerabilities) |
| **Vite Production Bundler** | Frontend Build | `npm run build` | 2,693 modules | **PASS** (0 errors, 7.26s) |
| **Public Content Reference** | SSR & Data Parity | `node tests/PublicContentReferenceTest.mjs` | 25+ assertions | **PASS** (กรรมการ, จนท., ดอกเบี้ย) |
| **Workflow Frontend Contracts**| Workflow & RBAC | `node tests/WorkflowFrontendTest.mjs` | 10+ cases | **PASS** (Workspace, Rates, News, MFA) |
| **Navigation & Redirects** | Routing Security | `node tests/NavigationRedirectTest.mjs` | 8 cases | **PASS** (Redirects, Splat routes, 404) |
| **Security Regression Tests** | Backend Security | `php tests/SecurityRegressionTest.php` | 42 assertions | **PASS** (Auth bypass, RBAC, Rate limit, CSRF) |
| **Migration Engine Safety** | Database Safety | `php tests/MigrationRunnerTest.php` | 10 assertions | **PASS** (Path traversal, Lock, Checksum) |
| **Two-Factor TOTP Logic** | Cryptography & MFA | `php tests/TwoFactorServiceTest.php` | 8 assertions | **PASS** (Base32, AES-GCM, Time slice) |
| **QR Code Vector Generator** | Security & Privacy | `php tests/QrCodeGeneratorTest.php` | 2 assertions | **PASS** (Local SVG, Zero network calls) |

---

## 2. รายละเอียดผลการทดสอบเฉพาะด้าน (Detailed Verification)

### 2.1 Backend RBAC & Authorization Matrix
- [x] ผู้ใช้ทั่วไปที่ไม่ล็อกอิน ไม่สามารถเข้าถึง `/admin/*` หรือ `/api/admin/*` ได้ (ส่งคืน 401 Unauthorized)
- [x] เซสชันสถานะ `pending_mfa` ไม่สามารถเรียก API เจ้าหน้าที่ได้ (ส่งคืน 403 Forbidden พร้อม redirect code)
- [x] สิทธิ์ `member_admin` สามารถดูและจัดการสมาชิกและเงินปันผลได้ แต่ไม่สามารถเข้าถึงการจัดการผู้ใช้งานหลัก (`/api/admin/users`) ได้
- [x] มีการตรวจสอบ CSRF Token ในทุกคำขอที่เป็น HTTP POST, PUT, DELETE, PATCH

### 2.2 Client-Side Routing & SPA Performance
- [x] `BrowserRouter` ทำงานราบรื่น ร่วมกับ React Router 7.18.4 ใน Library Mode
- [x] Deep Links (`/loans`, `/deposits`, `/welfare`, `/login`) เข้าถึงและรีเฟรชได้โดยตรง ไม่เกิดข้อผิดพลาด 404
- [x] เส้นทางที่ไม่ถูกต้องแสดงหน้า Custom 404 Page สวยงาม ไม่หลุดข้อผิดพลาดของระบบ
- [x] Background session probes ไม่มีการเรียก `window.location.reload()` ข้อมูลในฟอร์มของเจ้าหน้าที่ไม่สูญหาย

### 2.3 Visual & Responsive Verification
- [x] Desktop (1920x911, 1280x800): ธีมสีน้ำเงินสหกรณ์ ฟอนต์ภาษาไทย การจัดวางการ์ด อัตราดอกเบี้ย ชัดเจนถูกต้อง
- [x] Mobile (375x667): เมนูย่อเป็น Hamburger Drawer สวยงาม การ์ดดอกเบี้ยไม่ล้นขอบจอ
