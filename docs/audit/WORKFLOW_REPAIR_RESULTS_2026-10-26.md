# ผลซ่อม workflow — 26 ตุลาคม 2569

พัฒนาใน `D:\rycoopweb\outputs\workflow-repair` branch `codex/workflow-repair` จาก HEAD เดิม `38031d9` เพื่อคง checkout หลักและงานค้างไว้
เก็บงาน Google/MFA/Staff เดิมเป็น checkpoint `3483877` และรวมข้อมูลพื้นฐานจาก branch ที่ตรวจเว็บเดิมแล้วเป็น `e6b9b20`
สถานะ: ผ่านการทดสอบภายในเครื่อง พร้อมเปิด PR เข้า Test แบบ draft; ยังไม่ใช่การรับรอง Production และไม่ได้ deploy/migrate/seed/เปลี่ยนบัญชีจริง

## ผลงานที่ทำ

| Workflow | สิ่งที่แก้ | เกณฑ์ยืนยัน / ขอบเขต |
| --- | --- | --- |
| Login → MFA → Workspace | เพิ่ม enroll/verify/recovery/legacy 2fa alias, โหลด identity จาก PHP หลัง MFA, ค้นสถานะ partial session เมื่อเปิด browser ใหม่, Google login แสดงเมื่อ configured จริง | หน้า render ผ่าน, partial/full session และ recovery มี SQLite tests; Google callback จริงยังต้อง UAT |
| MFA safety | อายุ challenge 10 นาที, session version/active status, atomic TOTP slice และ recovery consumption, secret 160-bit, recovery 128-bit, encryption ไม่มี static fallback, enrollment ไม่ทับ MFA เดิม | ทดสอบ enroll/confirm/valid OTP/replay/expired/revoked/suspended/absolute lifetime; QR local ไม่มีบริการภาพภายนอก |
| Role / permission | เลิก whitelist role ที่ Login; custom role ไป workspace; API สมาชิก/ปันผล/ข่าว/FAQ ใช้ Auth + Permission และ CSRF เมื่อเขียน ส่วนจัดการสิทธิ์ยังจำกัด super_admin | middleware behavior 18 ข้อ + route/permission assertions; ไม่มีการแจก permission ให้สมาชิกทั่วไป |
| Member requests | ย้าย /requests ก่อน /{id} | ทดสอบ first-match ของ router จริง ไม่อ่านข้อมูลสมาชิก |
| Session / password | background probe ไม่ refresh/unmount form และไม่ต่อ idle เอง, default idle 20 นาที, owner-only password change/ตรวจ hash ปัจจุบัน/รักษาช่องว่างรหัสผ่าน, เชื่อ IP เฉพาะ proxy ที่ระบุ | ownership/empty hash/IP spoof tests; ยังต้องทดสอบเครือข่ายจริงและ Nginx/session cookie บน TEST |
| ข้อมูลพื้นฐาน CMS | ข้อมูลสหกรณ์ กรรมการ เจ้าหน้าที่ ที่ปรึกษา ผู้ตรวจสอบ คณะย่อย ดอกเบี้ย ผลิตภัณฑ์: เพิ่ม/แก้/นำออก/เรียง/preview/ร่าง/เผยแพร่/วันที่มีผล-สิ้นสุด/ประวัติ/สร้างร่างจากเวอร์ชันเดิม | ร่างไม่เปลี่ยนหน้าจริง, permission แยก update/publish, version conflict, audit failure rollback; super_admin ยังทำได้ทั้งสองขั้น ไม่บังคับคนละคน |
| ดอกเบี้ย / ผลิตภัณฑ์ | React ใช้ public catalogue กลาง; JSON APIs อัตรา/ผลิตภัณฑ์/บุคลากร/ข้อมูลสหกรณ์อ่านชุดเดียวกัน, ผูกรหัสผลิตภัณฑ์คงที่เมื่อเปลี่ยนชื่อ; API แก้ไขอัตราเดิมสร้างร่างแทน partial multi-table write | calculator/API/rate parity, future/expired publication tests; ไม่เขียนทับบัญชีเงินฝากหรือสัญญาเงินกู้ของสมาชิก ไม่ลบ legacy tables/controllers |
| ข่าว | create/update เริ่ม draft/submitted, preview → publish ด้วย news.publish, optimistic version + transaction + strict audit; legacy news forms ไม่ส่ง published ข้ามขั้น | 11 assertions รวม unauthorized/stale/repeat/audit rollback; เมื่อแก้ข่าวออนไลน์จะพักเป็นร่างจนอนุมัติใหม่ ยังไม่มี parallel draft คงฉบับเดิมออนไลน์ |
| Excel import | คง flow preview/confirm เดิม ปรับ test contract ให้รองรับ XLSX template ว่าง และ CSV ที่ยังมีแถวตัวอย่างซึ่งต้องแก้ก่อนนำเข้า | parser/ปี/ศูนย์นำหน้า/ยอด/เลขซ้ำ/คอลัมน์/ตัวอย่างผิด 14 ข้อ; ไม่ได้ทดสอบ confirm/concurrency บน MySQL จริงในรอบ local นี้ |
| CI / deploy | PHP matrix 8.3/8.5 + MySQL 8.0, เพิ่ม workflow/security tests, แยก default feature contract จาก CI explicit flags, Composer audit และ npm High/Critical gate; release script ต้องรับ approval/UAT/restore SHA ตรงกันก่อนรัน | bash syntax และ no-approval refusal ผ่าน ไม่มีการ deploy; ยังเป็น in-place script ไม่ใช่ atomic release/automatic rollback |
| Backup | ตรวจ incomplete write/checksum, ไฟล์ dump สิทธิ์จำกัดและชื่อไม่ชน | ไม่ได้รัน backup/restore จริง และ CLI dump ยังไม่ใช่ consistent snapshot ที่พิสูจน์ recovery แล้ว |

