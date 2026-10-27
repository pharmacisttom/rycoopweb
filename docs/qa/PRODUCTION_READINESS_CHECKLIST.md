# รายการตรวจสอบความพร้อมก่อนขึ้นระบบจริง (Production Readiness Checklist)

**ระบบ**: RYCOOPWEB 2.3  
**วันที่ประเมิน**: 2026-10-27  
**เป้าหมายการส่งมอบ**: Staging / TEST VPS (`https://test.rayongcoop.org`) -> Production (`https://www.rayongcoop.org`)  

---

## สรุปภาพรวมความพร้อม (Readiness Overview)

| ส่วนประกอบ (Component) | สถานะความพร้อม | ความเสี่ยงที่เหลืออยู่ (Residual Risk) | แผนรับมือ (Mitigation Plan) |
| :--- | :---: | :--- | :--- |
| **Dependencies & Security Audit** | **พร้อม (Ready)** | ไม่มี (Moderate/High/Critical = 0) | ควบคุมผ่าน CI `--audit-level=moderate` |
| **Frontend React / Vite Build** | **พร้อม (Ready)** | ต่ำ | ผ่านการทดสอบ Browser และ SSR ครบถ้วน |
| **Hardened Migration Engine** | **พร้อม (Ready)** | ต่ำ | มีระบบ Lock, Checksum, Target Verify |
| **Financial Migrations (019, 020)** | **รอการอนุมัติ** | สูง (หากรันโดยไม่อ่านค่าเดิม) | ต้องรัน Preflight และได้รับการอนุมัติเป็นลายลักษณ์อักษร |
| **Google OIDC & Mandatory MFA** | **พร้อมบน Staging** | ปานกลาง (การคอนฟิกใน Google Cloud Console) | ตรวจสอบ Redirect URI ตามคู่มือ `GOOGLE_CLOUD_SETUP.md` |

---

## รายการตรวจสอบ 7 ด้าน (Detailed Checklist)

### 1. ความมั่นคงปลอดภัยด้าน Dependency (Dependency Security)
- [x] ตรวจสอบ `npm audit --audit-level=moderate` ต้องได้ 0 vulnerabilities
- [x] ล็อกไฟล์ `package-lock.json` ถูกคอมมิตเข้า Git และตรงกับ `package.json`
- [x] ไม่มีคำสั่ง `npm audit fix --force` หรือการข้าม audit gate

### 2. ความปลอดภัยของระบบฐานข้อมูล (Database Migration Safety)
- [x] ระบบใช้ [MigrationRunner](file:///d:/rycoopweb/app/Services/MigrationRunner.php) ป้องกัน Path Traversal
- [x] ระบบตรวจสอบ `SELECT DATABASE()` ป้องกันการรันผิดฐานข้อมูล
- [x] มีการคำนวณ Checksum ตรวจจับการแก้ไขไฟล์ Migration ย้อนหลัง
- [x] มีคำสั่งทดสอบ `--dry-run` ก่อนการประมวลผลจริง
- [ ] มีการสำรองข้อมูลฐานข้อมูลล่าสุดก่อนรัน migration บน Production (`php bin/console backup:run`)
- [ ] Financial Migrations (019, 020) ได้รับการลงนามเห็นชอบตัวเลขอัตราดอกเบี้ยจริง

### 3. การควบคุมการเข้าถึงและการยืนยันตัวตน (Identity & Access Control)
- [x] Google OAuth ใช้ PKCE (S256), Cryptographic State และ Nonce ครบถ้วน
- [x] ไม่มีการสร้างหรือมอบสิทธิ์ระดับสูงจากอีเมลเพียงอย่างเดียว
- [x] เจ้าหน้าที่ทุกคนต้องผ่าน Mandatory MFA (TOTP)
- [x] ภาพ QR Code ถูกสร้างภายในเครื่องเซิร์ฟเวอร์แบบ Vector SVG (ไม่มีการส่ง Secret ออกนอกระบบ)
- [x] TOTP Secret ถูกเข้ารหัสด้วย AES-256-GCM ในฐานข้อมูล
- [x] มีระบบป้องกัน OTP Replay Attack ด้วย Time Slice Guard
- [x] Session มีการแยก 3 ระดับ และป้องกันไม่ให้ Pending MFA เข้าถึงข้อมูลสำคัญ

### 4. การทดสอบอัตโนมัติ (Continuous Integration & Testing)
- [x] ผ่านชุดทดสอบ Node.js (`PublicContentReferenceTest.mjs`, `WorkflowFrontendTest.mjs`)
- [x] ผ่านชุดทดสอบ Routing (`NavigationRedirectTest.mjs`)
- [x] ผ่านชุดทดสอบความปลอดภัยฝั่ง PHP (`SecurityRegressionTest.php`)
- [x] ผ่านชุดทดสอบกลไกย้ายข้อมูล (`MigrationRunnerTest.php`)
- [x] ผ่านชุดทดสอบการคำนวณ TOTP และ QR Code

### 5. ความเข้ากันได้ของ Frontend & การคงอยู่ของ UI (UI/UX Preservation)
- [x] สีน้ำเงินสหกรณ์และฟอนต์ภาษาไทยแสดงผลคมชัดตามมาตรฐานเดิม
- [x] หน้าแดชบอร์ดสมาชิก แดชบอร์ดเจ้าหน้าที่ และเครื่องคำนวณเงินกู้แสดงผลปกติ
- [x] Responsive Layout รองรับทั้งหน้าจอ Desktop และสมาร์ตโฟน
- [x] การเรียกตรวจเซสชันในเบื้องหลังไม่ทำให้ฟอร์มที่กำลังกรอกรีเฟรชหรือสูญหาย

### 6. แผนการกู้คืนและย้อนกลับฉุกเฉิน (Rollback & Disaster Recovery)
- [x] จัดทำเอกสาร [docs/deployment/ROLLBACK_AND_RECOVERY.md](file:///d:/rycoopweb/docs/deployment/ROLLBACK_AND_RECOVERY.md)
- [x] ระบุคำสั่งย้อนกลับทั้งระดับ Source Code และการ Restore ฐานข้อมูลอย่างชัดเจน
- [x] ตระหนักถึงข้อจำกัดเรื่อง MySQL DDL ไม่รองรับ Transaction Rollback

### 7. นโยบายการส่งมอบ (Release Governance)
- [x] แยกการส่งมอบออกเป็น Release A (Dependency Remediation) และ Release B (Application Upgrade)
- [x] ไม่มีการ Push เข้า Production หรือ Merge เข้า Target Branch โดยตรงก่อนผ่านการ Review
- [x] ส่งมอบผ่านการเปิด Pull Request ไปยัง branch `Test`
