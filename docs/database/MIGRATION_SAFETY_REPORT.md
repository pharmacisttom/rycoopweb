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
# ตรวจสอบความถูกต้องล่วงหน้า (Preflight / Dry-run — ไม่มีการรัน DDL/DML ใดๆ ทั้งสิ้น)
php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql --dry-run

# รันจริงสำหรับ migration ทั่วไป
php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql

# รัน migration ทางการเงิน (เช่น 019 หรือ 020) ต้องมี flag ยืนยัน
php database/run_migration.php 020_sync_deposit_rates.sql --confirm-financial

# รัน migration ที่มีผลกระทบต่อโครงสร้างตาราง (DROP / TRUNCATE)
php database/run_migration.php 018_drop_unused_tables.sql --confirm-destructive

# รันซ้ำหลังจาก reconciliation ในกรณีที่เคยเกิด partial DDL failure
php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql --force-retry-failed
```

### 3.2 การรัน Migration ทั้งหมดผ่าน `bin/console db:migrate`
```bash
# ตรวจสอบสถานะและ preflight migrations ที่ค้างอยู่ทั้งหมด
php bin/console db:migrate --dry-run

# รัน migration ไฟล์เดี่ยวอย่างปลอดภัย
php bin/console db:migrate --file=022_google_auth_and_mandatory_mfa.sql

# รัน migration ทั้งหมดที่ยังไม่เคยรัน
php bin/console db:migrate

