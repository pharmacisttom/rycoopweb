# RYCOOPWEB 2.1 — Phase 0 Baseline

วันที่ตรวจ: 21 ตุลาคม 2569; source หลัก `D:\rycoopweb`

## ข้อสรุป

ยังไม่พร้อมสำหรับ deployment หรือเริ่ม refactor security จนกว่าจะจัดการ baseline ที่ build ไม่ผ่านและยืนยันสิทธิ์ของงาน RBAC ที่ยังไม่ commit
ใน Phase 0 นี้ไม่ได้แก้ source, migration, credentials หรือ VPS; เพิ่มเฉพาะเอกสาร inventory/audit
งานแก้เนื้อหาเว็บจากคำสั่งก่อนหน้าเก็บค้างใน worktree แยก ยังไม่พร้อมเผยแพร่

## Git และงานค้างก่อน audit

- branch หลัก `codex/member-portal-integration`, HEAD `6bd0451` (Add member portal request APIs and scoped authorization bridge)
- commit ก่อนหน้า `1ab6bf9`, `8f23ca0`, `2e272f6`
- Modified: `app/Controllers/Admin/AuthController.php`, `app/Controllers/Admin/MemberManagementController.php`, `app/Controllers/Api/SpaApiController.php`, `app/Core/Session.php`, `config/routes.php`, `config/security.php`, `src/App.jsx`, `src/context/AuthContext.jsx`
- Untracked source: `app/Controllers/Admin/AccessController.php`, `app/Middlewares/PermissionMiddleware.php`, `app/Services/AccessService.php`, `database/migrations/017_access_management_permissions.sql`
- Untracked `outputs/` และ Excel ปันผลสองไฟล์ที่ root; ไม่อ่านข้อมูลส่วนบุคคลและไม่ stage/commit
- worktree เนื้อหา `outputs/public-content-verification`, branch `codex/public-content-verification` ที่ `1ab6bf9`: มีงาน source/docs/test ที่ยังไม่ commit จากคำสั่งก่อนหน้า; ไม่แก้เพิ่มระหว่าง Phase 0
- worktree preview `outputs/multi-environment-preview`, branch `codex/multi-environment-preview` ที่ `d021501`: ไม่ใช่ baseline ของ working tree หลัก
- ห้ามสรุปว่า source ที่ deploy ตรงกับ branch ใดจนกว่าจะได้ revision/artifact manifest จาก VPS

## Inventory ที่ตรวจจากไฟล์จริง

| กลุ่ม | จำนวนไฟล์ |
| --- | ---: |
| PHP controllers | 59 |
| Core | 12 |
| Services | 23 |
| Middleware | 6 |
| Models | 0 |
| React pages | 36 |
| React components | 37 |
| Contexts / hooks / API clients | 3 / 1 / 3 |
| SQL migrations | 19 |
| Test entry files | 13 |
| PHP registered routes | 253 |

รายการเต็มอยู่ใน `inventory.json`; inventory นับไฟล์ ไม่เท่ากับจำนวน test cases หรือ coverage
ไม่พบ directory `views/` ที่ View resolver ใช้; พบ literal render references 96 จุดที่ไม่มีไฟล์ปลายทาง ต้องประเมินแต่ละ route ว่า React intercept หรือ PHP render จริง ห้ามตีความว่าทั้ง 96 หน้าถูกเรียกใช้งานจริงทั้งหมด

## ผลตรวจและทดสอบที่รันจริง

