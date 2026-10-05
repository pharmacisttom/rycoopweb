# Prompt: อัปเดต VPS จาก GitHub

คุณเป็น DevOps และ Full-stack Engineer ช่วยอัปเดตเว็บสหกรณ์ออมทรัพย์สาธารณสุขระยองบน VPS จาก GitHub และตรวจใช้งานจริงหลังอัปเดต

Repository: https://github.com/pharmacisttom/rycoopweb
Branch: Test
เทคโนโลยี: React/Vite, PHP 8.2+, MySQL
ให้ตรวจ HEAD ล่าสุดของ branch จริงก่อนเริ่ม และบันทึก commit ที่ใช้ deploy

เริ่มจากตรวจ SSH connection หรือช่องทางเข้าถึง VPS ที่มีอยู่ หากไม่มี ให้ถามเฉพาะ host, SSH user, domain และตำแหน่งโปรเจกต์ที่ยังขาด ใช้กุญแจ SSH ที่มีอยู่ ไม่ขอให้วาง private key หรือรหัสผ่านในแชท หากทำงานผ่านแชทที่ไม่มี terminal/SSH ให้บอกข้อจำกัดและให้ชุดคำสั่งตามข้อมูลเซิร์ฟเวอร์จริง อย่าอ้างว่าติดตั้งสำเร็จถ้ายังไม่ได้รัน

อ่าน AGENTS.md, DEPLOYMENT.md, INSTALLATION.md, SECURITY.md, BACKUP.md, docs/member-dividends.md และ bin/deploy-production.sh จาก repository ก่อนวางแผน อย่ารันสคริปต์โดยไม่อ่านและตรวจว่าตรงกับ VPS นี้

## ขอบเขตการเปลี่ยนแปลง

- ปุ่มบนเว็บไซต์เปลี่ยนเป็น “ระบบสมาชิก” พร้อมหน้าเข้าสู่ระบบใหม่ในโทนสีน้ำเงิน
- เมนูจัดวางใหม่ ป้องกันการซ้อนทับ รองรับมือถือ
- สมาชิกเข้า `/member/login` และดูรายการที่ `/member/dividends`
- Username คือเลขบัตรประชาชน 13 หลัก รหัสเริ่มต้นของบัญชีใหม่คือเลขสมาชิกที่เก็บศูนย์นำหน้า เช่น `00025` รหัสผ่านถูกเก็บเป็น hash
- ผู้ดูแลนำเข้ารายปีผ่าน `/admin/dividends/import` รองรับ XLSX/CSV มี preview ก่อน confirm และแม่แบบใน `public/templates/`
- Migration 013 สร้างตาราง member_dividends
- ไฟล์ใน storage/private, .env, source Excel และข้อมูล MySQL local ไม่ได้อยู่ใน GitHub อย่าสรุปว่า git pull จะทำให้ข้อมูลสมาชิกปรากฏบน VPS
- ข้อมูล local ที่ตรวจแล้วมี 4,855 รายการ: ปี 2567 จำนวน 2,429 และปี 2568 จำนวน 2,426 มี 4 รายการพักไว้เพราะ identity ขัดแย้ง ห้ามเดาหรือรวมข้อมูลบุคคลต่างกัน
- Vite มี PHP backend อัตโนมัติสำหรับ development เท่านั้น ห้ามใช้ npm run dev หรือ PHP built-in server เป็น production service

## ขั้นตอนที่ต้องดำเนินการ

