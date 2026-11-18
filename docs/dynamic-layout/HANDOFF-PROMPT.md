# Prompt สำหรับส่งต่องาน

ดำเนินงาน RYCOOPWEB Dynamic Layout & Content Manager ต่อจาก branch `codex/dynamic-layout-gallery` ใน repository `pharmacisttom/rycoopweb` อ่าน `docs/dynamic-layout/PLAN.md`, `ARCHITECTURE.md`, `QA.md`, `SECURITY.md`, `TEST-RUNBOOK.md` และ `PRODUCTION-READINESS.md` ก่อนเริ่ม ตรวจ commit/diff และผล CI ล่าสุด โดยไม่รวมงานเก่าใน workspace ที่ยังไม่ commit

โค้ดเพิ่ม `/app/admin/layout`, section/subsection tree + CRUD/DnD/keyboard ordering, responsive config, draft preview, immutable publication/rollback, nested gallery categories/albums, private safe WebP multi-upload และ public gallery/section renderer แล้ว หน้าแรกเดิมมี 8 blocks จริงและรักษาไว้ครบ การแก้ไขทุกชนิดต้องใช้ version ปัจจุบัน; Publish ต้อง fresh TOTP scope `layout.publish`; Rollback ใช้ `layout.rollback` ห้ามเชื่อ user/actor ID จาก client

TEST ที่ผู้ใช้ยืนยัน: host `119.59.116.32`, user `coopadmin`, profile `rycoop-test`, port คาดว่า 22, URL `https://test.rayongcoop.org`, DB `rycoopweb_test` ขณะส่งต่องานยังไม่มี approved SSH profile/key/trusted known_hosts ในเครื่อง จึงหยุดการเชื่อมต่อ ห้ามเดาหรือสร้าง credential และห้าม StrictHostKeyChecking=no ต้องให้ผู้ดูแลจัดเตรียม connection/key และตรวจ fingerprint อย่างอิสระก่อน

เมื่อเข้าถึงได้ ให้ทำ read-only preflight เท่านั้นก่อน: ตรวจ TEST identity, deployed source ของ layout, symlink, DB name/version/schema, private storage และ dedicated FPM ให้ครบ ห้าม migration/restart/deploy จน preflight ผ่านและได้รับอนุญาตสำหรับผลการเปลี่ยนแปลงที่ตรวจสอบได้ จากนั้น backup TEST และสาธิต acceptance ด้วยข้อมูลที่ persist จริงบน TEST ตาม runbook เก็บ IDs/audit IDs/API status/screenshot/version/rollback evidence

ผลทดสอบ local/fixture และ MariaDB แยกผ่านตาม QA แต่ไม่เท่ากับ MySQL 8 หรือ UAT บน TEST จริง ตรวจ dedicated GitHub CI และบันทึกผลจริง ห้ามประกาศ TEST/Production-ready จาก mock/SQLite อย่างเดียว ไม่แตะ Production credentials/database/services ไม่ merge main และไม่ deploy อัตโนมัติ เมื่อเสร็จให้ push เฉพาะงานที่เกี่ยวข้อง พร้อมรายงานผลจริง ข้อค้าง และ prompt สำหรับส่งต่อ
