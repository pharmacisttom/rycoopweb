# RYCOOPWEB 2.1 — สถาปัตยกรรมระบบ (SYSTEM ARCHITECTURE)
**ระบบบริหารจัดการข้อมูลและบริการดิจิทัล สหกรณ์ออมทรัพย์สาธารณสุขระยอง จำกัด**  
**เอกสารสถาปัตยกรรมระบบฐานราก (Baseline Architecture Specification)**

---

## 1. ภาพรวมสถาปัตยกรรมและการไหลของข้อมูล (Request & Data Flow Overview)

ระบบ RYCOOPWEB เป็นสถาปัตยกรรมแบบ **Hybrid Single Page Application (SPA) และ PHP 8.3 MVC Backend** โดยมีการแบ่งแยกการทำงานของแต่ละส่วนอย่างชัดเจน:

```
[ Client Web Browser ]
         │
         ├─── (Static Assets: HTML, JS, CSS) ───► [ Nginx Static Root / Vite Dist ]
         │                                               │ (React Router DOM v6)
         │                                               ▼
         │                                      [ React SPA UI ]
         │                                               │
         └─── (API & Form Submissions: JSON) ────────────┘
                         │
                         ▼
             [ Nginx Reverse Proxy ] (SSL Termination, Basic Auth บน TEST)
                         │
                         ▼
             [ PHP-FPM 8.3 Application ]
                         │
                 [ public/index.php ] ───► [ App\Core\App ]
                         │
                 [ App\Core\Router ]
                         │ (Global Middleware: Session, Csrf, RateLimit)
                         ▼
            ┌────────────────────────────┐
            │ Controller Dispatch Layer  │
            ├────────────────────────────┤
            │ • Api\SpaApiController     │
            │ • Admin\AuthController     │
            │ • Admin\MemberManagement   │
            │ • Admin\DividendImport     │
            │ • Member\DividendController│
            └──────────────┬─────────────┘
                           │
             ┌─────────────┴─────────────┐
             ▼                           ▼
     [ Domain Services ]         [ Core Subsystems ]
     • AccessService             • Auth (Session / Argon2id)
     • DividendImportService     • Database (PDO Singleton)
     • MemberAdminService        • Logger & AuditService
     • TwoFactorService          • Csrf & RateLimiter
             │                           │
             └─────────────┬─────────────┘
                           ▼
                  [ MySQL 8.x Database ]
                  (InnoDB, Transactions, utf8mb4)
```

---

## 2. รายละเอียดสถาปัตยกรรมแต่ละระบบย่อย (Subsystem Architectures)

### 2.1 ระบบยืนยันตัวตนและจัดการเซสชัน (Authentication & Session Subsystem)
- **อัลกอริทึมรหัสผ่าน:** ใช้ `PASSWORD_ARGON2ID` ตามมาตรฐานความปลอดภัยระดับสูง (Memory Cost: 64MB, Time Cost: 4, Threads: 2)
- **การจัดการเซสชัน:**
  - จัดเก็บ Session แบบ Server-side โดย PHP Session Mechanism
  - ตั้งค่าคุกกี้: `HttpOnly = true`, `SameSite = Lax`, และ `Secure` (ต้องเปิดใช้งานบน HTTPS)
  - กำหนด `session.use_strict_mode = 1` เพื่อป้องกัน Session Fixation
  - ระบบ Idle Timeout ตรวจสอบระยะเวลาไม่มีการใช้งาน (30 นาที) หากเกินจะทำลาย Session ทันที
  - ระบบ Age Rotation ทำการหมุนเวียน Session ID ทุก 30 นาที
- **ข้อบกพร่องทางสถาปัตยกรรมที่ต้องปรับปรุง:**
  1. การยืนยัน 2FA ผ่านใน `Auth::verify2FA()` ยังขาดคำสั่ง `Session::regenerate()`
  2. ขาดการกำหนดอายุสูงสุดของ Session แบบ Absolute Lifetime (ป้องกันการใช้งานข้ามวันแบบไม่มีวันหมดอายุตราบใดที่ยังขยับเมาส์)
  3. `Request::sanitizeArray()` ดำเนินการ Trim ช่องว่างของรหัสผ่าน ส่งผลให้รหัสผ่านที่มีช่องว่างสูญเสียความถูกต้อง