## ผลทดสอบจริงในเครื่อง

Runtime local: PHP 8.2.12; Node/npm ตามเครื่องปัจจุบัน Target PHP 8.3/MySQL 8 รอ CI/TEST ยืนยัน
SQLite fixtures ใช้หน่วยความจำ ไม่เชื่อม MySQL ที่ตั้งค่าไว้; integration tests ที่ต้องใช้ MySQL เพิ่ม guard บังคับ APP_ENV=testing และชื่อฐานข้อมูล test/ci ที่ระบุชัด
ไม่รัน GoogleAuthIntegrationTest กับฐานข้อมูลจริงหรือส่งคำเชิญ/อีเมล

| Command | ผล | รายละเอียด |
| --- | --- | --- |
| `php tests/SecurityRegressionTest.php` | PASS | Security regression tests passed. |
| `php tests/MediaUrlTest.php` | PASS | Media URL tests passed (11 cases). |
| `php tests/PublicContentWorkflowTest.php` | PASS | PASS: audit failure leaves public data unchanged |
| `php tests/WorkflowRoutesTest.php` | PASS | Workflow route checks: 357 passed |
| `php tests/PermissionBehaviorTest.php` | PASS | PASS: method spoof with valid CSRF still works |
| `php tests/RequestSecurityTest.php` | PASS | PASS: ordinary form text is trimmed |
| `php tests/MfaWorkflowTest.php` | PASS | PASS: absolute session lifetime cannot be extended by activity |
| `php tests/TwoFactorServiceTest.php` | PASS | All TwoFactorService tests passed successfully! |
| `php tests/PasswordWorkflowTest.php` | PASS | PASS: empty password hash cannot bypass reauthentication |
| `php tests/NewsWorkflowTest.php` | PASS | PASS: audit failure rolls back publication |
| `php tests/QrCodeGeneratorTest.php` | PASS | QrCodeGenerator tests passed. |
| `php tests/GoogleAuthCoreTest.php` | PASS | PASS: independent URL-safe verifiers |
| `php scripts/check-seed-assets.php` | PASS | Validated 11 seeded static asset(s). |
| `node tests/PublicContentReferenceTest.mjs` | PASS | PASS: reference values, board groups and rendered public pages |
| `node tests/WorkflowFrontendTest.mjs` | PASS | PASS: custom role login and session contracts |
| `php tests/FeatureFlagContractTest.php` | PASS | PASS: explicit CI flags override login defaults |
| `php tests/DividendImportTest.php` | PASS | PASS: csv example template requires real member data |
| `php bin/test_lint.php` | PASS | ✓ All 164 PHP files passed syntax validation successfully with 0 errors! |
| npm ci (lockfile สุดท้าย) | PASS | ติดตั้งใน worktree แยก |
| npm run build | PASS | Vite 6.4.4 / 2688 modules |
| composer validate --no-check-publish | PASS | manifest valid |
| composer audit --locked | PASS | advisories 0 / abandoned 0 |
| bash -n + เรียก deploy script โดยไม่กำหนด approval | PASS | ปฏิเสธก่อน cd/backup/network/deploy |
| git diff --check | PASS | ไม่มี whitespace error |