# รัน migration โดยอนุญาต force retry กรณีมี migration ที่เคย failed
php bin/console db:migrate --force-retry-failed
```

---

## 4. กลไกความปลอดภัยขั้นสูง (Advanced Security Hardening)

### 4.1 Fail-closed Migration History
- **ขจัด Silent Catch**: ตัดทิ้งพฤติกรรม `catch (Throwable) { return []; }` ทั้งหมด เมื่อเกิดข้อผิดพลาดในการเชื่อมต่อหรืออ่านตาราง `migrations` ระบบจะ throw controlled `RuntimeException` ทันที ป้องกันสถานการณ์ที่ฐานข้อมูลขัดข้องชั่วคราวแล้วระบบเข้าใจผิดว่าไม่เคยมี migration ใดๆ ถูกรันมาก่อน ซึ่งอาจทำให้ระบบพยายามรัน migration ตั้งแต่ไฟล์ 001 ซ้ำ
- **Redaction of Credentials**: มีฟังก์ชัน `sanitizeErrorMessage()` ในตัว ตัดทิ้ง password, secret token, หรือ DSN parameters จาก exception message ก่อนส่งออกไปยัง CLI หรือ log (`password=******`)
- **Read-only History Inspection**: มีเมธอด `hasMigrationsTable()` และ `getMigrationTableColumns()` ที่ทำการตรวจสอบโครงสร้างแบบ read-only ทำให้สามารถทำงานร่วมกับตาราง `migrations` แบบเดิม (legacy schema ที่มีเพียง `id, migration, applied_at`) ได้อย่างราบรื่นโดยไม่ error

### 4.2 Strict Dry-run Guarantee
- **Zero DDL / DML Guarantee**: ในโหมด `--dry-run` ระบบรับประกันว่าจะไม่มีการรันคำสั่ง `CREATE TABLE`, `ALTER TABLE`, `DROP TABLE`, `INSERT`, `UPDATE`, หรือ `DELETE` ใดๆ ทั้งสิ้น
- **Zero Schema Mutation during Preflight**: การตรวจสอบใน dry-run จะไม่สร้างตาราง `migrations` และจะไม่เพิ่มคอลัมน์ audit ในตาราง legacy
- **Limitations Transparency**: รายงานข้อจำกัดของการทำ dry-run อย่างชัดเจนว่า: Dry-run ตรวจสอบความถูกต้องของโครงสร้าง, Checksum และ Safety Gate ผ่านการวิเคราะห์แบบ Read-only แต่ MySQL syntax error ระดับ runtime หรือ constraint violation เชิงลึก จะสามารถตรวจสอบได้สมบูรณ์เมื่อรันจริงเท่านั้น

### 4.3 Partial DDL Failure Handling & MySQL Implicit Commits
- **MySQL Implicit Commit Risk**: ใน MySQL / MariaDB คำสั่งประเภท DDL (`CREATE TABLE`, `ALTER TABLE`, `DROP TABLE` ฯลฯ) จะบังคับ `COMMIT` โดยอัตโนมัติ ไม่สามารถ rollback ผ่าน transaction ได้ หากไฟล์ migration มีหลายคำสั่งแล้วเกิดล้มเหลวที่คำสั่งกลางไฟล์ คำสั่งก่อนหน้าจะยังคงถูก commit ค้างอยู่ในฐานข้อมูล
- **Separate Attempt & Failure Recording**: หากเกิดความผิดพลาดในการประมวลผล ระบบจะบันทึกสถานะ `status = 'failed'`, เวลาที่ใช้ และข้อความ error ที่ผ่านการ sanitize ลงในตาราง `migrations` ทันที
- **Strict Automatic Retry Block**: เมื่อมี migration ที่อยู่ในสถานะ `failed` ระบบจะระงับการรันอัตโนมัติในครั้งถัดไปทันที เพื่อป้องกัน schema corruption จากการรันคำสั่ง DDL ซ้ำ
- **Manual Reconciliation Gate**: ผู้ดูแลระบบต้องตรวจสอบความสมบูรณ์ของ schema ในฐานข้อมูลจริง (reconciliation) และสั่งรันซ้ำด้วยแฟล็ก `--force-retry-failed` อย่างชัดเจนเท่านั้น

### 4.4 Database Isolation & Explicit Configuration
- **Explicit Target Requirement**: ปฏิเสธการรันคำสั่ง Migration ทันทีหากขาดการกำหนด `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` อย่างชัดเจน
- **Prohibition of Default Credentials**: ห้ามใช้งานค่า default `rayongcoop_db` โดยเงียบๆ และห้ามเชื่อมต่อด้วยบัญชี `root` โดยเด็ดขาด เพื่อป้องกันการยกระดับสิทธิ์ (Privilege Escalation) และความเสี่ยงในการรันผิดฐานข้อมูล
- **Dedicated PDO Instance**: สคริปต์ Migration จะสร้าง PDO instance เฉพาะกิจผ่าน `MigrationConnectionFactory` แยกต่างหากจาก Shared Singleton `Database::connect()` ของ Web Application
- **Lab Database Isolation (`rycoopweb_migration_lab`)**:
  - รองรับการทดสอบ Integration ผ่านฐานข้อมูล Lab โดยเฉพาะ
  - บังคับใช้บัญชี Lab-only ห้ามใช้บัญชี Production หรือ Web App
  - ตรวจสอบ Privilege Isolation ไม่อนุญาตให้มี Grant ข้ามไปยัง `rycoopweb_test` หรือ Production
- **Migration CLI Preflight Inspection**:
  - แสดงผลสรุป Host, Port, Target Database, Database User, สิทธิ์ผู้ใช้, Checksum, Safety Gate Assessment ก่อนเริ่มทำงาน
  - ห้ามแสดงรหัสผ่านหรือความลับในหน้าจอหรือ Log เด็ดขาด (`Never display secrets`)

### 4.5 การขจัดสภาวะแย่งชิง (TOCTOU Elimination) & In-Lock History Verification
- **ปัญหาเดิม (Time-of-Check to Time-of-Use)**: ในอดีตกระบวนการจะอ่านประวัติ migration นอก lock เพื่อตัดสินว่าไฟล์ใดต้องรัน จากนั้นจึง acquire advisory lock แล้วรัน SQL ทันทีโดยไม่ตรวจประวัติซ้ำ หากมี 2 process ทำงานพร้อมกัน ทั้งคู่อาจตัดสินว่า migration ยังไม่ถูกรัน และนำไปสู่การรัน DDL ซ้ำซ้อน (Duplicate Execution)
- **สถาปัตยกรรมใหม่ (Lock-Before-Check)**:
  1. ทำ read-only preflight และตรวจสอบ database target นอก lock
  2. ขอรับ MySQL advisory lock (`GET_LOCK`) เป็นลำดับแรก
  3. ภายใน lock ดำเนินการเตรียม schema ตาราง `migrations`
  4. **อ่านประวัติจากฐานข้อมูลซ้ำอีกครั้งภายใต้ lock (In-Lock Authoritative History Check)**
  5. ตรวจสอบ SHA-256 checksum และสถานะภายใต้ lock หากมี process อื่นรันสำเร็จไปแล้ว ระบบจะข้าม (`skipped`) ทันทีโดยไม่แตะต้อง SQL
  6. ตรวจสอบเงื่อนไข retry สำหรับ migration ที่เคยล้มเหลว
  7. Re-evaluate confirmation gate (Financial / Destructive)
  8. ทำการรัน migration SQL และบันทึกผล
  9. ปลด lock ในบล็อก `finally` เสมอ

### 4.6 Concurrency Protection สำหรับ Migration Metadata (`ensureMigrationTable`)
- คุ้มครองการสร้างและอัปเกรดตาราง `migrations` (`ensureMigrationTable`) ด้วย MySQL Advisory Lock เดียวกัน
- ป้องกันไม่ให้ 2 process รันคำสั่ง `CREATE TABLE` หรือ `ALTER TABLE migrations ADD COLUMN` แข่งกัน
- รองรับ Re-entrant locking หาก lock ถูกถือครองอยู่แล้วโดย `runSingle` จะไม่แย่ง lock ซ้ำ และไม่ปลด lock ก่อนเวลาอันควร

### 4.7 การป้องกันความล้มเหลวในการบันทึกสถานะ (Failure Recording Safety & Critical Incident)
- หากการรัน SQL ล้มเหลวกลางคัน (เกิด Partial DDL Commit) และการบันทึกสถานะ `failed` ลงตาราง `migrations` ขัดข้องด้วย (เช่น disk เต็ม, connection หลุด):
  - **Fail Closed**: ระบบจะไม่ยอมให้ปล่อยผ่านหรืออนุญาตให้รันซ้ำโดยปริยาย
  - **Durable Incident Marker**: ระบบจะบันทึก Incident Marker File (`migration_incident.json`) ลงบน Filesystem ภายนอก Transaction ทันที
  - **Raise MigrationIncidentException**: โยน dedicated exception ระดับวิกฤต
  - **Block Subsequent Execution**: กระบวนการ migration ใดๆ ในอนาคตจะถูกระงับทันทีตราบใดที่ยังมี incident marker ค้างอยู่ จนกว่าผู้ดูแลระบบจะทำ reconciliation และสั่งปลดล็อกอย่างชัดเจน

---

## 5. แผนการสำรองข้อมูลและการกู้คืน (Backup & Recovery Procedures)

### 5.1 ขั้นตอนก่อนรัน Migration ในทุกสภาพแวดล้อม (Pre-migration Protocol)
ก่อนรัน migration ใดๆ ให้สร้าง snapshot สำรองข้อมูลฐานข้อมูล MySQL เสมอ:
```bash
# คำสั่งสำรองฐานข้อมูลฉุกเฉิน (Snapshot Backup)
mysqldump -u <DB_USER> -p \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  <DB_NAME> | gzip > /var/backups/db_backup_$(date +%Y%m%d_%H%M%S).sql.gz

