# แผนทดสอบและปล่อย workflow — 26 ตุลาคม 2569

ชุดงาน `codex/workflow-repair` จัดทำใน worktree แยก ไม่เปลี่ยน checkout หลัก ไม่ได้แตะบัญชีจริง ฐานข้อมูลจริง หรือ VPS
เป้าหมาย PR คือ `Test` เท่านั้น การตั้งค่าในไฟล์ .env ของ VPS ต้องตรวจและจัดการโดยผู้ดูแล ไม่คัดลอกทับด้วย .env.example

## ขั้น TEST ก่อน Production

1. ใช้ revision ของ PR ที่ CI ผ่าน เตรียม release/checkout สำหรับ TEST แยกจากระบบที่ใช้งานอยู่ และฐานข้อมูล TEST แยกจริง ไม่มีข้อมูลสมาชิกจริงใน automated tests
2. ตรวจรายการ migrations ที่ยังไม่เคยรันทั้งหมดก่อนอนุมัติ ไม่ใช้ `db:seed` กับฐานข้อมูลที่มีอยู่ โดยเฉพาะ migrations เดิม 019/020 ที่ปรับข้อมูลอัตรา
3. Migration 022 เพิ่ม identity/invitation/recovery tables และคอลัมน์ MFA/session; แก้ conditional ADD COLUMN ให้ใช้ information_schema/PREPARE รองรับ MySQL 8 และ retry หลัง partial DDL ได้
4. Migration 023 เพิ่ม public_content_revisions และ permissions ของข้อมูลสหกรณ์/บุคลากร/อัตรา/ผลิตภัณฑ์/news.publish ไม่แจกสิทธิ์แก่ role เดิมอัตโนมัติ นอกจาก super_admin ที่ใช้สิทธิ์รวมตาม policy เดิม
5. สร้าง backup ด้วยเครื่องมือที่รองรับ consistent snapshot (เช่น mysqldump --single-transaction สำหรับ InnoDB) ตรวจ checksum และทดลอง restore ในฐานข้อมูลว่างแยกต่างหากก่อน migration จริง ไม่ใช้ CLI dump ปัจจุบันเป็นหลักฐานว่า recovery ผ่านแล้ว
6. หลังอนุมัติ migration บน TEST ให้ผู้ดูแลรันเอง ตรวจ schema และข้อมูลก่อน–หลัง จากนั้นติดตั้ง dependencies/build ตาม lockfiles ทดสอบ PHP-FPM 8.3/MySQL 8 จริง
7. ตรวจ Nginx ให้ deep link /admin/mfa/*, /admin/public-content, /admin/content และ React routes ถึง SPA ส่วน /api/*, /auth/*, POST login/logout ถึง PHP หน้า HTTP preview ต้องเป็น static เท่านั้น ห้ามเปิด protected API/login ผ่าน HTTP
8. ทดสอบ UAT ด้านล่างด้วย synthetic accounts/ข้อมูลจำลองแล้วบันทึก SHA ที่ทดสอบตรงกับ artifact
9. ขออนุมัติ Production เป็นครั้ง ๆ หลังมีผล CI/UAT/restore ครบ ห้ามถือว่าการ push หรือ PR เป็นการอนุมัติ deploy

## การตั้งค่าที่ต้องตรวจ

- APP_KEY ต้องเป็น key ที่กำหนดเองอย่างน้อย 32 ตัวอักษร เก็บนอก Git หากเคยเข้ารหัส MFA แล้วต้องรักษา key เดิม ห้ามสุ่มเปลี่ยนเพราะจะถอดรหัสข้อมูลเดิมไม่ได้
- TWO_FACTOR_ENCRYPTION_KEY ใช้แยก key ได้เมื่อวางแผน re-enrollment/rotation แล้วเท่านั้น ค่าไม่กำหนดจะใช้ APP_KEY เดิม ไม่มี static fallback key อีก
- STAFF_MFA_REQUIRED=true บังคับ staff enrollment หลัง login; สถานะ MFA เปิดแล้วแต่ secret หายจะปฏิเสธ ไม่ข้าม MFA
- Google ปิดโดย default ต้องตั้ง GOOGLE_AUTH_ENABLED/client ID/client secret/HTTPS redirect URI ให้ครบและตรงกับ Google Console ก่อนเปิดใช้งาน PRIMARY_SUPERADMIN_EMAIL ไม่มีอีเมลบุคคล default อีก
- STAFF_GOOGLE_LOGIN_REQUIRED ทำงานเมื่อ Google เปิดและ configured แล้ว เจ้าหน้าที่จะใช้ Google ตาม policy สมาชิกยังใช้เส้นทางสมาชิก
- SESSION_SECURE=true, APP_DEBUG=false และ HTTPS สำหรับระบบจริง Session idle default 20 นาที อายุรวมตาม SESSION_LIFETIME (default 2 ชั่วโมง); MFA partial session 10 นาที
- TRUSTED_PROXY_IPS ว่าง = เชื่อ REMOTE_ADDR เท่านั้น ระบุเฉพาะ IP reverse proxy ที่ควบคุมและจัดการ X-Forwarded-For ถูกต้อง ไม่มีการเชื่อ CF header ที่ส่งมาโดย client โดยตรง
- กำหนด custom role แบบมี view และ update สำหรับผู้จัดทำ, view และ publish สำหรับผู้อนุมัติ แอดมินหลักยังทำได้ทั้งสองขั้น ไม่มีข้อบังคับว่าต้องคนละบัญชี

## UAT ที่ต้องผ่านบน TEST

- สมาชิกไม่เห็นข้อมูลสมาชิกอื่น; editor ไม่เห็น/importข้อมูลการเงิน; custom role เข้า workspace และโมดูลที่อนุญาตได้ API ปฏิเสธผู้ไม่มีสิทธิ์และ CSRF ที่ไม่ถูกต้อง
- Login → enroll/verify/recovery → dashboard, refresh/deep link, wrong code/429, expired challenge/restart, session revoke, suspended account, logout และ Google callback จริง
- จัดทำร่างข้อมูลทุกหมวด สลับหมวด/เพิ่ม/นำออก/เลื่อนลำดับ/preview/เผยแพร่/ตั้งวันมีผลและสิ้นสุด/สร้างร่างจากประวัติ ไม่เปลี่ยนข้อมูลจริงก่อนเผยแพร่
- อัตราบน TopBar/หน้าเงินฝาก/หน้าเงินกู้/เครื่องคำนวณ/API ตรงกันทั้งหลังแก้ชื่อผลิตภัณฑ์และกรณีมีวันมีผลในอนาคต
- ข่าว draft → submitted → preview → publish ผู้แก้ไม่สามารถแอบส่ง published ใน payload; ข่าวที่เผยแพร่แล้วเมื่อแก้จะพักเป็นร่างจนอนุมัติใหม่ (ยังไม่มี parallel draft ของข่าวที่คงฉบับเดิมออนไลน์)
- GET member requests ใช้ handler requests ไม่ใช่ show; ทดสอบ approve/reject/version conflict และ import preview/confirm/totals/duplicate confirmation/concurrent change/rollback บน MySQL TEST ด้วยข้อมูลจำลอง
- Browser จริงบนมือถือ/desktop, logo/image HTTP 200 และ Content-Type, ไม่มี form reset ระหว่าง background session checks

## Deployment guard และข้อจำกัด

`bin/deploy-production.sh` ต้องได้รับ DEPLOY_APPROVED_COMMIT, DEPLOY_TESTED_COMMIT, DEPLOY_RESTORE_VERIFIED_COMMIT เป็น SHA เต็มเดียวกัน และ DEPLOY_HEALTH_URL เป็น HTTPS ตรวจ working tree สะอาดและ SHA ของ Test ตรงก่อน merge Build ต้องผ่านก่อน migrate
ค่าเหล่านี้เป็นการยืนยันโดยผู้ดูแล ไม่ใช่การตรวจผล GitHub/UAT/restore อัตโนมัติ ห้ามใส่ค่าล่วงหน้าทิ้งไว้ถาวร
สคริปต์ยัง deploy แบบ in-place และไม่ได้ทำ atomic release switch/automatic rollback จึงยังมีช่วงเสี่ยงระหว่างเปลี่ยน source/build ต้องออกแบบ release-directory/symlink กับ path/Nginx ของ VPS จริงและอนุมัติก่อนเปลี่ยนสถาปัตยกรรม
รอบนี้ทดสอบเฉพาะ bash syntax และการปฏิเสธเมื่อไม่มีอนุมัติ ไม่ได้ deploy หรือทดสอบคำสั่ง sudo บน VPS

## Rollback

- เก็บ SHA/artifact/config ที่ใช้งานก่อนหน้าและ backup นอก web root พร้อมผล restore drill ก่อนเริ่ม
- หากหน้า/API เสีย ให้หยุด release และใช้ artifact/source ชุดก่อนตามกระบวนการ VPS ที่อนุมัติ ไม่ใช้ force push หรือ reset งานค้าง
- 022/023 เป็น additive: rollback source ไม่จำเป็นต้อง DROP คอลัมน์/ตาราง เก็บ revision/audit/recovery data ไว้ ตรวจว่า source เก่าอยู่ร่วม schema ใหม่ได้ก่อนใช้จริง
- MySQL DDL ไม่ rollback ด้วย transaction ทั่วไป หากต้องกู้ฐานข้อมูลต้องอนุมัติและวาง maintenance window/reconcile รายการหลัง backup ห้าม restore ทับ Production อัตโนมัติ
- หากเผยแพร่เนื้อหาผิด ให้ใช้ประวัติสร้างร่างใหม่ ตรวจแล้วเผยแพร่ ไม่ลบ audit และไม่แก้ยอดสมาชิกโดยตรง
