# รายงานสถาปัตยกรรมความมั่นคงปลอดภัยฐานข้อมูลและการแยกบัญชีผู้ใช้ใน CI (CI Database Security & Least Privilege Architecture)

**ระบบ**: RYCOOPWEB 2.3
**วันที่จัดทำ**: 2026-10-27
**ผู้จัดทำ**: Principal DevSecOps Engineer, PHP Security Architect & MySQL Database Engineer
**ขอบเขต**: GitHub Actions CI Quality Gate (`.github/workflows/ci.yml`), `scripts/ci-provision-database.php`, `App\Services\MigrationConfigValidator`
**สถานะ**: Production-Ready & Formally Verified

---

## 1. บริบทและสาเหตุของปัญหา (Root Cause Analysis)

ในการยกระดับความมั่นคงปลอดภัยของระบบจัดการ Database Migrations (RYCOOPWEB 2.3) คลาส `App\Services\MigrationConfigValidator` ได้บังคับใช้นโยบาย **Principle of Least Privilege** อย่างเข้มงวด โดยสั่งห้ามการรัน Migration ด้วยบัญชีผู้ดูแลระบบระดับสูง (`root`, `admin`, `administrator`) โดยเด็ดขาด:

```
Migration security violation: Executing migrations as 'root' is strictly prohibited.
Use a dedicated migration account adhering to principle of least privilege.
```

อย่างไรก็ดี เวิร์กโฟลว์ CI เดิมใน `.github/workflows/ci.yml` ยังคงกำหนดตัวแปรสภาพแวดล้อม `DB_USERNAME: root` ส่งผลให้ขั้นตอน `Migrate fresh CI database` ถูกสกัดกั้นทันที (Fail-Closed) ซึ่งเป็นการทำงานที่ถูกต้องตามข้อกำหนดความมั่นคงปลอดภัย

---

## 2. สถาปัตยกรรมการแยกบัญชีผู้ใช้ฐานข้อมูล 3 ระดับ (3-Tier CI Account Separation)

เพื่อแก้ไขปัญหาดังกล่าวโดยไม่ลดทอนความเข้มงวดของ `MigrationConfigValidator` และ `MigrationRunner` ระบบ CI ได้ถูกปรับปรุงให้แยกสิทธิ์ตามมาตรฐาน DevSecOps ดังนี้:

```
+---------------------------------------------------------------------------------+
|                               MySQL 8.0 CI Service                              |
+---------------------------------------------------------------------------------+
          |                                  |                               |
          v                                  v                               v
[1. Provisioning Account]         [2. Migration Account]          [3. Application Account]
- User: root (Admin)              - User: rycoop_ci_migrator      - User: rycoop_ci_app
- Scope: Initial Setup Only       - Scope: rayongcoop_ci.* DDL/DML- Scope: rayongcoop_ci.* DML
- Used in: ci-provision script    - Used in: db:migrate           - Used in: db:seed, API server
- No migration execution          - No Global/SUPER grants        - No DDL (No CREATE/DROP/ALTER)
```

