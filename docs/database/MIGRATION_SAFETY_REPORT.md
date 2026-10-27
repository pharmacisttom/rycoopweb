# รายงานความปลอดภัยของระบบประมวลผลฐานข้อมูลและการย้ายข้อมูล (Database Migration Safety Report)

**ระบบ**: RYCOOPWEB 2.3  
**วันที่จัดทำ**: 2026-10-27  
**ผู้จัดทำ**: Principal Software Engineer & MySQL Database Security Architect  
**สถานะการยกระดับ**: Phase 1 Completed & Verified  

---

## 1. บทนำและปัญหาเดิมที่พบ (Background & Observed Issues)

ในการตรวจสอบโครงสร้างเดิมของระบบ Migration พบจุดเสี่ยงระดับวิกฤตหลายประการ:
1. **ขาดการตรวจสอบเป้าหมายฐานข้อมูล (Database Targeting)**: สคริปต์ `database/run_migration.php` เดิมไม่เคยตรวจสอบว่ากำลังเชื่อมต่อกับฐานข้อมูลใด (`SELECT DATABASE()`) เสี่ยงต่อการรันสคริปต์ผิดสภาพแวดล้อม (เช่น รันสคริปต์ TEST ใส่ Production)
2. **ความเสี่ยง Path Traversal**: พารามิเตอร์ชื่อไฟล์สามารถรับพาธใดๆ เข้ามาประมวลผลได้โดยตรง ขาดการตีกรอบให้อยู่เฉพาะไดเรกทอรี `database/migrations/*.sql`
3. **ขาดการตรวจสอบประวัติและ Checksum**: ไม่มีการตรวจจับว่าไฟล์ migration เดิมถูกแก้ไขเนื้อหาหลังจากรันไปแล้วหรือไม่
4. **ขาดกลไก Concurrency Locking**: หากมีการสั่ง migrate พร้อมกันจาก CI หรือ CLI สองตัว อาจเกิด race condition ใน DDL statement
5. **ไม่มีโหมด Preflight / Dry-run**: ไม่สามารถตรวจสอบความถูกต้องของสคริปต์ก่อนลงมือทำจริงได้
6. **ขาดการกักกันคำสั่งเสี่ยงและข้อมูลทางการเงิน**: คำสั่งที่มีผลกระทบต่อตารางดอกเบี้ยหรือคำสั่งประเภท `DROP` / `TRUNCATE` สามารถรันได้โดยไม่ต้องมีการยืนยันอย่างชัดเจน

---

## 2. สถาปัตยกรรมระบบ Migration ที่ได้รับการยกระดับ (Hardened Architecture)