### 2.2 ระบบยืนยันตัวตนสองขั้นตอน (Two-Factor Authentication - 2FA)
- **มาตรฐาน:** TOTP ตาม RFC 6238 (Time-based One-Time Password, 30-second time-step, 6 digits, HMAC-SHA1)
- **สถาปัตยกรรมเป้าหมาย (Target RFC 6238 Architecture):**
  1. **การสร้าง Secret:** ใช้ Cryptographically Secure Pseudorandom Number Generator (CSPRNG) ขนาด 160-bit (Base32 encoded 16-32 ตัวอักษร)
  2. **การจัดเก็บข้อมูล:** Secret ต้องได้รับการเข้ารหัสก่อนบันทึกลงใน Database (Authenticated Encryption เช่น AES-256-GCM หรือ Sodium `crypto_secretbox`) โดยจัดเก็บ Encryption Key ไว้นอก Database (ใน Environment Variable `APP_KEY` หรือ `TWO_FACTOR_KEY`)
  3. **การแสดง QR Code:** ต้องสร้างภาพ SVG หรือ Data URI ภายใน Server โดยตรง (Internal Library) **ห้ามส่ง Secret หรือ URL ไปยังภายนอกเด็ดขาด**
  4. **การป้องกัน Replay Attack:** บันทึก Timestamp หรือ Time-slice ที่ยืนยันสำเร็จล่าสุดของ User เพื่อปฏิเสธการใช้รหัสซ้ำใน Time-window เดียวกัน
  5. **ระบบรหัสกู้คืน (Recovery Codes):** สร้างชุดรหัสผ่านใช้ครั้งเดียว (One-time Recovery Codes) 8-10 ชุด จัดเก็บเฉพาะค่า Secure Hash (เช่น `password_hash` หรือ `hash_hmac`) และแสดงให้ผู้ใช้บันทึกเพียงครั้งเดียวตอนเปิดใช้งาน

### 2.3 ระบบควบคุมสิทธิ์การเข้าถึง (Authorization & RBAC Architecture)
ระบบกำลังอยู่ในช่วงเปลี่ยนผ่านระหว่าง **Role-Based Access Control (RBAC 1.0)** ไปสู่ **Granular Permission-Based Access Control (RBAC 2.0)**:
- **โครงสร้างตารางฐานข้อมูล:**
  - `roles`: กำหนดกลุ่มผู้ใช้งาน (`super_admin`, `staff`, `member_admin`, `member`, `auditor`, ฯลฯ)
  - `permissions`: กำหนดสิทธิ์แบบเจาะจงระดับ Module + Action (เช่น `news.view`, `news.create`, `members.view`, `dividends.import`)
  - `user_roles`: ความสัมพันธ์ระหว่าง User และ Role (Many-to-Many)
  - `role_permissions`: ความสัมพันธ์ระหว่าง Role และ Permission
- **ลำดับการตรวจสอบสิทธิ์ (Deny-by-Default Pipeline):**
  1. `AuthMiddleware`: ตรวจสอบว่าผู้ใช้ล็อกอินและผ่าน 2FA ครบถ้วนแล้วหรือไม่
  2. `RoleMiddleware`: ตรวจสอบว่า Role ของผู้ใช้อยู่ในกลุ่มที่ได้รับอนุญาต
  3. `PermissionMiddleware`: ตรวจสอบสิทธิ์ระดับฟังก์ชันการทำงานอย่างละเอียด
  4. `Super Admin Bypass`: สิทธิ์ `super_admin` ได้รับการยกเว้นให้เข้าถึงได้ทุกส่วนโดยอัตโนมัติ

