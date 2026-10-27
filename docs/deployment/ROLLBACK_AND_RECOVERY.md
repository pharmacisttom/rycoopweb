# คู่มือการกู้คืนระบบและขั้นตอนย้อนกลับฉุกเฉิน (Rollback and Disaster Recovery Runbook)

**ระบบ**: RYCOOPWEB 2.3
**วันที่จัดทำ**: 2026-10-27
**เป้าหมายสภาพแวดล้อม**: TEST VPS (`https://test.rayongcoop.org`) และ PRODUCTION (`https://www.rayongcoop.org`)

---

## 1. หลักการสำคัญเกี่ยวกับ MySQL DDL และข้อจำกัดด้าน Rollback

> [!CAUTION]
> **MySQL DDL Statements ทำให้เกิด Implicit Commit**: คำสั่ง DDL เช่น `CREATE TABLE`, `ALTER TABLE`, `DROP TABLE` ใน MySQL **ไม่สามารถย้อนกลับด้วยคำสั่ง `ROLLBACK` ใน Transaction ได้** การ rollback โครงสร้างฐานข้อมูลจึงต้องอาศัยการ Restore จากไฟล์ Backup ที่สร้างขึ้นก่อนเริ่มกระบวนการเสมอ

---

## 2. ขั้นตอนการสำรองข้อมูลก่อนดำเนินการ (Pre-Deployment Backup Drill)

ก่อนดำเนินการ Deployment หรือรัน Migration ใดๆ จะต้องทำการ Backup ข้อมูลและโครงสร้างดังนี้:

```bash
# 1. สำรองข้อมูลฐานข้อมูลผ่าน CLI
php bin/console backup:run

# 2. ตรวจสอบว่ามีไฟล์สำรองข้อมูลอยู่ใน storage/backups/
ls -lh storage/backups/

# 3. สำรองไฟล์คอนฟิกูเรชันและ .env
cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
```

---

## 3. ขั้นตอนการย้อนกลับซอร์สโค้ดและ Dependencies (Code Rollback)

หากพบข้อผิดพลาดด้านการแสดงผล, API หรือ Routing หลังการ Deploy:

```bash
# 1. ตรวจสอบ commit hash ดั้งเดิมที่ปลอดภัย (ตัวอย่าง Baseline: 88f8805 หรือ commit ก่อนหน้า)
git log -n 5 --oneline

# 2. ย้อนกลับ source code และ lockfile ไปยัง commit เป้าหมาย
git checkout <APPROVED_BASELINE_COMMIT> -- .

# 3. ทำความสะอาดและติดตั้ง dependencies ที่สอดคล้องกับ lockfile เดิม
npm ci
composer install --no-dev --optimize-autoloader

# 4. คอมไพล์โปรดักชันแอปพลิเคชันใหม่
npm run build

# 5. รีโหลด Nginx และ PHP-FPM
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```

---

## 4. ขั้นตอนการย้อนกลับฐานข้อมูล (Database Recovery)

หากการย้ายข้อมูล (Migration) ล้มเหลว หรือโครงสร้างตารางเกิดความเสียหาย:

```bash
# 1. ปิดรับทราฟฟิกชั่วคราวผ่าน Nginx Maintenance Page (หากจำเป็น)
# 2. ระบุไฟล์ backup ล่าสุดที่ผ่านการทดสอบ
BACKUP_FILE=$(ls -t storage/backups/*.sql | head -n 1)
echo "Restoring from: ${BACKUP_FILE}"

# 3. กู้คืนฐานข้อมูลกลับคืนสู่สภาพสมบูรณ์ (ตัวอย่างสำหรับ rycoopweb_test)
mysql -u root -p rycoopweb_test < "${BACKUP_FILE}"

# 4. ตรวจสอบความถูกต้องของข้อมูลหลัก
mysql -u root -p -e "SELECT id, migration, applied_at FROM rycoopweb_test.migrations ORDER BY id DESC LIMIT 5;"
```

---

## 5. การตรวจสอบความพร้อมหลังการกู้คืน (Post-Recovery Verification Checklist)

1. [ ] **HTTP 200 Health Check**: เรียกดูหน้าหลัก `https://test.rayongcoop.org/`
2. [ ] **CSRF Token Generation**: ทดสอบ `GET /csrf-token` ตอบกลับ JSON ถูกต้อง
3. [ ] **Public Content Verification**: อัตราดอกเบี้ย ข้อมูลกรรมการ เจ้าหน้าที่ แสดงผลครบถ้วน
4. [ ] **Authentication Flow**: เจ้าหน้าที่สามารถเข้าสู่ระบบและยืนยันตัวตนได้
5. [ ] **Log Inspection**: ตรวจสอบ `storage/logs/*.log` และ `/var/log/nginx/error.log` ไม่มี Uncaught Exception ตกค้าง