1. ตรวจระบบจริง: OS, document root, Git remote/branch/working tree, deployed commit, Nginx/Apache, PHP-FPM/version/extensions, Node/npm, MySQL, SSL และสิทธิ์ไฟล์ ตรวจว่าเว็บอยู่ใต้ root หรือ subpath อย่าเดา path หรือ service name
2. สำรองฐานข้อมูล ไฟล์อัปโหลด .env และ configuration ที่เกี่ยวข้องไว้ในตำแหน่ง private พร้อมบันทึก deployed commit และวิธี restore ตรวจ backup ว่าใช้ได้ ห้ามแสดง secrets ใน output
3. Fetch repository แล้วเปรียบเทียบ diff หาก working tree บน VPS มีการแก้ไขให้รักษาไว้ ห้าม git reset --hard, clean, force push หรือเขียนทับโดยอัตโนมัติ ใช้ checkout/release แยกหรือ fast-forward ที่ไม่สูญเสียงานเดิม
4. ติดตั้ง dependencies ตาม lockfiles ด้วย npm ci และ Composer ที่จำเป็น รัน build และ tests ที่เกี่ยวข้อง ตรวจว่า production base ของ Vite `/app/` ตรงกับการเสิร์ฟไฟล์จริงก่อนใช้
5. ตรวจ migration ที่ apply แล้วก่อนรัน php bin/console db:migrate อ่าน migration ที่ยังไม่ apply เพราะบาง migration เก่าเปลี่ยน seed/content อย่ารัน full db:seed ทับ production และห้ามล้างฐานข้อมูล ตรวจ member role และข้อจำกัด schema
6. รักษา .env เดิม ตั้ง FEATURE_MEMBER_LOGIN=true สำหรับบริการสมาชิก ตรวจ SESSION_SECURE=true ภายใต้ HTTPS, SESSION_SAVE_PATH ที่เขียนได้, APP_URL ที่ตรง domain และค่าฐานข้อมูลจริง อย่านำค่าของ local ไปแทนค่า production ทั้งชุด ฟีเจอร์สมาชิกส่วนอื่นยังปิดไว้ได้
7. ตรวจ rewrite: SPA routes ต้องเปิด React, API/auth POST ต้องส่ง PHP, assets และ templates ต้องโหลดได้, GET /login ควรแสดงหน้า React และไม่ตกไปยัง PHP view ที่ไม่มีอยู่ ตรวจ routing ด้วย Nginx/Apache จริง ไม่พึ่งเฉพาะ Vite proxy
8. ตรวจสิทธิ์ storage/sessions, logs และ uploads ให้เหมาะสม ห้าม chmod 777 เปิด PHP zip และ SimpleXML หากนำเข้า XLSX ปิดการเข้าถึง source/backend/private, .env และ Git โดยตรง ตรวจว่า document root ไม่เปิดเผยโฟลเดอร์ repository
9. เปิด release ใหม่ด้วยวิธีที่ย้อนกลับได้ ตรวจ config syntax ก่อน reload service และลด downtime
10. ตรวจข้อมูลสมาชิกใน production ก่อนนำเข้า หากไม่มีไฟล์/ข้อมูลให้ถามเจ้าของระบบว่าจะใช้ไฟล์ใดและช่องทางส่งที่ปลอดภัย ห้ามนำข้อมูล Excel จริงขึ้น GitHub ใช้ web import หรือ CLI ที่มี validation ตามคู่มือ การ reset รหัสทุกบัญชีบน production ต้องได้รับยืนยันโดยเฉพาะ เพราะจะเขียนทับรหัสที่สมาชิกเปลี่ยนแล้ว; การนำเข้าปกติต้องคงรหัสเดิม
11. Smoke test domain จริง: หน้าแรก ข่าว เอกสาร รูปภาพ ระบบสมาชิก หน้า login, CSRF, login/logout, session expiry, role access, ownership ของข้อมูลสมาชิก, เลือกปี 2567/2568, หน้า import, โหลดแม่แบบ, มือถือ และตรวจ console/network errors ใช้บัญชีทดสอบที่เจ้าของอนุญาตและปกปิดข้อมูลในหลักฐาน
12. ตรวจ log หลัง deploy ถ้าพบปัญหาหนักให้ rollback ตามแผนที่เตรียมไว้ ต้องแยก rollback code จากการ restore ฐานข้อมูล ห้าม restore ทับธุรกรรมใหม่โดยไม่ประเมินและยืนยัน

## รายงานหลังงาน

แจ้ง domain, branch, full commit, migration ที่รัน, สิ่งที่เปลี่ยน, ผลทดสอบพร้อม HTTP status/หลักฐาน, สถานะข้อมูลสมาชิก, งานที่ยังค้าง, ที่เก็บ backup โดยไม่แสดง secrets และคำสั่ง rollback ที่ตรงกับ VPS นี้ แยกสิ่งที่ทำจริงจากสิ่งที่ยังแนะนำหรือทดสอบไม่ได้
