# รายงานการวิเคราะห์ผลกระทบการย้ายข้อมูลทางการเงิน (Financial Migration Review 017–023)

**ระบบ**: RYCOOPWEB 2.3
**วันที่จัดทำ**: 2026-10-27
**ขอบเขต**: ตรวจสอบผลกระทบรายไฟล์สำหรับ Migration 017 ถึง 023 โดยเน้นมาตรการควบคุมความเสี่ยงสำหรับ Migration 019 และ 020
**ผู้จัดทำ**: Application Security Engineer & Financial Data Integrity Reviewer

---

## 1. ตารางสรุปภาพรวมการทบทวน Migration 017–023

| ไฟล์ Migration | ประเภท | ระดับความเสี่ยง | วัตถุประสงค์และการเปลี่ยนแปลงโครงสร้าง | สถานะความปลอดภัย |
| :--- | :--- | :--- | :--- | :--- |
| **017**_access_management_permissions.sql | Schema / Data | ต่ำ (Low) | เพิ่มสิทธิ์โมดูล news, faqs, members, dividends และผูกกับบทบาท `member_admin` | ปลอดภัย (ใช้ `ON DUPLICATE KEY UPDATE` / `INSERT IGNORE`) |
| **018**_member_profile_requests.sql | Schema DDL | ต่ำ (Low) | สร้างตาราง `member_profile_requests` สำหรับคำขอแก้ไขข้อมูลส่วนตัวสมาชิก | ปลอดภัย (Additive `CREATE TABLE IF NOT EXISTS`) |
| **019**_cleanup_legacy_interest_rates.sql | Financial Data | **สูงมาก (Critical)** | ปิดการใช้งาน (deactivate) รายการอัตราดอกเบี้ยเงินกู้ซ้ำซ้อน | **ต้องตรวจสอบ**: ใช้ `id IN (4, 6)` แบบ Hardcoded |
| **020**_sync_deposit_rates.sql | Financial Data | **สูงมาก (Critical)** | ปรับอัตราดอกเบี้ยเงินฝากออมทรัพย์พิเศษ (1.75%) และเงินฝากออมทรัพย์ (1.50%) | **ต้องตรวจสอบ**: กำหนด `effective_date = '2026-01-01'` และ `created_by = 1` |
| **021**_create_visitor_counter_tables.sql | Schema DDL | ต่ำ (Low) | สร้างตาราง `website_visits` และ `visitor_counters` พร้อมมาตรการ PDPA IP Masking | ปลอดภัย (Additive `CREATE TABLE IF NOT EXISTS`) |
| **022**_google_auth_and_mandatory_mfa.sql | Schema DDL | ปานกลาง (Medium) | สร้างตาราง Google identities, staff invitations, recovery codes และ ALTER users/login_logs | ปลอดภัยสูง (ใช้ Dynamic `information_schema` checks) |
| **023**_public_content_revisions.sql | Schema DDL | ต่ำ (Low) | สร้างตารางร่างและการอนุมัติข้อมูลพื้นฐาน `public_content_revisions` | ปลอดภัย (Additive `CREATE TABLE IF NOT EXISTS`) |

---

## 2. การวิเคราะห์เชิงลึก: Migration 019 (Cleanup Legacy Interest Rates)

### 2.1 วัตถุประสงค์
เพื่อปิดการใช้งานรายการอัตราดอกเบี้ยเงินกู้เดิมที่ซ้ำซ้อนในระบบ เพื่อให้รายการดอกเบี้ยเงินกู้ที่แสดงบนหน้าเว็บตรงกับ 10 รายการประกาศหลักของสหกรณ์

### 2.2 โค้ด SQL ปัจจุบัน
```sql
UPDATE interest_rates
SET status = 'inactive'
WHERE id IN (4, 6)
  AND product_name IN ('เงินกู้เพื่อเหตุฉุกเฉิน', 'เงินกู้พิเศษเพื่อเคหะสงเคราะห์');
```

### 2.3 ข้อสังเกตและข้อบกพร่องที่พบ (Critical Findings)
1. **การผูกกับ Auto-Increment ID แบบ Hardcoded (`WHERE id IN (4, 6)`)**:
   - ในฐานข้อมูลแต่ละ instance (Development, Test, Production) ค่า auto-increment ID ของระเบียนอาจไม่ตรงกัน
   - หากใน Production หรือฐานข้อมูลที่ restore มา ID 4 หรือ 6 กลายเป็นผลิตภัณฑ์อื่น การรันคำสั่งนี้อาจส่งผลให้ปิดสถานะผิดรายการ
