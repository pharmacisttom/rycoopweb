# คู่มือขั้นตอนการนำขึ้นระบบทดสอบ (TEST Deployment Runbook)

**ระบบ**: RYCOOPWEB 2.3  
**วันที่จัดทำ**: 2026-10-27  
**เป้าหมาย**: สภาพแวดล้อมทดสอบ TEST VPS (`https://test.rayongcoop.org`)  

---

## 1. ข้อมูลสภาพแวดล้อม TEST (Environment Topology)

- **URL สาธารณะ**: `https://test.rayongcoop.org`
- **พาธ Backend (PHP)**: `/var/www/rycoopweb/test-v2`
- **พาธ Frontend (React Static Assets)**: `/var/www/rycoopweb/test-v2-static`
- **ฐานข้อมูลเป้าหมาย**: `rycoopweb_test`
- **ระบบป้องกันหน้าเว็บ (Gateway)**: Nginx HTTP Basic Authentication
- **พาธทดสอบความปลอดภัยแยกเดี่ยว**: `/var/www/rycoopweb/security-validation`

---

## 2. ขั้นตอนการติดตั้งและ Deploy (Step-by-Step Procedure)

### ขั้นที่ 1: ตรวจสอบและดึงโค้ดล่าสุดบนเซิร์ฟเวอร์
```bash
cd /var/www/rycoopweb/test-v2

# ตรวจสอบว่าไม่มี uncommitted changes บนเซิร์ฟเวอร์
git status

# ดึง branch ล่าสุดที่ผ่านการอนุมัติ (เช่น codex/dependency-moderate-fixes หรือ Test)
git fetch https://github.com/pharmacisttom/rycoopweb.git codex/dependency-moderate-fixes
git checkout -B codex/dependency-moderate-fixes FETCH_HEAD
```

### ขั้นที่ 2: สำรองข้อมูลฐานข้อมูล TEST ก่อนดำเนินการเสมอ
```bash
php bin/console backup:run
ls -lh storage/backups/
```

### ขั้นที่ 3: ติดตั้ง Dependencies ฝั่ง Backend
```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

### ขั้นที่ 4: ติดตั้ง Dependencies และ Build ฝั่ง Frontend
```bash
npm ci
npm audit --audit-level=moderate
npm run build

# คัดลอกไฟล์ที่ build แล้วไปยังไดเรกทอรี static
cp -r public/app/* /var/www/rycoopweb/test-v2-static/
```

### ขั้นที่ 5: ตรวจสอบและรัน Database Migrations อย่างปลอดภัย
```bash
# 1. รัน Dry-run เพื่อตรวจสอบความเข้ากันได้
php bin/console db:migrate --dry-run

# 2. เมื่อผล Preflight ถูกต้อง ให้ดำเนินการรันจริง
# สำหรับ Migration โครงสร้างความปลอดภัย (021, 022, 023):
php database/run_migration.php 021_create_visitor_counter_tables.sql
php database/run_migration.php 022_google_auth_and_mandatory_mfa.sql
php database/run_migration.php 023_public_content_revisions.sql

# สำหรับ Migration ข้อมูลการเงิน (019, 020) ต้องมี flag ยืนยันหลังตรวจสอบความถูกต้อง:
# php database/run_migration.php 020_sync_deposit_rates.sql --confirm-financial
```

### ขั้นที่ 6: ตรวจสอบการตั้งค่า Nginx สำหรับ Google OAuth Callback
ตรวจสอบว่าไฟล์คอนฟิก Nginx มีการยกเว้น Basic Auth สำหรับ `/auth/google/callback`:
```nginx
location = /auth/google/callback {
    auth_basic off;
    try_files $uri $uri/ /index.php?$query_string;
}
```
ทดสอบไวยากรณ์และรีโหลด Nginx:
```bash
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl reload php8.3-fpm
```

---

## 3. ขั้นตอนการทดสอบหลังการ Deploy (Smoke Testing)

1. **ตรวจสอบความปลอดภัยของ Dependency**:
   ```bash
   npm audit --audit-level=moderate
   ```
2. **เรียกดูหน้าหลักและทดสอบ HTTP Status**:
   ```bash
   curl -I https://test.rayongcoop.org/
   ```
3. **ทดสอบเส้นทาง Authentication**:
   - เปิดบราวเซอร์ไปที่ `https://test.rayongcoop.org/login` และ `https://test.rayongcoop.org/admin/login`
   - ทดสอบล็อกอินด้วย Google OIDC และเข้าสู่หน้าตั้งค่า MFA (`/admin/mfa/enroll` หรือ `/admin/mfa/verify`)
   - ตรวจสอบว่าภาพ QR Code แสดงผลถูกต้อง และสามารถป้อนรหัส 6 หลักจาก Google Authenticator ได้สำเร็จ
