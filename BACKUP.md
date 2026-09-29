# Disaster Recovery & Backup Guide — RayongCoop Digital Portal

คู่มือการสำรองและกู้คืนระบบฐานข้อมูลสำหรับผู้ดูแลระบบ

---

## 1. วิธีการสำรองข้อมูล (Backup Procedures)

### 1.1 การสำรองข้อมูลผ่านคำสั่ง CLI (แนะนำ)
```bash
php bin/console backup:run
```
ไฟล์สำรองจะถูกจัดเก็บไว้ที่ `storage/backups/backup_YYYY_MM_DD_HHMMSS.sql`

### 1.2 การสำรองข้อมูลผ่าน Admin CMS
1. เข้าสู่ระบบ Admin CMS -> เมนู **สำรองข้อมูล (Backup)**
2. คลิกปุ่ม **"สำรองข้อมูลทันที"**
3. ระบบจะสร้างไฟล์ SQL Dump อัตโนมัติ สามารถดาวน์โหลดเก็บไว้ภายนอกได้

---

## 2. ขั้นตอนการกู้คืนระบบ (Disaster Recovery & Restore)

> [!CAUTION]
> การกู้คืนข้อมูลจะเขียนทับข้อมูลปัจจุบัน กรุณาตรวจสอบให้แน่ใจก่อนดำเนินการ

1. ทำการสำรองฐานข้อมูลปัจจุบันเก็บไว้ก่อน (Pre-restore snapshot)
2. สั่งนำเข้าไฟล์สำรอง SQL ผ่าน MySQL CLI:
   ```bash
   mysql -u root -p rayongcoop_db < storage/backups/backup_YYYY_MM_DD_HHMMSS.sql
   ```
3. ตรวจสอบความถูกต้องของข้อมูลและรีเฟรชแคชของระบบ
# Production rollback

Before every deployment, record the output of `git rev-parse HEAD` and run
`php bin/console backup:run`. To roll back application code, check out that
recorded commit, then run `composer install --no-dev --optimize-autoloader`,
`npm ci`, and `npm run build`. Run `sudo nginx -t` before reloading Nginx.
Restore the database backup only when the failed release changed data or
schema incompatibly; never restore it merely to roll back frontend code.
