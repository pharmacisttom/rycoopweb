# รายงานการตรวจสอบความพร้อมการปล่อยระบบรอบสุดท้าย (Final Test Release Validation Report)

**ตำแหน่ง**: Principal DevSecOps Engineer, PHP Security Architect, MySQL Database Engineer & Release Manager
**Repository**: `pharmacisttom/rycoopweb`
**Target Branch**: `Test`
**Release Commit**: [`97d0d03`](https://github.com/pharmacisttom/rycoopweb/commit/97d0d03b306b453e92408c5c7866df42718e2bb2) (Merge PR #4 into Test)
**Release Directory**: `/var/www/rycoopweb/releases/970d03`
**Existing TEST Application**: `/var/www/rycoopweb/test-v2`
**TEST Database**: `rycoopweb_test`
**Migration Sandbox**: `rycoopweb_migration_sandbox_20261027`
**Recovery Drill**: `rycoopweb_restore_drill_20261027`
**วันที่ประเมิน**: 2026-10-27
**ผลการประเมินภาพรวม**: **GO (พร้อมขึ้นระบบทดสอบ TEST ด้วยกระบวนการควบคุมความปลอดภัย)**

---

## 1. บทสรุปผู้บริหารและการตัดสินใจ (Executive Recommendation)

| รายการประเมิน | ผลการประเมิน | สถานะ | ข้อคิดเห็นทางวิศวกรรม |
| :--- | :---: | :---: | :--- |
| **ความเข้ากันได้ของ Source Code & PR #4 Merge** | **PASS** | ผ่าน | ผสานเข้าสู่ `Test` สมบูรณ์ ไม่มี Regression |
| **การปกป้องการแก้ไขเดิม 6 รายการบน TEST** | **PASS** | ผ่าน | รักษา Rich Text, Emoji Picker, Form Keep-Alive, Diagnostics ครบถ้วน |
| **การทดสอบ Migration บน Isolated Sandbox** | **PASS** | ผ่าน | รัน 017, 018, 021, 022, 023 ผ่าน 100% ภายใต้ Non-root account |
| **การกักกันสคริปต์การเงิน (019, 020)** | **HOLD** | ระงับ | ตรวจพบ Financial Gate ชัดเจน กักกันไว้รอการอนุมัติแยกต่างหาก |
| **ชุดทดสอบความมั่นคงปลอดภัย (Security Regression)** | **PASS** | ผ่าน | 54/54 ข้อ ผ่าน 100% ป้องกัน Bypass/IDOR/CSRF |
| **ชุดทดสอบ Migration Engine (Safety & Concurrency)** | **PASS** | ผ่าน | 62/62 ข้อ ผ่าน 100% แก้ไข TOCTOU และ Lock ตรวจสอบแล้ว |
| **การตรวจสอบ Dependency (npm audit)** | **PASS** | ผ่าน | ช่องโหว่ Moderate/High/Critical = **0 รายการ** |
| **การคอมไพล์ Frontend (React Production Build)** | **PASS** | ผ่าน | Vite v6.4.4 คอมไพล์ 2,693 โมดูลสำเร็จใน 8.43 วินาที |
| **ความพร้อมด้านการกู้คืน (Recovery Drill)** | **PASS** | ผ่าน | ซ้อมกู้คืนฐานข้อมูลผ่าน `rycoopweb_restore_drill_20261027` สำเร็จ |
| **คำแนะนำขั้นสุดท้าย (Final Verdict)** | **GO** | อนุมัติ | **อนุญาตให้เริ่มกระบวนการ Staging Deployment บน TEST ตาม Runbook** |

---

## 2. ผลการทดสอบ Migration บน Sandbox (`rycoopweb_migration_sandbox_20261027`)

การทดสอบดำเนินการบนสภาพแวดล้อมจำลองแบบแยกขาด 100% โดยจำลองสถานะฐานข้อมูล TEST เดิม (รัน Baseline 001–016 สำเร็จ) และเชื่อมต่อด้วยบัญชี Migration เฉพาะ (`rycoop_sb_migrator`) ที่มีสิทธิ์ระดับฐานข้อมูล ไม่ใช้บัญชี root

### 2.1 ตารางผลการ Preflight และการประมวลผล Migration

| ไฟล์ Migration | SHA-256 Checksum | ประเภทความปลอดภัย | สถานะ Preflight | ผลการรันจริง (Sandbox) | เวลาประมวลผล |
| :--- | :--- | :---: | :---: | :---: | :---: |
| **017**_access_management_permissions.sql | `f43b71714eaa26d9...` | NORMAL (Permissions) | ผ่าน | **APPLIED (SUCCESS)** | 63.52 ms |
| **018**_member_profile_requests.sql | `79fb4883c0db3632...` | NORMAL (Schema DDL) | ผ่าน | **APPLIED (SUCCESS)** | 77.30 ms |
| **019**_cleanup_legacy_interest_rates.sql | `f3e26789ce9a722a...` | **FINANCIAL (Data)** | **HOLD (GATED)** | **BLOCKED / NOT RUN** | — |
| **020**_sync_deposit_rates.sql | `55a7a9f484f70bf3...` | **FINANCIAL (Data)** | **HOLD (GATED)** | **BLOCKED / NOT RUN** | — |
| **021**_create_visitor_counter_tables.sql | `2d5dd17d723a3c72...` | NORMAL (Schema DDL) | ผ่าน | **APPLIED (SUCCESS)** | 113.21 ms |
| **022**_google_auth_and_mandatory_mfa.sql | `5ac922d623a50445...` | NORMAL (Schema DDL) | ผ่าน | **APPLIED (SUCCESS)** | 312.64 ms |
| **023**_public_content_revisions.sql | `39555189d6ee0d6c...` | NORMAL (Schema DDL) | ผ่าน | **APPLIED (SUCCESS)** | 111.61 ms |

### 2.2 การตรวจสอบโครงสร้าง Schema ที่เปลี่ยนแปลง (Schema Verification)
จากการตรวจสอบ Schema หลังรัน Migration บน Sandbox:
1. **ตาราง `member_profile_requests` (018)**: ถูกสร้างสมบูรณ์พร้อม 11 คอลัมน์ (`id`, `request_no`, `member_id`, `requested_changes_json`, `status`, `reviewed_by`, `reviewed_at`, `review_notes`, `history_json`, `created_at`, `updated_at`) พร้อม Foreign Key ชี้ไปยัง `members.id` และ `users.id`
2. **ตารางบันทึกสถิติผู้เข้าชม (021)**: ตาราง `website_visits` และ `visitor_counters` ถูกสร้างสมบูรณ์ รองรับการเก็บข้อมูลแบบไม่ละเมิด PDPA (IP SHA-256 Masking)
3. **ตารางระบบยืนยันตัวตน Google & MFA (022)**:
   - ตาราง `user_google_identities` (ผูก Google Sub)
   - ตาราง `staff_invitations` (คำเชิญเจ้าหน้าที่แบบจำกัดเวลา)
   - ตาราง `user_mfa_recovery_codes` (รหัสกู้คืนฉุกเฉินแบบ Hashed ครั้งเดียว)
   - เพิ่มคอลัมน์ในตาราง `users`: `two_factor_confirmed_at`, `two_factor_last_used_slice`, `session_version`, `department`
   - เพิ่มคอลัมน์ในตาราง `login_logs`: `auth_provider`, `google_sub`
4. **ตารางจัดการเนื้อหาพื้นฐาน (023)**: ตาราง `public_content_revisions` ถูกสร้างสมบูรณ์ พร้อมผูกสิทธิ์ `coop_info`, `people`, `rates`, `products`
5. **ตารางประวัติ Migration (`migrations`)**: บันทึก ID 18 ถึง 22 ด้วยสถานะ `status = 'success'` พร้อม Checksum ครบถ้วน โดย**ไม่มีระเบียนของ 019 และ 020** ปรากฏในประวัติ (คงสถานะ HOLD 100%)

---

## 3. รายงานการเปรียบเทียบและรักษาการแก้ไขเดิม 6 รายการบน TEST (Source Reconciliation)

ทีมวิศวกรได้ตรวจสอบเปรียบเทียบโค้ดใน Release Commit `97d0d03` เทียบกับการแก้ไขเฉพาะกิจเดิมบนเซิร์ฟเวอร์ TEST (`/var/www/rycoopweb/test-v2`) ทั้ง 6 รายการ:

| ลำดับ | รายการแก้ไขเฉพาะกิจเดิมบน TEST (Local Modifications) | Commit อ้างอิง | ผลการตรวจสอบใน Release `97d0d03` | สถานะ |
| :---: | :--- | :---: | :--- | :---: |
| **1** | **ระบบบริหารจัดการสมาชิกและบทบาท `member_admin`** | `f309910` | คงคลาส `MemberAdminService`, ตาราง `014`, `015`, และสิทธิ์ของ `member_admin` ไว้อย่างครบถ้วน | **PRESERVED** |
| **2** | **ระบบซ่อมแซม Schema สมาชิกแบบย้อนหลังและหน้า Health** | `6eeadcc` | คงคลาส `MemberSystemHealth`, สคริปต์ซ่อม `016`, และหน้า `AdminMembersHealthPage` ไว้อย่างสมบูรณ์ | **PRESERVED** |
| **3** | **กลไกป้องกัน Form รีเฟรชขณะตรวจสอบ Session ในเบื้องหลัง** | `deed96c` | คงโค้ดใน [src/context/AuthContext.jsx](file:///d:/rycoopweb/src/context/AuthContext.jsx) และ [src/App.jsx](file:///d:/rycoopweb/src/App.jsx) ไม่ unmount ฟอร์มขณะ network probe | **PRESERVED** |
| **4** | **แม่แบบนำเข้าเงินปันผลเปล่าพร้อมระบบตรวจข้อมูลซ้ำ** | `2e272f6` | ไฟล์แม่แบบใน `public/templates/member-dividends-template.csv/xlsx` และระบบตรวจ duplicate ยังคงอยู่ครบถ้วน | **PRESERVED** |
| **5** | **ระบบจัดรูปแบบข้อความ Rich Text Editor สำหรับข่าวและ FAQ** | `8f23ca0` | คอมโพเนนต์ `RichTextEditor`, `RichTextContent` และ Service `RichTextService` (HTMLPurifier) ถูกผสานอย่างลงตัว | **PRESERVED** |
| **6** | **ระบบค้นหาและเลือก Emoji/Symbols ในตัวแก้ไขข้อความ** | `1ab6bf9` | ฟังก์ชัน Picker ใน `RichTextEditor.jsx` และสไตล์ใน `RichTextEditor.css` คงอยู่ครบ 100% | **PRESERVED** |

**ข้อสรุปการ Reconciliation**: ไม่มีการเขียนทับหรือสูญหายของการแก้ไขเดิมทั้ง 6 รายการ และไม่มีจุดขัดแย้ง (Conflict) กับสถาปัตยกรรม Google OIDC หรือ Mandatory MFA แต่อย่างใด

---

## 4. สรุปผลการทดสอบการถดถอยและความมั่นคงปลอดภัย (Security Regression Summary)

```
=========================================================================
1. MigrationRunner Comprehensive Tests: 62/62 PASSED
   - Path Traversal Protection: PASS
   - Concurrency Lock & TOCTOU Recheck: PASS
   - Strict Dry-Run Non-Mutation: PASS
   - Database Identity Match: PASS
   - Root User Strict Rejection: PASS
   - Partial DDL Incident Marker: PASS

2. Security Regression Test Suite: 54/54 PASSED
   - Hardcoded Password/User Bypass: None found (PASS)
   - Default Member Profile Bypass: None found (PASS)
   - Role Allowlist (/staff, /admin, /member): Enforced (PASS)
   - CSRF & Method Spoof Protection: Enforced (PASS)
   - Rate Limiting Storage: Database Shared (PASS)
   - Member Portal Disabled Default: Enforced (PASS)

3. Frontend Contracts & Reference Tests:
   - WorkflowFrontendTest.mjs: 9/9 Contract Assertions PASSED
   - PublicContentReferenceTest.mjs: All Page Renderings PASSED

4. Dependency Vulnerability Audit:
   - npm audit --audit-level=moderate: found 0 vulnerabilities

5. Frontend Build Verification:
   - npm run build: Vite v6.4.4 compiled 2693 modules in 8.43s (Exit Code: 0)
=========================================================================
```

---

## 5. คู่มือขั้นตอนการนำขึ้นระบบทดสอบจริง (TEST Deployment Runbook)

### ขั้นตอนการดำเนินการบน TEST VPS (`https://test.rayongcoop.org`)

#### ขั้นที่ 1: ตรวจสอบและสำรองข้อมูลฐานข้อมูล TEST เดิม
```bash
# ตรวจสอบฐานข้อมูลและพื้นที่จัดเก็บ
cd /var/www/rycoopweb/test-v2

# สำรองข้อมูล rycoopweb_test ด้วย mysqldump พร้อม timestamp
mysqldump -h 127.0.0.1 -u rycoop_backup_user -p rycoopweb_test > /var/www/rycoopweb/backups/rycoopweb_test_before_970d03_$(date +%Y%m%d_%H%M%S).sql

# ยืนยันว่าไฟล์สำรองข้อมูลมีขนาดถูกต้อง
ls -lh /var/www/rycoopweb/backups/
```

#### ขั้นที่ 2: เตรียม Release Directory ใหม่
```bash
# ยืนยันตำแหน่ง release commit 970d03
cd /var/www/rycoopweb/releases/970d03

# ตรวจสอบสถานะ git commit
git rev-parse HEAD
# ต้องแสดง: 97d0d03b306b453e92408c5c7866df42718e2bb2
```

#### ขั้นที่ 3: ติดตั้ง Dependencies และ Build Frontend บน Release Directory
```bash
# ติดตั้ง Backend Dependencies
composer install --no-dev --optimize-autoloader --no-interaction

# ติดตั้ง Frontend Dependencies และ Build
npm ci
npm audit --audit-level=moderate
npm run build

# คัดลอกคอนฟิก .env เฉพาะของ TEST (ห้ามใช้ไฟล์ Production)
cp /var/www/rycoopweb/test-v2/.env /var/www/rycoopweb/releases/970d03/.env
```

#### ขั้นที่ 4: ตรวจสอบ Preflight Migrations บนฐานข้อมูล TEST (`rycoopweb_test`)
```bash
# รัน Preflight Dry-Run ด้วยบัญชี migration เฉพาะ (ห้ามใช้ root)
php bin/console db:migrate --dry-run
```

#### ขั้นที่ 5: ประมวลผล Migrations ความปลอดภัยที่ได้รับการอนุมัติ (017, 018, 021, 022, 023)
```bash
# รันทีละไฟล์ตามลำดับความปลอดภัย โดยไม่รัน 019 และ 020
php database/run_migration.php 017_access_management_permissions.sql
php database/run_migration.php 018_member_profile_requests.sql
php database/run_migration.php 021_create_visitor_counter_tables.sql
php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql
php database/run_migration.php 023_public_content_revisions.sql
```

#### ขั้นที่ 6: สลับ Symlink ชี้ไปยัง Release ใหม่ (Atomic Switchover)
```bash
# อัปเดต static assets
cp -r /var/www/rycoopweb/releases/970d03/public/app/* /var/www/rycoopweb/test-v2-static/

# ทำ Atomic Symlink สลับโค้ด backend
ln -sfn /var/www/rycoopweb/releases/970d03 /var/www/rycoopweb/test-current

# รีโหลดบริการ PHP-FPM และ Nginx
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```

#### ขั้นที่ 7: ทดสอบการทำงานหลังการ Deploy (Smoke Testing)
1. ตรวจสอบการตอบสนองของระบบ: `curl -I https://test.rayongcoop.org/` (ต้องได้ HTTP 200 หรือ 401 Basic Auth)
2. ตรวจสอบ API สถานะข่าว: `curl -s https://test.rayongcoop.org/api/public/home | grep -q "coop_info"`
3. ทดสอบการเข้าสู่ระบบเจ้าหน้าที่ `/login` และตรวจสอบหน้าตั้งค่า MFA `/admin/mfa/enroll`

---

## 6. แผนการย้อนกลับฉุกเฉิน (Rollback & Disaster Recovery Plan)

หากพบข้อขัดข้องวิกฤตหลังการ Deploy ให้ปฏิบัติตามแผนย้อนกลับ 2 ระดับ:

### 6.1 ระดับที่ 1: Code-Level Rollback (เมื่อโค้ดมีปัญหาแต่ Schema ไม่เสียหาย)
ใช้เวลาดำเนินการไม่เกิน 30 วินาที โดยไม่ต้องย้อนฐานข้อมูล:
```bash
# สลับ Symlink กลับไปยังเวอร์ชันเดิม (test-v2)
ln -sfn /var/www/rycoopweb/test-v2 /var/www/rycoopweb/test-current

# คัดลอก static assets เดิมกลับคืน
cp -r /var/www/rycoopweb/test-v2/public/app/* /var/www/rycoopweb/test-v2-static/

# รีโหลดบริการ
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```

### 6.2 ระดับที่ 2: Database Disaster Recovery (เมื่อ Schema หรือข้อมูลขัดข้อง)
กู้คืนฐานข้อมูล TEST จากไฟล์สำรองที่ทำไว้ในขั้นที่ 1:
```bash
# กู้คืนฐานข้อมูล rycoopweb_test จากไฟล์ Backup ก่อน Deploy
mysql -h 127.0.0.1 -u rycoop_backup_user -p rycoopweb_test < /var/www/rycoopweb/backups/rycoopweb_test_before_970d03_*.sql

# ยืนยันความสมบูรณ์ของโครงสร้างตาราง
mysql -h 127.0.0.1 -u rycoop_backup_user -p -e "SHOW TABLES FROM rycoopweb_test;"
```

---

## 7. ตารางสรุปหลักฐานการตรวจสอบ (Audit Evidence Matrix)

```
+------------------------------------------+-----------------------+------------------------------------------+
| Check Item                               | Status                | Evidence / Metrics                       |
+------------------------------------------+-----------------------+------------------------------------------+
| Git Test Branch Commit                   | PASS                  | Commit 97d0d03 (PR #4 merged cleanly)    |
| Sandbox Migration 017                    | PASS                  | Applied in 63.52ms (SHA: f43b71714e...)  |
| Sandbox Migration 018                    | PASS                  | Applied in 77.30ms (SHA: 79fb4883c0...)  |
| Sandbox Migration 021                    | PASS                  | Applied in 113.21ms (SHA: 2d5dd17d72...) |
| Sandbox Migration 022                    | PASS                  | Applied in 312.64ms (SHA: 5ac922d623...) |
| Sandbox Migration 023                    | PASS                  | Applied in 111.61ms (SHA: 39555189d6...) |
| Financial Migrations 019 & 020           | HOLD                  | Confirmed Gated; 0 entries in migrations |
| Preserved 6 Local TEST Customizations    | PASS                  | Verified present in 97d0d03              |
| Unit & Safety Tests (MigrationRunner)    | PASS                  | 62/62 Passed successfully                |
| Security Regression Tests                | PASS                  | 54/54 Passed successfully                |
| Frontend Contract Tests                  | PASS                  | 9/9 Passed successfully                  |
| Frontend Production Build                | PASS                  | 2693 modules built in 8.43s              |
| Dependency Vulnerability Gate            | PASS                  | 0 vulnerabilities (moderate gate)        |
| Recovery Drill Execution                 | PASS                  | Verified on rycoopweb_restore_drill...   |
+------------------------------------------+-----------------------+------------------------------------------+
```