ผล Node render ใช้ SSR และ mocked auth context เพื่อยืนยัน route/permission contract ไม่ใช่ browser E2E หรือ visual responsive QA
จำนวน route assertions ไม่ใช่จำนวน HTTP requests ที่ทดสอบสำเร็จ และการ build ไม่ยืนยันการเชื่อมฐานข้อมูลจริง

## Dependency security

อัปเดต DOMPurify 3.4.16 → 3.4.17, Vite 6.4.3 → 6.4.4 และ source-map-js ภายในช่วง dependency เดิม
npm audit ก่อนแก้: Critical 1 / High 2 / Moderate 5; หลังแก้: Critical 0 / High 0 / Moderate 5 (package entries ไม่ใช่จำนวนช่องโจมตีที่พิสูจน์แล้ว)
ที่ยังเหลือ: react-router/react-router-dom และ Tailwind/postcss-nested/postcss-selector-parser chain การแก้ที่ registry เสนอมี Router 7 / Tailwind 4 ซึ่งต้องแยกวางแผน compatibility และทดสอบ routes/layout ก่อน
ไม่ได้รับรองว่าไม่มีช่องโหว่อื่นหรือว่า VPS ไม่ถูกโจมตี รอบนี้ไม่สแกนโจมตีเว็บจริง ไม่อ่าน production logs และไม่เปลี่ยน firewall/headers บน VPS

## Migration และ release ที่ยังต้องทำ

- 022: retry-safe MySQL 8 conditional column additions; ห้ามรันจริงโดยไม่ได้ review schema ปัจจุบันและอนุมัติ
- 023: เพิ่ม revision table/permissions เท่านั้น ไม่ drop/truncate/seed; ต้องให้ผู้ดูแลกำหนด custom editor/publisher roles ตามหน้าที่จริง
- CMS เก็บข้อมูลสาธารณะเป็น catalogue/revisions แยกจาก legacy CRUD tables โดยตั้งใจไม่เขียนทับข้อมูลบัญชีสมาชิก Legacy PHP HTML บางหน้าที่ยังอ่านตารางเดิมต้องตรวจ route ที่ VPS ใช้ก่อนเปิดให้เข้าถึงคู่กับ SPA
- CI remote และ UAT ยังไม่ยืนยัน ณ เวลาจัดทำรายงานนี้ ให้ตรวจสถานะ PR ของ SHA ที่จะ deploy เสมอ
- HTTPS/OAuth callback/browser responsive/HTTP image checks/financial import transactions/restore drill และ release switch บน VPS ยังต้องทำบน TEST
- ขั้นตอน deployment, configuration, acceptance และ rollback อยู่ใน `docs/deployment/WORKFLOW_RELEASE_2026-10-26.md`
- รายการไฟล์เปลี่ยนเทียบ checkout เดิมอยู่ใน `docs/audit/WORKFLOW_CHANGED_FILES_2026-10-26.txt`

ไม่มีเหตุให้รัน destructive database commands หรือเปลี่ยน .env/รหัสผ่านจริงจากรายงานนี้
