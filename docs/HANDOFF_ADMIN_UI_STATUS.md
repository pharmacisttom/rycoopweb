# Prompt ส่งต่อสถานะงาน RYCOOPWEB

คัดลอกข้อความด้านล่างเพื่อส่งต่อให้ ChatGPT/Antigravity ที่ดูแล repository หรือ VPS:

```text
รับช่วงตรวจและเตรียม TEST UAT สำหรับ RYCOOPWEB 3.0
Repository: https://github.com/pharmacisttom/rycoopweb
Base: Test

งานแยกตรวจเป็นสามส่วน โดย branch UI สุดท้ายจัดฐานรวม Slideshow และ Branding ไว้แล้ว:
1. Activity Slideshow — PR #7, branch codex/activity-slideshow
   https://github.com/pharmacisttom/rycoopweb/pull/7
   เพิ่มอัลบั้มข่าว/กิจกรรม จัดภาพปก เรียงภาพ คำบรรยาย alt text
   สไลด์/Lightbox/มือถือ/แป้นพิมพ์ และตรวจ/แปลงภาพก่อนจัดเก็บ
   ใช้ news.gallery_images เดิม ไม่มี migration

2. Global Branding — PR #8, branch codex/fix-global-logo-branding
   https://github.com/pharmacisttom/rycoopweb/pull/8
   รวมตราสหกรณ์เป็น src/assets/branding/coop-logo.webp และ CoopBrandLogo
   แก้ path เก่าใน Navbar/Footer/Login/Member/Admin/MFA และ favicon
   ไฟล์ตรงกับ Production reference ทุกไบต์ ไม่ได้ออกแบบหรือแก้ภาพตราใหม่

3. Admin UI/UX — branch codex/admin-uiux-modernization
   ตรวจ PR และ head SHA ล่าสุดของ branch นี้จาก GitHub ก่อนทำงานต่อ
   ใช้ shared AdminAppShell ร่วมกัน ปรับหน้า Access/ตาราง/แบบฟอร์ม/สิทธิ์
   รองรับมือถือ dark mode และ focus ของ drawer/modal
   เพิ่ม Step-Up MFA UI ตาม endpoint เดิม ยืนยันแล้วลองคำสั่งเดิมซ้ำได้ครั้งเดียว
   ไม่ retry คำสั่งเมื่อ network/401/500 และยกเลิกคำสั่งค้างเมื่อเปลี่ยนหน้า
   แสดงคำขอ Maker/Checker ห้ามอนุมัติคำขอตนเอง และคง Restore policy lock
   แก้การอ่าน staff response และการ hydrate ID/effective permissions จาก server
   โค้ด UI ไม่ได้เปลี่ยน backend security gate หรือ schema
   สาขานี้รวม commit Slideshow เดิมเป็นฐานแล้ว และแก้ conflict หน้าจัดการข่าวก่อนเผยแพร่

เอกสารที่ต้องอ่าน:
- AGENTS.md และ docs/agents/*
- docs/ACTIVITY_SLIDESHOW.md (หลังรวม PR #7)
- docs/audit/GLOBAL_BRANDING_FIX_2026-10-29.md
- docs/audit/ADMIN_UIUX_2026-10-29.md
- docs/ADMIN_UIUX_RELEASE.md

ผลทดสอบในเครื่องและภาพ synthetic presentation fixtures อยู่ในรายงาน
ต้องตรวจ CI ของ head SHA ล่าสุดอีกครั้ง อย่าอ้างผล CI จาก commit เก่า
ภาพทดสอบไม่ใช่การเข้าสู่หน้า TEST จริงและไม่ใช่การข้าม authentication
ผู้ใช้ให้ทำ local QA ก่อน แล้วจึงทำ TEST UAT ภายหลัง

ข้อจำกัดที่ต้องรักษา:
- Role editor เดิมรองรับ catalog บางส่วน บทบาทที่มีสิทธิ์ใหม่จะแสดงอ่านอย่างเดียว
  ห้ามตัดสิทธิ์เดิมทิ้งเพื่อให้บันทึกผ่าน เสนอ API enhancement แยกหากจำเป็น
- Status filter ใน Access เป็น current-page filter ตาม API เดิม
- Detailed staff API ยังจำกัด Super Admin ไม่ได้ขยายสิทธิ์ให้ role อื่น
- Legacy CMS บางส่วนเก็บเฉพาะเบราว์เซอร์ มีป้ายแจ้งและเลิกแสดง KPI สมมติ
- งาน encryption-configuration hotfix เดิมไม่ใช่งานแก้โลโก้/UI ชุดนี้

ห้าม merge/deploy อัตโนมัติ
ห้ามแก้ Production, live TEST symlinks, .env, APP_KEY, MFA encryption key,
บัญชีจริง, shared uploads หรือ migration history
ห้ามรัน migration 025 ซ้ำ; migration 019/020 ยัง ON HOLD
คง BACKUP_RESTORE_EXECUTION_ENABLED=false และ Basic Auth ของ TEST

ขั้นต่อไป:
1. ตรวจ diff, dependency และ CI ของ PR/commit ล่าสุด
2. ตรวจว่า PR Branding ถูก review ก่อน PR UI ซึ่งพัฒนาต่อบน branch นั้น
3. ตรวจการรวม PR Slideshow กับ UI โดยไม่ทิ้งโค้ดของอีก PR
4. เตรียม immutable TEST release ใหม่ พร้อมเก็บ backend/frontend เดิมสำหรับ rollback
5. ขออนุมัติก่อน cutover และทำ UAT ด้วยบัญชี TEST ที่ได้รับอนุมัติ พร้อม MFA
6. ตรวจโลโก้ HTTP 200/image-webp และสิทธิ์/Step-Up/dual-control/Restore lock
7. ตรวจข้อมูลจริงของแต่ละโมดูล ห้ามใช้ภาพ fixture แทนหลักฐาน live UAT
8. รายงานผล ข้อผิดพลาด และแผน rollback ก่อนขออนุมัติ Production แยกอีกครั้ง
```