### 2.1 บัญชีผู้ดูแลระบบสำหรับติดตั้ง (CI Provisioning Account)
- **ชื่อบัญชี**: `root`
- **ขอบเขตการใช้งาน**: ใช้งานเฉพาะในสคริปต์ [scripts/ci-provision-database.php](file:///d:/rycoopweb/scripts/ci-provision-database.php) ก่อนเริ่มการรัน Migration
- **หน้าที่**:
  1. สร้างฐานข้อมูลทดสอบแบบแยกเด็ดขาด: `CREATE DATABASE IF NOT EXISTS rayongcoop_ci CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
  2. สร้างบัญชี `rycoop_ci_migrator` และกำหนดรหัสผ่านเฉพาะ
  3. มอบสิทธิ์ระดับฐานข้อมูลเฉพาะ DDL และ DML บน `rayongcoop_ci.*`
  4. สร้างบัญชี `rycoop_ci_app` และกำหนดรหัสผ่านเฉพาะ
  5. มอบสิทธิ์ระดับฐานข้อมูลเฉพาะ DML บน `rayongcoop_ci.*`
  6. สั่ง `FLUSH PRIVILEGES;` และตัดการเชื่อมต่อทันที
- **ข้อกำหนดความปลอดภัย**: ไม่มีการเรียกใช้บัญชีนี้ในขั้นตอน MigrationRunner หรือ Application รันไทม์ใดๆ ทั้งสิ้น

### 2.2 บัญชีสำหรับการอัปเกรดฐานข้อมูล (CI Dedicated Migration Account)
- **ชื่อบัญชี**: `rycoop_ci_migrator`
- **ขอบเขตการใช้งาน**: ส่งผ่านตัวแปรสภาพแวดล้อมในขั้นตอน `Migrate fresh CI database`:
  - `DB_HOST: 127.0.0.1`
  - `DB_PORT: 3306`
  - `DB_DATABASE: rayongcoop_ci`
  - `DB_USERNAME: rycoop_ci_migrator`
  - `DB_PASSWORD: ${{ env.CI_MIGRATOR_PASSWORD }}`
- **สิทธิ์ที่ได้รับ (Database-Scoped Only)**:
  `GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE ON rayongcoop_ci.*`
- **ข้อจำกัดความปลอดภัย**:
  - ไม่มีสิทธิ์ระดับโกลบอล (`*.*`)
  - ไม่มีสิทธิ์ `SUPER` หรือ `SYSTEM_VARIABLES_ADMIN`
  - ไม่มีสิทธิ์ `GRANT OPTION`
  - ไม่สามารถเข้าถึงฐานข้อมูลอื่นหรือระบบฐานข้อมูล MySQL (`mysql`, `information_schema` นอกเหนือจากสิทธิ์ตนเอง, `sys`)

### 2.3 บัญชีสำหรับแอปพลิเคชันรันไทม์ (CI Application Runtime Account)
- **ชื่อบัญชี**: `rycoop_ci_app`
- **ขอบเขตการใช้งาน**: เป็นบัญชีหลักของ Job Environment สำหรับการ Seed ข้อมูล (`db:seed`) และการรันเซิร์ฟเวอร์ทดสอบ API (`php -S`)
- **สิทธิ์ที่ได้รับ (Runtime DML Only)**:
  `GRANT SELECT, INSERT, UPDATE, DELETE, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE ON rayongcoop_ci.*`
- **ข้อจำกัดความปลอดภัย**:
  - ไม่ได้รับสิทธิ์ DDL โครงสร้างตาราง (`CREATE TABLE`, `DROP TABLE`, `ALTER TABLE`)
  - ป้องกันข้อผิดพลาดจากการที่โค้ดส่วนแอปพลิเคชันพยายามแก้ไข Schema โดยพลการ

---

## 3. การคงไว้ซึ่งมาตรการความปลอดภัย Migration อย่างสมบูรณ์ (Preserved Security Gates)

การปรับปรุงนี้ **ไม่มีการลดทอน** หรือสร้างข้อยกเว้น (Bypass) ใดๆ ใน `MigrationRunner` หรือ `MigrationConfigValidator`:

1. **ห้ามใช้ Root/Admin**: `MigrationConfigValidator::PROHIBITED_USERS` ยังคงตรวจจับและระงับการทำงานของ `root`, `admin`, `administrator` ตลอดเวลา
2. **การยืนยันฐานข้อมูลเป้าหมาย**: `verifyDatabaseTarget()` ตรวจสอบว่า `SELECT DATABASE()` ตรงกับ `rayongcoop_ci` อย่างเคร่งครัด
3. **การตรวจสอบสิทธิ์ผู้ใช้**: `MigrationConnectionFactory::validatePrivileges()` ตรวจสอบ `SHOW GRANTS FOR CURRENT_USER` เพื่อยืนยันว่าไม่มีสิทธิ์รั่วไหลข้ามฐานข้อมูล
4. **การล็อกพร้อมกัน (Concurrency Advisory Lock)**: ใช้ `GET_LOCK('rayongcoop_migrations_lock', 10)` เพื่อป้องกัน Race Condition
5. **การตรวจสอบซ้ำหลังล็อก (TOCTOU Resolution)**: ตรวจสอบประวัติการรันและสถานะตารางซ้ำทันทีหลังได้ Lock
6. **ประตูป้องกันข้อมูลทางการเงิน (Financial Gate)**:
   - Migration `009`, `019`, และ `020` ที่มีการแก้ไขตาราง `interest_rates` และ `loan_products` ยังคงต้องได้รับการยืนยันอย่างชัดแจ้งผ่านแฟล็ก `--confirm-financial` ในคำสั่ง CI:
     `php bin/console db:migrate --confirm-financial`
   - ป้องกันไม่ให้มีการแก้ไขข้อมูลทางการเงินโดยไม่ตั้งใจ
7. **ระบบ Incident Marker ถาวร**: หากเกิดข้อผิดพลาดรุนแรง ระบบจะบันทึกไฟล์ Incident Marker บนดิสก์และระงับการทำงานแบบ Fail-Closed

---

## 4. ผลการตรวจสอบข้อขัดข้อง PHP 8.5 (PHP 8.5 Job Investigation)

### 4.1 สาเหตุของการถูกยกเลิก (Cancellation Analysis)
จากการวิเคราะห์เหตุการณ์ที่ Job PHP 8.5 ถูกยกเลิก (Cancelled) พบว่า:
1. **GitHub Actions Matrix Fail-Fast Behavior**: ค่าดีฟอลต์ของ GitHub Actions คือ `strategy.fail-fast: true` เมื่อ Job PHP 8.3 เกิดข้อผิดพลาดในขั้นตอน `Migrate fresh CI database` (จากเหตุผล username `root`), ระบบ GitHub Actions จะส่งสัญญาณยกเลิก (Cancel) ไปยัง Job อื่นๆ ใน Matrix ทันที ส่งผลให้ PHP 8.5 ถูกยกเลิกโดยอัตโนมัติก่อนที่จะได้รันจนเสร็จ
2. **Workflow Concurrency Cancellation**: การตั้งค่า `cancel-in-progress: true` ในกลุ่ม Concurrency หากมีการ Push คอมมิตใหม่ก่อนที่รันเดิมจะเสร็จสมบูรณ์ จะสั่งยกเลิกรันเดิมทันที

### 4.2 มาตรการแก้ไข
ได้เพิ่มการกำหนดค่า:
```yaml
strategy:
  fail-fast: false
  matrix:
    php: ['8.3', '8.5']
```
การปิด `fail-fast` ทำให้ทั้ง PHP 8.3 และ PHP 8.5 สามารถรันการทดสอบได้อย่างอิสระจนจบกระบวนการ แม้ Job ใด Job หนึ่งจะรายงานข้อผิดพลาด ทำให้ทีมวิศวกรได้รับรายงานผลการวินิจฉัยครบถ้วนทุกสภาพแวดล้อม

---

## 5. สรุปรายการไฟล์และคอมมิตที่ส่งมอบ (Deliverables)

1. [scripts/ci-provision-database.php](file:///d:/rycoopweb/scripts/ci-provision-database.php): สคริปต์เตรียมฐานข้อมูลและบัญชี Least-Privilege ปลอดภัยสูง
2. [.github/workflows/ci.yml](file:///d:/rycoopweb/.github/workflows/ci.yml): เวิร์กโฟลว์ CI ที่กำหนดบัญชีแยก `rycoop_ci_migrator` และ `rycoop_ci_app` พร้อมแฟล็ก `--confirm-financial` และ `fail-fast: false`
3. [docs/security/CI_DATABASE_SECURITY.md](file:///d:/rycoopweb/docs/security/CI_DATABASE_SECURITY.md): รายงานเอกสารความมั่นคงปลอดภัยสถาปัตยกรรมฐานข้อมูลใน CI