ทีมวิศวกรรมได้พัฒนาคลาส [App\Services\MigrationRunner](file:///d:/rycoopweb/app/Services/MigrationRunner.php) ขึ้นมาทำหน้าที่เป็นแกนกลางในการประมวลผล migration ทั้งหมด โดยมีเกราะป้องกันความปลอดภัยดังนี้:

```
[CLI / Console / Script]
         │
         ▼
[1. Path Traversal & Format Guard] ────► ตรวจสอบให้อยู่ใน database/migrations/*.sql เท่านั้น
         │
         ▼
[2. Target Database Verification] ─────► เทียบ SELECT DATABASE() กับ config ห้ามรันบน system dbs
         │
         ▼
[3. Concurrency Lock Acquisition] ─────► MySQL GET_LOCK('rycoopweb_migration_lock', 10s)
         │
         ▼
[4. SQL Safety Analysis] ──────────────► ตรวจจับ DROP/TRUNCATE (Destructive) & interest_rates (Financial)
         │
         ▼
[5. Checksum Verification] ────────────► คำนวณ SHA-256 เทียบกับประวัติที่เคยบันทึก
         │
         ▼
[6. Execution & Auditing] ─────────────► บันทึก execution_time_ms, checksum, status ลงตาราง migrations
         │
         ▼
[7. Concurrency Lock Release] ─────────► MySQL RELEASE_LOCK() ใน finally block เสมอ
```

### รายละเอียดเกราะป้องกันสำคัญ
- **Strict Allowlisted Directory**: ปฏิเสธชื่อไฟล์ที่มี `..`, สแลช, หรือนามสกุลที่ไม่ใช่ `.sql` บังคับรูปแบบ `^[0-9]{3}[a-z0-9_]*\.sql$`
- **Database Targeting Verification**: บังคับเปรียบเทียบ active database กับ intended database และปฏิเสธทันทีหากเป็น system database เช่น `mysql`, `information_schema`, `sys`
- **Compatibility with Existing History**: ตาราง `migrations` ยังคงคอลัมน์เดิม `(id, migration, applied_at)` ไว้อย่างครบถ้วน และเพิ่มคอลัมน์ audit แบบ additive ได้แก่ `checksum CHAR(64)`, `execution_time_ms INT`, และ `status ENUM('success', 'failed')` โดยไม่ลบหรือเขียนทับประวัติเดิม
- **Financial & Destructive Confirmation Gate**:
  - เมื่อพบคำสั่ง `DROP TABLE`, `TRUNCATE`, หรือ `ALTER TABLE ... DROP COLUMN` สคริปต์จะระงับการทำงานทันที เว้นแต่จะระบุแฟล็ก `--confirm-destructive`
  - เมื่อพบคำสั่งแก้ไขข้อมูลในตารางการเงิน (`interest_rates`, `deposit_products`, `loan_products`, `member_dividends`) จะระงับการทำงานทันที เว้นแต่จะระบุแฟล็ก `--confirm-financial`
- **Dry-run Mode (`--dry-run`)**: รองรับการตรวจสอบไวยากรณ์ การคำนวณ Checksum และจำลองการประเมินความปลอดภัยโดยไม่มีการเปลี่ยนแปลงข้อมูลจริง

---

## 3. คู่มือการใช้งาน (CLI Operations Runbook)

### 3.1 การรัน Migration ไฟล์เดี่ยวผ่าน `database/run_migration.php`
```bash
# ตรวจสอบความถูกต้องล่วงหน้า (Preflight / Dry-run)
php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql --dry-run

# รันจริงสำหรับ migration ทั่วไป
php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql

# รัน migration ทางการเงิน (เช่น 019 หรือ 020) ต้องมี flag ยืนยัน
php database/run_migration.php 020_sync_deposit_rates.sql --confirm-financial
```

### 3.2 การรัน Migration ทั้งหมดผ่าน `bin/console db:migrate`
```bash
# ตรวจสอบสถานะและ preflight migrations ที่ค้างอยู่ทั้งหมด
php bin/console db:migrate --dry-run

# รัน migration ไฟล์เดี่ยวอย่างปลอดภัย
php bin/console db:migrate --file=022_google_auth_and_mandatory_mfa.sql

# รัน migration ทั้งหมดที่ยังไม่เคยรัน
php bin/console db:migrate
```

---

## 4. ผลการทดสอบความปลอดภัยอัตโนมัติ (Automated Safety Test Results)

ผ่านการทดสอบครบถ้วน 100% จาก [tests/MigrationRunnerTest.php](file:///d:/rycoopweb/tests/MigrationRunnerTest.php):
- `PASS: Path traversal attempt with ../.. is rejected`
- `PASS: Non-sql file extension is rejected`
- `PASS: Non-existent migration file is safely reported`
- `PASS: Valid migration file resolves strictly inside migrations directory`
- `PASS: Destructive statements (DROP TABLE, DROP COLUMN) are detected`
- `PASS: Financial table mutations (interest_rates) are detected`
- `PASS: Financial migration without confirmation is blocked`
- `PASS: Dry-run with financial confirmation passes preflight`
- `PASS: System database target is rejected`
- `PASS: Mismatched database name is rejected immediately`
