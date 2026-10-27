# เอกสารข้อกำหนดการส่งมอบ Release B: การยกระดับระบบเจ้าหน้าที่และสถาปัตยกรรมความมั่นคงปลอดภัย (Release B Specification)

**รหัสเวอร์ชัน**: Release B (RYCOOPWEB 2.3 Application & Security Upgrade)
**Branch ต้นทาง**: `codex/member-portal-integration`
**Target Branch**: `Test` (หลังควบรวม Release A สำเร็จ)
**สถานะการอนุมัติ**: รอการอนุมัติและทดสอบบน TEST Sandbox

---

## 1. ขอบเขตการเปลี่ยนแปลง (Scope of Release B)

Release B เป็นการส่งมอบฟีเจอร์ด้านความมั่นคงปลอดภัยและการจัดการเจ้าหน้าที่ขั้นสูง ซึ่งพัฒนาต่อยอดจาก Release A:

1. **Google OAuth 2.0 / OpenID Connect (OIDC)**:
   - การยืนยันตัวตนเจ้าหน้าที่ผ่าน Google Workspace / Gmail
   - สถาปัตยกรรม PKCE + Cryptographic State + Nonce
   - การผูกบัญชีด้วย Google `sub` และระบบ Staff Invitation Link
   - การ Bootstrap บัญชี Primary Super Admin (`pharmacisttom@gmail.com`) อย่างปลอดภัย
2. **Mandatory Multi-Factor Authentication (MFA)**:
   - บังคับใช้ TOTP สำหรับบทบาทเจ้าหน้าที่ทุกตำแหน่ง (Google Authenticator)
   - การสร้าง SVG QR Code ในเครื่องเซิร์ฟเวอร์แบบออฟไลน์ 100%
   - การเข้ารหัส TOTP Secret ด้วย AES-256-GCM
   - การป้องกัน OTP Replay Attack ด้วย Time Slice tracking
   - การแบ่งแยกสถานะเซสชัน 3 ระดับ (Unauthenticated vs. Pending MFA vs. Fully Authenticated)
   - รหัสกู้คืนฉุกเฉินแบบใช้ครั้งเดียว (Single-Use Hashed Recovery Codes)
3. **Hardened Migration Engine**:
   - คลาส `App\Services\MigrationRunner` ตรวจสอบฐานข้อมูลเป้าหมาย (`SELECT DATABASE()`), Concurrency Lock, Checksum verification, Dry-run mode, และคำขอยืนยันสำหรับ Financial Migrations
4. **การประมวลผล Migrations 017–023**:
   - รัน Migrations ตามลำดับการอนุมัติ: 017, 018, 021, 022, 023
   - สำหรับ 019 และ 020 (Financial Data): ต้องผ่านการตรวจสอบเปรียบเทียบก่อน-หลัง และได้รับความเห็นชอบอย่างเป็นลายลักษณ์อักษรตามเอกสาร `docs/database/FINANCIAL_MIGRATION_REVIEW.md`

---

## 2. ขั้นตอนและลำดับการตรวจสอบบนสภาพแวดล้อม TEST (Staging Deployment Order)

1. ทำการ Merge **Release A** เข้าสู่ branch `Test` และรัน CI จนกระทั่งสถานะเป็นสีเขียว
2. ทำการสำรองข้อมูลฐานข้อมูล TEST (`rycoopweb_test`) ด้วย `php bin/console backup:run`
3. ดำเนินการ Preflight ตรวจสอบ Migration บน TEST:
   ```bash
   php bin/console db:migrate --dry-run
   ```
4. ดำเนินการ Migrate สคริปต์ความปลอดภัย (017, 018, 021, 022, 023):
   ```bash
   php database/run_migration.php 017_access_management_permissions.sql
   php database/run_migration.php 018_member_profile_requests.sql
   php database/run_migration.php 021_create_visitor_counter_tables.sql
   php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql
   php database/run_migration.php 023_public_content_revisions.sql
   ```
5. ทดสอบการเข้าสู่ระบบผ่าน Google OIDC, การลงทะเบียน MFA, การป้อนรหัส OTP, และการเข้าถึงหน้า Workspace ตามสิทธิ์ RBAC