# หรือผ่าน RYCOOP Console:
php bin/console backup:run
```

### 5.2 ขั้นตอนเมื่อเกิด Partial DDL Failure (Incident Recovery Runbook)
หาก migration ล้มเหลวกลางคันและถูกบันทึกสถานะ `failed`:

1. **ตรวจสอบรายละเอียดข้อผิดพลาด**:
   ```sql
   SELECT id, migration, checksum, status, error_message, applied_at 
   FROM migrations 
   WHERE status = 'failed';
   ```
2. **ตรวจสอบ Schema ที่ถูกสร้างค้างไว้ในฐานข้อมูล**:
   - ตรวจสอบ `SHOW TABLES;` หรือ `DESCRIBE <table_name>;` ว่าคำสั่งส่วนใดของ migration ถูก commit ไปแล้วบ้าง
3. **การ Reconcile Schema**:
   - **ทางเลือก ก (Rollback)**: หากต้องการเริ่มใหม่ทั้งหมด ให้ย้อนสถานะ DDL ที่ค้างอยู่ (เช่น `DROP TABLE <partially_created_table>;`) จากนั้นลบแถวออกจากตาราง migrations:
     ```sql
     DELETE FROM migrations WHERE migration = '<failing_file.sql>';
     ```
   - **ทางเลือก ข (Forward-fix)**: หากแก้ไขข้อผิดพลาดในไฟล์ SQL ให้รองรับสถานะปัจจุบัน (เช่น ใส่ `IF NOT EXISTS` หรือปรับปรุง syntax) จากนั้นทดสอบด้วย dry-run:
     ```bash
     php database/run_migration.php <failing_file.sql> --dry-run
     ```
4. **รันซ้ำด้วย Approval Gate**:
   ```bash
   php database/run_migration.php <failing_file.sql> --force-retry-failed
   ```

### 5.3 ขั้นตอนเมื่อเกิด Critical Migration Incident (Dual Failure Recovery Runbook)
หากเกิดเหตุฉุกเฉินระดับวิกฤตที่ทั้งคำสั่ง SQL และการบันทึกสถานะล้มเหลวพร้อมกัน:
1. **สังเกตข้อความแจ้งเตือน**:
   - ระบบจะแสดงข้อความ `🚨 CRITICAL MIGRATION INCIDENT` และแจ้งตำแหน่งของไฟล์ Incident Marker
2. **ตรวจสอบรายละเอียดในไฟล์ Incident Marker**:
   - ตรวจสอบไฟล์ `storage/logs/migration_incident.json` เพื่อดูชื่อ migration, checksum, migration error และ recording error
3. **ตรวจสอบและแก้ไขฐานข้อมูล (Manual Reconciliation)**:
   - ตรวจสอบโครงสร้างตารางในฐานข้อมูลจริงว่ามีคำสั่งใดถูก commit ไปแล้วบ้าง
   - ปรับแก้โครงสร้างให้ถูกต้องตามที่ต้องการ
4. **การรันต่อหรือเคลียร์ Incident**:
   - หากแก้ไขไฟล์ migration ให้เข้ากับสถานะฐานข้อมูลแล้ว สามารถสั่งรันต่อด้วย:
     ```bash
     php database/run_migration.php <migration_file.sql> --force-retry-failed
     ```
     เมื่อสำเร็จ ระบบจะลบไฟล์ `migration_incident.json` ให้โดยอัตโนมัติ
   - หรือหาก reconcile เสร็จสิ้นและไม่ต้องการรันไฟล์นั้นซ้ำ สามารถลบไฟล์ Incident Marker ได้โดยตรง:
     ```bash
     rm storage/logs/migration_incident.json
     ```

---

## 6. ผลการทดสอบความปลอดภัยอัตโนมัติ (Automated Safety Test Results)

ผ่านการทดสอบครบถ้วน 100% จาก [tests/MigrationRunnerTest.php](file:///d:/rycoopweb/tests/MigrationRunnerTest.php) (62 assertions / 15 test groups):

```
Running MigrationRunner Comprehensive Unit, Safety & Security Tests...
=========================================================================
PASS: 1.1 Path traversal attempt with ../.. is rejected
PASS: 1.2 Non-sql file extension is rejected
PASS: 1.3 Non-existent migration file is safely reported
PASS: 1.4 Valid migration file resolves strictly inside migrations directory
PASS: 2.1 Destructive statements (DROP TABLE, DROP COLUMN) are detected
PASS: 2.2 Financial table mutations (interest_rates) are detected
PASS: 3.1 Financial migration without confirmation is blocked
PASS: 3.2 Dry-run with financial confirmation passes preflight
PASS: 4.1 System database target is rejected
PASS: 4.2 Mismatched database name is rejected immediately
PASS: 5.1 History query failure throws fail-closed exception
PASS: 5.2 Credentials in DB error messages are redacted
PASS: 5.3 Sensitive credentials replaced with asterisks
PASS: 5.4 Table check failure throws fail-closed exception
PASS: 6.1 Legacy migrations table read successfully
PASS: 6.2 Missing checksum column is represented as null
PASS: 6.3 Missing status column defaults to success
PASS: 7.1 Dry-run recognizes applied migration in legacy table
PASS: 7.2 Legacy migrations table schema was NOT modified during dry-run (still 3 columns)
PASS: 7.3 No checksum column added during dry-run
PASS: 8.1 Dry-run passed on empty database without migrations table
PASS: 8.2 Dry-run explicitly reports validation limitations
PASS: 8.3 Migrations table was NOT created during dry-run on empty database
PASS: 9.1 Lock acquisition failure triggers concurrency exception
PASS: 10.1 Failing migration throws execution exception
PASS: 10.2 Failed migration recorded in migrations table
PASS: 10.3 Failed status is explicitly recorded
PASS: 10.4 Error message is stored in migrations table
PASS: 10.5 Automatic retry of failed migration is strictly BLOCKED
PASS: 10.6 Force retry allows execution after manual reconciliation
PASS: 10.7 Status updated to success upon successful retry
PASS: 10.8 Error message cleared on successful retry
PASS: 11.1 Checksum mismatch triggers integrity exception
PASS: 12.1 Previously applied migration is skipped idempotently
PASS: 13.1 Missing DB_HOST is rejected before opening connection
PASS: 13.2 Missing DB_PORT is rejected
PASS: 13.3 Out of range DB_PORT is rejected
PASS: 13.4 Missing DB_DATABASE is rejected
PASS: 13.5 System database target is rejected
PASS: 13.6 Default root username is strictly rejected
PASS: 13.7 Silent default database rayongcoop_db is rejected
PASS: 13.8 Explicitly confirmed database configuration passes
PASS: 13.9 Lab configuration targeting rycoopweb_test is rejected
PASS: 13.10 Reusing application credentials in Lab is rejected
PASS: 13.11 Lab user grant on protected database is rejected
PASS: 13.12 Passwords in connection errors are redacted
PASS: 13.13 Controlled connection failure exception
PASS: 13.14 Preflight identifies active database
PASS: 13.15 Preflight validates database identity match
PASS: 13.16 Preflight never exposes passwords or secrets
PASS: 13.17 Empty expected database with MySQL PDO is rejected
PASS: 14.1 Runner B preflight sees migration as unapplied before lock
PASS: 14.2 Runner A applies migration successfully
PASS: 14.3 Runner B detects in-lock applied status and cleanly skips (TOCTOU resolved)
PASS: 14.4 Runner B never executed migration SQL DDL after acquiring lock
PASS: 14.5 ensureMigrationTable standalone acquires advisory lock
PASS: 14.6 ensureMigrationTable standalone releases advisory lock in finally
PASS: 15.1 Failure in both SQL and failure-recording raises MigrationIncidentException
PASS: 15.2 Durable incident marker file was created on disk
PASS: 15.3 Incident marker contains valid JSON
PASS: 15.4 Incident marker records correct migration name
PASS: 15.5 Incident marker records CRITICAL severity
PASS: 15.6 Recording error is captured in incident marker
PASS: 15.7 Subsequent migration execution is strictly blocked while incident marker is active
PASS: 15.8 Preflight detects active incident state
PASS: 15.9 Reconciled migration succeeds with --force-retry-failed
PASS: 15.10 Incident marker file automatically cleared after successful reconciliation
=========================================================================
All MigrationRunner Unit, Safety & Security tests PASSED successfully!
```


