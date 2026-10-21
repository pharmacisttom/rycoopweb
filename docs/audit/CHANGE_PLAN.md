# แผนซ่อมและ acceptance gates

รอบนี้เป็น Phase 0 เท่านั้น ไม่มีการเริ่ม implementation ระยะถัดไปโดยข้าม acceptance criteria

## ลำดับที่เสนอ

| ลำดับ | งาน / เหตุผล | ไฟล์ที่กระทบ | Acceptance |
| --- | --- | --- | --- |
| 0 | จัดพื้นที่ local และ checkpoint งานค้างแยกจาก content/preview | ไม่ลบข้อมูล; Git branches, untracked access module และไฟล์ส่วนบุคคล | build/test runtime เปิดได้; working tree ทุกไฟล์มีเจ้าของงานและสำรองโดยไม่เผยแพร่ secrets/Excel |
| 1 | คืน baseline build และตัดสิน semantics RBAC ที่กำลังทำ | App.jsx, access pages, AccessService/PermissionMiddleware, config/routes.php, migration017 | React build ผ่าน; direct API tests ครอบคลุม role/permission matrix; ไม่มีการถอด route/guard เพื่อหลบ test |
| 2 | Authentication/session ขนาดเล็กก่อน enrollment | Request.php, Auth.php, Session.php, AuthController, AuthContext, security config | password bytes เดิม; login/disabled/no-role/pending/expired/logout/change-password/cross-role ผ่าน; regenerate ทั้งสองช่วง; JSON error ชัดเจน |
| 3 | Complete 2FA ใช้ service เดิม | TwoFactorService, auth controllers/views/React pages, additive migration/config | enrollment reauth+confirm, local QR/no-store, AEAD key outside DB, TTL, OTP limiter/replay, hashed one-time recovery, next-login challenge tests ผ่าน |
| 4 | Route permissions และ IDOR regression | routes, Permission/Role/Csrf middleware, member/LED/admin controllers | deny-by-default matrix ทุก mutating route; method spoof CSRF test; member A อ่าน/แก้ member B ไม่ได้ |
| 5 | Financial imports และข้อมูลอ้างอิง | DividendImportService/controller/tests, rates/products, content branch | independent fixture totals ตรง; precision/rounding ไม่เปลี่ยนตามอำเภอใจ; duplicate confirm/concurrency rollback/audit ผ่าน; UI/API/DB rates และกรรมการตรง reference |
| 6 | Backup/restore และ observability | BackupController, AuditService, scheduler/deploy docs | consistent backup และ restore isolated DB ผ่าน row counts/checksums; encryption/retention/access มีหลักฐาน; ไม่แสดง sensitive logs |
| 7 | Multi-env artifact/TEST package | vite config, API clients, server templates, CI, deployment docs | base / ของ test subdomain, static artifact ไม่มี PHP/secrets, legacy build ยังใช้ได้, no source/config overwrite |
| 8 | UAT และ Production readiness | QA/deployment documents | Critical/High ปิดพร้อมหลักฐาน; restore/rollback ผ่าน; ผู้รับผิดชอบอนุมัติ UAT และ Production แยกครั้ง |

ก่อนสร้าง migration ที่เปลี่ยน live data หรือ deploy TEST/Production ต้องได้รับอนุมัติตาม Master Prompt; local reversible patches หลัง baseline ที่ผ่านทำได้ใน feature branch
ห้ามแก้ Nginx Virtual Host จาก local source development หรือ SSH โดยไม่ได้รับมอบหมาย

## Regression plan

- รักษา existing tests พร้อมรายงาน PASS/FAIL รายคำสั่ง ไม่ใช้ exit code สุดท้ายของ batch แทนผลแต่ละตัว
- ปรับ assertion ที่ผูก RoleMiddleware เฉพาะเมื่อมี behavior tests ยืนยัน permission equivalence
- DB tests ต้อง require testing environment และ explicit disposable database target ก่อน connect; ไม่ clone production financial rows
- ใช้ synthetic member/financial fixtures เท่านั้น; ไม่ใช้ Excel ที่ root
- รอบปิด baseline: syntax all PHP, clean dependency install/manifest validate, frontend build, original security suite, redirect/media tests; DB integration เฉพาะ disposable instance ที่มีพื้นที่และ guard
- browser E2E หลัง build ผ่าน: session expiry, login/OTP/recovery/logout, admin search/import, member isolation และ responsive pages

## เอกสารระยะถัดไป

สร้าง security/AUTHENTICATION_REVIEW, TWO_FACTOR_DESIGN, RBAC_MATRIX และ SECURITY_TEST_RESULTS เมื่อทำ phase นั้นจริง
ใช้ docs/RBAC.md, SECURITY.md, docs/member-dividends.md, docs/AUDIT_LOG.md, DEPLOYMENT.md และ docs/DEPLOYMENT.md ที่มีอยู่เป็นหลัก ไม่สร้างเอกสารซ้ำโดยไม่มีเหตุผล
database/QA/deployment reports ต้องระบุว่าอะไรทดสอบแล้ว อะไรยังไม่ได้ทดสอบ ก่อนสรุปพร้อมใช้งาน

## Rollback / checkpoint

- เอกสาร Phase 0 เป็นไฟล์ใหม่ใน docs/audit; ถอนเฉพาะไฟล์รายงานเหล่านี้ได้เมื่อไม่ต้องการ ไม่ checkout/reset ไฟล์ source ที่แก้ค้าง
- ก่อน implementation ทำ checkpoint เฉพาะงานที่เกี่ยวข้องบน codex/feature branch; ไม่ stage outputs ทั้ง directory หรือไฟล์ Excel, ไม่ force push
- source release ย้อนด้วย revert commit ที่ระบุและ build artifact รุ่นก่อนหน้า; ห้าม reset --hard working tree ร่วม
- schema rollback ต้องจัดแผนก่อน migration และทดสอบ isolated DB; additive security fields ไม่ลบประวัติ recovery/audit โดยอัตโนมัติ
- live deploy ต้อง backup release/config/DB และมีการอนุมัติทั้ง TEST ที่ทำงานอยู่และ Production; ไม่เขียนทับ .env

## ปัญหาจากงานเนื้อหาก่อนหน้า (ยังไม่ปิด)

worktree public-content-verification build ผ่าน แต่ PublicContentReferenceTest.mjs ล้มเพราะ LoansPage แสดงเพียง 3 categories จึงไม่มีวงเงินสินค้า 300,000 ใน rendered output; ต้องแก้ page mapping ก่อนรับงาน
เครื่องมือ public-content-sync.php ที่เตรียมไว้ยังไม่ได้ทดสอบ MySQL integration และอาจแก้ fields ของรายการเดิม ต้อง review diff/scope/backup/restore ก่อนใช้ ห้ามรันบน Production
