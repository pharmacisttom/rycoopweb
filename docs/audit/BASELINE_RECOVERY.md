# ผลซ่อม baseline หลังแก้พื้นที่ดิสก์ — 21 ตุลาคม 2569

## ขอบเขต

ทำใน `codex/baseline-recovery`, worktree `D:\rycoopweb\outputs\baseline-recovery`
checkpoint `f4d101d` เก็บ source งาน RBAC เดิม 12 ไฟล์ก่อนแก้; เทียบข้อความกับโครงการหลักแล้วตรงกันทั้ง 12 ไฟล์ โดยคำนึงถึง Git CRLF normalization
โครงการหลักและงาน content/preview แยกยังไม่ถูกเขียนทับ ไม่ได้รัน migration, seed, restore, เปลี่ยนบัญชี/รหัสผ่าน หรือ deploy TEST/Production

## สิ่งที่ซ่อม

- เพิ่ม AdminAccessPage ที่ imports เดิมเรียกแต่ไม่มี: แสดง Role/สิทธิ์และบัญชีเจ้าหน้าที่, เพิ่ม/แก้/ลบผ่าน API เดิมพร้อม CSRF และ optimistic version; ไม่ดึง/แสดงรหัสผ่านเดิม ไม่ส่ง password ตอนแก้บัญชี ไม่ส่ง request ระหว่าง render
- เพิ่ม AdminWorkspacePage แสดงทางเข้าตาม permission; ใช้ dashboard ข่าวเดิมผ่าน route `/admin/content`; ไม่สร้างระบบข่าวหรือการเงินทดแทน
- ป้องกัน custom role ถูก redirect กลับหน้า staff ที่ตนไม่มี role ซ้ำ ๆ; ปฏิเสธแล้วกลับ workspace ซึ่งแสดงรายการตามสิทธิ์
- ย้าย `/api/admin/members/requests` ก่อน `/{id}` เพื่อไม่ถูกตีความเป็นรหัสสมาชิก
- แก้ security assertions ให้ตรวจ Auth + permission + CSRF ตาม routes จริง โดยมี test middleware behavior ประกอบ ไม่ถอด RoleMiddleware จาก routes ที่ยังต้องใช้ role
- แก้ test XLSX/CSV ตามแม่แบบเจ้าหน้าที่ปัจจุบันที่เป็นไฟล์ว่าง: ต้องถูกปฏิเสธก่อน import; ไม่เปลี่ยนสูตร/validation หรือใส่ข้อมูลสมาชิกจริงเพื่อทำให้ผ่าน
- ปรับ redirect assertion ตาม workspace ที่งาน RBAC เดิมตั้งไว้ และตรวจ feature flag ให้เคารพค่าปิดที่ CI ระบุ
- เพิ่ม test guard ก่อนเชื่อมฐานข้อมูลใน integration entrypoints 4 ไฟล์: ต้อง APP_ENV=testing และระบุ DB_DATABASE ที่เป็น test/CI โดยตรง
- เพิ่ม CI ให้รัน permission/CSRF behavior, auth/dividend regression, page render และ navigation tests; ใช้ PHP 8.3 ให้ตรงเป้าหมาย พร้อม pdo_sqlite สำหรับ memory fixtures

## ผลที่รันจริง

| คำสั่ง / การตรวจ | ผล |
| --- | --- |
| `npm ci --ignore-scripts --no-audit --no-fund` | PASS ใน worktree แยก; รอบแรก sandbox ล้มเหลว แล้ว retry ที่อนุญาต network สำเร็จ; ไม่แก้ lockfile |
| `npm run build` | PASS; รวม chunks ของ AdminAccessPage และ AdminWorkspacePage |
| `php tests/PermissionBehaviorTest.php` | PASS 18 cases; SQLite memory เท่านั้น ไม่เชื่อม MySQL |
| `php tests/SecurityRegressionTest.php` | PASS; route role/permission/auth/CSRF checks และ feature flags |
| `php tests/DividendImportTest.php` | PASS 14 cases; synthetic arrays และแม่แบบว่าง |
| `php tests/AuthRedirectRegressionTest.php` | PASS |
| `node tests/NavigationRedirectTest.mjs` | PASS 8 cases |
| `php tests/MediaUrlTest.php` | PASS 11 cases |
| `node tests/BaselinePagesTest.mjs` | PASS; render pages จริงกับ fixture context, ตรวจลิงก์ตาม permission และ loading state; ไม่ใช่ browser E2E |
| Integration guard negative tests | PASS 4 entrypoints ปฏิเสธ APP_ENV=production ด้วย exit 2 ก่อน DB access |

ตรวจ PHP syntax 138 ไฟล์ด้วย `php -l` ผ่านทั้งหมด; `git diff --check` ผ่าน
SecurityRegressionTest รันซ้ำด้วย FEATURE_MEMBER_PORTAL=false และ FEATURE_MEMBER_LOGIN=false ตาม CI ผ่าน
ไม่ถือว่า MySQL integration ผ่านจาก memory tests

## ข้อจำกัด / ยังไม่พร้อม deploy

- ยังไม่ได้ทดสอบ role/account CRUD transactions กับ MySQL หรือ browser UI interactions ทั้ง workflow; ต้องทำบน disposable database และ TEST
- migration 017 เป็นงานเดิมที่เพิ่งเก็บ checkpoint ยังไม่ได้รันจริง; เมื่อยังไม่ติดตั้ง permissions API อาจรายงานฐานข้อมูลไม่พร้อม ต้องขออนุมัติก่อน migration ที่กระทบ live data
- ยังมีงาน 2FA, password whitespace, session lifetime, secret storage, backup restore, dependency advisories และ mojibake ใน source เดิมตาม RISK_REGISTER
- ยังไม่ยืนยัน VPS revision/config, Nginx, FPM, Basic Auth, TEST/Production cookie isolation หรือ backup restore
- งาน rates/board/content ยังอยู่ branch `codex/public-content-verification` และยังมี test ที่ต้องแก้ ไม่รวมเป็นข้อมูลที่แก้บนเว็บจริงในรอบนี้
- ไดรฟ์ C อ่านค่าพื้นที่ว่างได้ประมาณ 86 MB ตอนเริ่มรอบซ่อม จึงใช้ npm cache และ temp ใน D; ไม่ได้ลบไฟล์เพื่อเพิ่มพื้นที่

## เกณฑ์ก่อนเริ่ม security implementation ถัดไป

source build และ regression ที่ระบุผ่านแล้ว แต่ต้องทดสอบ role CRUD แบบ isolated MySQL, review baseline patch และจัด environment ที่ปลอดภัย ก่อนรับรอง RBAC ทั้งระบบ
Phase 1/2 ยังไม่ถูกประกาศว่าผ่าน ไม่มีการเปิด mandatory 2FA หรือแก้ credentials ในรอบนี้

## Rollback

แยก commit ซ่อมออกจาก `f4d101d`: revert เฉพาะ commit ซ่อมเพื่อกลับ checkpoint RBAC ก่อนซ่อม โดยไม่ reset โครงการหลัก
ไม่มี schema/data change ให้ย้อนในรอบนี้ ห้ามใช้ Seeder หรือ drop database เพื่อย้อน source
หากนำ patch เข้า Test ให้ build ใหม่จาก revision ที่เลือก; เก็บ release ก่อนหน้าและขออนุมัติ deployment ตามปกติ