### 2.4 ระบบความถูกต้องของข้อมูลการเงินและเงินปันผล (Financial Data Integrity)
- **โมเดลข้อมูลทางการเงิน:**
  - ตาราง `members`: เก็บข้อมูลส่วนบุคคล ข้อมูลสมาชิก เลขบัตรประชาชน และสังกัด
  - ตาราง `member_dividends`: เก็บรายละเอียดรายการเงินปันผลแต่ละปีในรูปแบบ JSON Structured Data พร้อมประวัติการนำเข้า
  - ตาราง `member_import_runs`: เก็บสถิติสรุปของการนำเข้าแต่ละรอบ (จำนวนรายการ ยอดรวมสุทธิ ผู้ดำเนินการ)
- **หลักการคำนวณและรักษาความสมบูรณ์:**
  - **การคำนวณแบบแม่นยำ:** หลีกเลี่ยง Floating-point Inaccuracy โดยแปลงยอดเงินทั้งหมดเป็น "สตางค์ (Cents)" ในการตรวจสอบผลรวม
  - **การตรวจสอบสมดุลบัญชี (Balance Equation):**
    $$\text{ยอดเงินคงเหลือ (Net)} = \sum \text{รายรับทั้งหมด (Income)} - \sum \text{รายจ่ายทั้งหมด (Deductions)}$$
    หากผลต่างเกิน 2 สตางค์ (เผื่อ Rounding ใน Excel) ระบบจะปฏิเสธการนำเข้าทันที
  - **การป้องกัน Concurrency และการแก้ไขชนกัน:** ใช้ Snapshot Versioning และฐานข้อมูล Row-Level Lock (`FOR UPDATE`) ใน Transaction เดียวกัน

### 2.5 สถาปัตยกรรมฝั่ง Frontend (React SPA)
- **Core Framework:** React 18 พร้อม Vite Build Tool และ React Router DOM v6
- **State Management:**
  - `AuthContext`: ควบคุมสถานะผู้ใช้งาน, การตรวจสอบเซสชันพื้นหลัง (Background Ping), ฟังก์ชัน `can(permission)` และการ Logout
  - `ToastContext`: จัดการ Notification และแจ้งเตือนผู้ใช้งาน
- **การแยกส่วนหน้าจอ (Code Splitting):** ใช้ `React.lazy()` และ `Suspense` สำหรับโหลดโมดูลหน้าจอตามความต้องการ
- **API Client:** รองรับการแนบ Headers ความปลอดภัย (`X-CSRF-TOKEN`, `X-Requested-With: XMLHttpRequest`, `Accept: application/json`)

---

## 3. การแมปโครงสร้างโฟลเดอร์และการ Deploy (Deployment Mapping)

| สภาพแวดล้อม | Frontend Root | Backend Root | พอร์ต / โดเมน | ฐานข้อมูล |
|---|---|---|---|---|
| **Local Dev** | `d:\rycoopweb` (Vite 3000) | `d:\rycoopweb` (PHP Built-in 8087) | `localhost:3000` | `rayongcoop_db` (Local) |
| **TEST Server** | `/var/www/rycoopweb/test-v2-static` | `/var/www/rycoopweb/test-v2` | `https://test.rayongcoop.org` (Proxy: `18081` / `18443`) | `rycoopweb_test` |
| **Production** | `/var/www/rycoopweb/public/app` | `/var/www/rycoopweb` | `https://www.rayongcoop.org` | `rayongcoop_db` |

### ข้อกำหนดด้าน Build Artifact:
1. การ Build สำหรับ TEST Server ต้องผลิตไฟล์ Static Assets แยกต่างหาก โดยใช้ Base Path เป็น `/` (Root Subdomain)
2. Static Build Artifact ต้องไม่มี Source Code PHP, ไฟล์ `.env`, เอกสารภายใน หรือ Credential ใดๆ ปะปน
3. ห้ามเขียนทับโฟลเดอร์ `public/` ของ PHP โดยตรงในการ Build แบบแยก Static