2. **ข้อเสนอแนะในการปรับปรุง (Recommendation)**:
   - ควรอ้างอิงด้วยเงื่อนไขทางธุรกิจ เช่น `product_type = 'loan' AND product_name = '...'` หรือจับคู่กับ version/slug แทนการพึ่งพาตัวเลข ID เพียงอย่างเดียว

---

## 3. การวิเคราะห์เชิงลึก: Migration 020 (Sync Deposit Rates)

### 3.1 วัตถุประสงค์
ปรับปรุงอัตราดอกเบี้ยเงินฝากออมทรัพย์พิเศษ และเงินฝากออมทรัพย์ทั่วไป ให้ตรงกับข้อมูลที่เผยแพร่บนเว็บไซต์เดิมของสหกรณ์

### 3.2 ตารางเปรียบเทียบข้อมูลก่อนและหลัง (Before vs. After Comparison)

| รายการผลิตภัณฑ์ (Product) | ค่าเดิมในฐานข้อมูล (Before) | ค่าที่ต้องการปรับปรุง (Intended Value) | แหล่งอ้างอิงทางการ (Approved Source) |
| :--- | :--- | :--- | :--- |
| **เงินฝากออมทรัพย์พิเศษ** (`special-savings`) | Rate: `1.50%` | **`1.750%`** (ขั้นต่ำ 1,000 บาท) | เว็บไซต์สหกรณ์เดิม (`pid=5`) ตรวจสอบ ณ 21 ต.ค. 2569 |
| **เงินฝากออมทรัพย์ทั่วไป** (`savings-account`) | *(ไม่มีใน interest_rates)* | **`1.500%`** (ขั้นต่ำ 100 บาท) | เว็บไซต์สหกรณ์เดิม (`pid=5`) ตรวจสอบ ณ 21 ต.ค. 2569 |

### 3.3 ข้อสังเกตและข้อบกพร่องที่พบ (Critical Findings)
1. **การสมมติวันที่มีผลบังคับใช้ (`effective_date = '2026-01-01'`)**:
   - จากเอกสารอ้างอิง `src/data/legacyReference.json` ระบุชัดเจนว่า: `"effectiveDate": null, "note": "ข้อมูลตามหน้าเว็บเดิม ซึ่งไม่ระบุวันที่มีผลบังคับใช้"`
   - การใส่ `'2026-01-01'` เป็นการประดิษฐ์วันที่ขึ้นมาเอง (Invented effective date) ซึ่งอาจขัดแย้งกับข้อเท็จจริงทางกฎหมายของสหกรณ์
2. **การ Hardcode ผู้สร้าง (`created_by = 1`)**:
   - หากในฐานข้อมูลเป้าหมายไม่มีผู้ใช้ `id = 1` หรือผู้ใช้หมายเลข 1 ถูกลบ/เปลี่ยนบทบาท จะเกิดข้อผิดพลาดด้าน Foreign Key Constraint
3. **ข้อเสนอแนะในการปรับปรุง (Recommendation)**:
   - สำหรับวันที่มีผลบังคับใช้ ควรปล่อยเป็น `NULL` หรือใช้วันที่ตามประกาศจริงที่ได้รับการรับรองจากคณะกรรมการสหกรณ์
   - สำหรับ `created_by` ควรดึงจากผู้ดูแลระบบที่มีบทบาท `super_admin` คนแรกในตาราง `users` แบบ dynamic query (`(SELECT id FROM users ORDER BY id ASC LIMIT 1)`)

---

## 4. มาตรการควบคุมความปลอดภัยของข้อมูลทางการเงิน (Financial Data Protection Protocol)

1. **ห้ามรัน Migration ข้อมูลการเงินอัตโนมัติ**: ระบบ [MigrationRunner](file:///d:/rycoopweb/app/Services/MigrationRunner.php) ได้ติดตั้งกลไกตรวจจับการแก้ไขตาราง `interest_rates`, `deposit_products`, `loan_products`, `member_dividends` หากตรวจพบจะต้องมีแฟล็ก `--confirm-financial` เท่านั้น
2. **การบันทึก Audit Trail ทุกครั้ง**: การปรับอัตราดอกเบี้ยและเงื่อนไขทางการเงินจะต้องถูกบันทึกลงในระบบร่าง `public_content_revisions` เพื่อให้เจ้าหน้าที่ตรวจสอบและกด "เผยแพร่ (Publish)" โดยมีผู้ลงนามอนุมัติเสมอ
3. **การทดสอบบน Disposable Database**: การทดสอบ migration ทางการเงินทุกครั้ง ต้องกระทำบนฐานข้อมูลจำลอง (Isolated Sandbox) และตรวจทานยอดคำนวณดอกเบี้ยก่อนและหลังการ migrate ทุกครั้ง