| คำสั่ง | ผลจริง | ขอบเขต |
| --- | --- | --- |
| `php tests/SecurityRegressionTest.php` | FAIL 10 checks | route assertions คาด RoleMiddleware แต่ working tree ใช้ PermissionMiddleware บาง route; ไม่ใช่ข้อพิสูจน์ว่า authorization ถูก bypass |
| `php tests/DividendImportTest.php` | FAIL | ผ่าน 12 checks ก่อน exception ตอน parse XLSX แม่แบบว่างที่ test บรรทัด 31; ต้องตรวจ expectation ของแม่แบบ ห้ามเปลี่ยนสูตรเพื่อให้ผ่าน |
| `php tests/AuthRedirectRegressionTest.php` | FAIL | ไม่พบข้อความ redirect default ไป staff ที่ test คาด ต้องเทียบ semantics ของงาน RBAC |
| `node tests/NavigationRedirectTest.mjs` | PASS 8 cases | redirect utility เท่านั้น |
| `php tests/MediaUrlTest.php` | PASS 11 cases | URL utility เท่านั้น |
| `php tests/MemberDeploymentCompatibilityTest.php` | FAIL / environment | พยายามสร้างตารางใน DB ชั่วคราว `rycoop_member_test_*`; MySQL errno 135 No more room in record file; ไม่ผ่าน integration และไม่ยืนยันสภาพ DB จริง |
| `npm run build` | FAIL | resolve `./pages/AdminAccessPage` ไม่ได้; static scan พบ `./pages/AdminWorkspacePage` ไม่มีด้วย |
| `npm audit --json` | FAIL (advisories) | 11 packages: High 7, Moderate 4, Critical 0; ยังไม่ได้แก้ dependency |
| `php -v` | PHP 8.2.12 CLI | ไม่ตรงเป้าหมาย PHP-FPM 8.3 ที่ผู้ใช้แจ้ง; ไม่ได้ตรวจ runtime บน VPS |

ไม่มีการรัน migration, Seeder, restore หรือแก้ Production DB โดยผู้ตรวจ
integration test ข้างต้นใช้ source connection เพื่อ clone schema และพยายามสร้างฐานข้อมูลชั่วคราว; หลังพบปัญหาหยุดทดสอบฐานข้อมูลเพิ่มเติม ไม่ใช้ข้อมูลสมาชิกจริงใน fixtures
ไม่มี npm ci/composer validate/audit รอบใหม่นี้สำเร็จให้รายงาน; ไม่อ้างผลจาก worktree เก่าแทน
ในช่วงท้าย `exec_command` เปิด process ไม่ได้เพราะ OS error 112 พื้นที่ดิสก์ไม่พอ; Node REPL ก็เริ่ม process ไม่สำเร็จ จึงยังตรวจ syntax ทั้งโครงการและ capacity เพิ่มไม่ได้

## Secrets และขอบเขตที่ยังไม่ยืนยัน

ตรวจเฉพาะชื่อไฟล์จาก Git ไม่แสดงเนื้อหา secrets: tracked พบ `.env.example`; ไม่พบชื่อ untracked secret-like นอก outputs; `.env`, `.env.local`, `.env.production`, `.env.test` ไม่พบใน root นี้
นี่ไม่ใช่การรับรองว่า Git history หรือไฟล์อื่นไม่มี secret; ยังไม่ได้ full content/history scan
ไม่ได้ SSH, ตรวจ Nginx/FPM, Basic Auth, certificate renewal, cron, DB schema จริง, cookie จริง หรือ restore backup ของ VPS
ผลอ่าน public URL จากงานก่อนหน้า: www.rayongcoop.org หน้าแรก 200 แต่สอง public APIs 404; ยังไม่พิสูจน์ server configuration ต้นเหตุ

## เกณฑ์ปิด Phase 0

inventory, architecture, registered routes, risks และแผนแก้พร้อมแล้ว แต่ baseline build/regression ยัง FAIL และ server parity ยังไม่ยืนยัน
จึงไม่อนุมัติ Production readiness และยังไม่เริ่ม Phase 1 ในรอบนี้
ก่อน implementation ให้สำรองงานค้างแยกตามเจ้าของงาน สร้าง feature branch/checkpoint ที่ review ได้ และแก้ baseline พร้อม tests โดยไม่ทับไฟล์เดิม
