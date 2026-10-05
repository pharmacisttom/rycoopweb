# Prompt แจ้งงานระบบสมาชิกให้ผู้ร่วมพัฒนา

คัดลอกข้อความด้านล่างส่งให้ระบบหรือผู้ร่วมพัฒนาได้ ไม่แนบรหัสผ่านหรือข้อมูลสมาชิกจริง

```text
คุณรับช่วงตรวจสอบและพัฒนาระบบสมาชิกของสหกรณ์ออมทรัพย์สาธารณสุขระยอง
Git: https://github.com/pharmacisttom/rycoopweb | Branch: Test
Workspace: D:\rycoopweb | Stack: React/Vite + PHP 8.2+ custom MVC + MySQL
ตรวจ commit ล่าสุด อ่าน AGENTS.md, docs/agents/ และ docs/member-dividends.md ก่อนทำงาน

งานที่ทำแล้ว
1. สมาชิก login ด้วยเลขบัตร 13 หลัก ดูปันผลของตนเองย้อนหลังแยกปีที่ /member/dividends
2. นำเข้า XLSX/CSV UTF-8 ปี พ.ศ. 2400–2800 มีแม่แบบ; ไฟล์เก่าไม่มีปีใช้ปีที่เลือก
3. ตรวจหัวคอลัมน์ เลขศูนย์นำหน้า เลขบัตรซ้ำ เลขสมาชิกขัดแย้ง สูตรไม่มีผลคำนวณ และยอดรวมไม่ตรง แสดงแถว เลขสมาชิก เลขบัตร และสาเหตุ ไม่บันทึกบางส่วนเมื่อผิด
4. Preview เทียบฐานข้อมูลก่อนยืนยัน แสดงรายการใหม่/ยอดเปลี่ยน/ยอดเดิม/สมาชิกใหม่/ชื่อและสังกัดที่จะเปลี่ยน พร้อมตัวอย่าง 20 รายการ
5. ยืนยันใน transaction ด้วย token ผูก session อายุ 30 นาที หากข้อมูลเปลี่ยนหลัง preview ต้องตรวจใหม่
6. นำเข้าซ้ำไม่สร้างยอดซ้ำ คงข้อมูลปีอื่นและรหัสเดิม ชื่อ/สังกัดเดิมเปลี่ยนเมื่อเลือก checkbox เท่านั้น
7. /admin/members/dashboard แสดงข้อมูลจริง: จำนวนสมาชิก สถานะ ยอดรายปี ประวัตินำเข้าและการแก้ไขล่าสุด
8. /admin/members ค้นหาชื่อ เลขสมาชิก เลขบัตร สังกัด กรองสถานะ/ปี แบ่งหน้าและปิดบังเลขบัตรในตาราง
9. /admin/members/:id แก้ข้อมูลสมาชิก ตรวจเลขบัตร/สมาชิกซ้ำ sync username เมื่อเปลี่ยนเลขบัตร ระงับ/พ้นสมาชิกปิดการเข้าใช้ รหัสเดิมคงอยู่
10. แก้ปันผลรายปีได้ ระบบคำนวณยอดใหม่ด้วยจำนวนสตางค์เต็ม ต้องระบุเหตุผล และป้องกันบันทึกข้อมูลรุ่นเก่า
11. บันทึกผู้แก้ เหตุผล เวลา ข้อมูลก่อน/หลังใน member_data_changes และรอบนำเข้าใน member_import_runs
12. เพิ่ม role member_admin เฉพาะดูแลสมาชิกและปันผล ไม่มีสิทธิ์ CMS หรือบัญชีผู้ใช้หลัก
13. สร้างบัญชี memberadmin ใน MySQL local แล้ว บัญชี admin หลักและรหัสเดิมไม่เปลี่ยน รหัสใหม่ส่งให้เจ้าของโดยตรง ไม่อยู่ใน Git/Prompt นี้
14. หลังบ้านมีปุ่มเปลี่ยนรหัสของตนเอง login/redirect/navbar รองรับ member_admin
15. PHP ของ Vite ใช้ storage/tmp ที่เขียนได้ ป้องกัน startup notice ปะปน JSON เมื่ออัปโหลดไฟล์

ไฟล์สำคัญ
- app/Services/DividendImportService.php, MemberAdminService.php, MemberDataException.php
- app/Controllers/Admin/DividendImportController.php, MemberManagementController.php
- database/migrations/014_member_admin_history.sql, 015_member_admin_role.sql
- config/routes.php, config/permissions.php, AuthController และ AuthContext
- src/pages/AdminMembersDashboardPage.jsx, AdminMembersPage.jsx, AdminMemberDetailPage.jsx, AdminDividendImportPage.jsx
- src/components/member/AdminMemberShell.jsx, src/pages/AdminMembers.css, src/services/memberAdminApi.js
- bin/member-admin.php สร้าง/รีเซ็ตเฉพาะบัญชี role member_admin

ติดตั้งบนเครื่องอื่น/VPS
สำรองฐานข้อมูลก่อน รัน php bin/console db:migrate และ npm run build
ตั้ง environment variable MEMBER_ADMIN_PASSWORD เป็นรหัสใหม่อย่างน้อย 16 ตัว แล้วรัน php bin/member-admin.php memberadmin
ใช้ --reset เฉพาะเมื่อเจ้าของอนุญาตรีเซ็ตบัญชี member_admin ที่มีอยู่; เครื่องมือปฏิเสธบัญชี role อื่น
อย่ารัน db:seed เพื่อรีเซ็ตรหัส admin อย่านำรหัส local ไปใช้ซ้ำบน production
ฐานข้อมูลและบัญชี local ไม่ย้ายไป VPS อัตโนมัติจาก Git ต้องติดตั้งและนำเข้าข้อมูลแยก

ข้อมูลเดิม local
ปันผล 4,855 รายการ: ปี 2567 จำนวน 2,429 และปี 2568 จำนวน 2,426 ของสมาชิก 2,508 ราย
พัก 4 รายการจากต้นฉบับเพราะเลขบัตรเดียวตรงกับคนละสมาชิก ต้องตรวจเอกสารกับเจ้าของ ห้ามเดาหรือรวมบัญชี
ข้อมูลจริง/credentials อยู่ใน storage/private ซึ่ง ignore ห้ามเผยแพร่ ไม่แนบ Excel จริงขึ้น GitHub

การทดสอบ
npm run build
php tests/DividendImportTest.php
php tests/MemberAdministrationTest.php — สร้างฐานทดสอบสุ่มแยกข้อมูลจริงและลบเฉพาะฐานนั้น ต้องมีสิทธิ์ CREATE DATABASE
php tests/SecurityRegressionTest.php
node tests/NavigationRedirectTest.mjs
tests/MemberAdministrationHttpTest.py — ใช้ MEMBER_ADMIN_USER, MEMBER_ADMIN_PASSWORD, MEMBER_TEST_URL ตรวจ HTTP/CSRF/สิทธิ์/preview ไม่ยืนยันหรือแก้ข้อมูลจริง
ทดสอบ API local แล้วว่า member_admin ถูกปฏิเสธ API ผู้ใช้หลัก dashboard CMS ข่าว และรายงานเฉพาะสมาชิก
ยังต้องตรวจภาพ desktop/mobile/dark mode ในเบราว์เซอร์จริง เพราะเครื่องมือ browser automation ในรอบนี้เชื่อมต่อไม่ได้
ยังไม่ได้ deploy VPS ในรอบนี้

สิ่งที่ให้ทำต่อ
ตรวจ diff และทดสอบ end-to-end ในฐานทดสอบ: login, import ปีใหม่/นำเข้าซ้ำ/เลขซ้ำ/stale preview, แก้ข้อมูลและยอด, audit, suspension และ ownership
ตรวจขอบเขตสิทธิ์ member_admin ทุก endpoint, CSRF และ session หมดอายุ
ตรวจ UI มือถือ/สีโหมดสว่างและมืด/ประวัติแก้ไขให้อ่านง่าย
เสนอแผนเรียงตามความเสี่ยง เช่น MFA, ผู้ตรวจรับรองการแก้ยอด, สำรอง/กู้คืนและอายุประวัติ, รายงานข้อผิดพลาดนำเข้าแบบดาวน์โหลด
แยกสิ่งที่พบจริงจากข้อเสนอ รายงานไฟล์ สาเหตุ ผลกระทบ วิธีแก้ และผลทดสอบ
ก่อน deploy ทำตาม docs/vps-update-prompt.md ห้ามลบ/รีเซ็ตรหัส/แก้ข้อมูลจริงนอกขอบเขตที่เจ้าของอนุญาต
```
