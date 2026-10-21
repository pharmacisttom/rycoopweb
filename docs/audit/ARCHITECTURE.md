# Architecture — verified local baseline

## เส้นทาง request

React เริ่มจาก `src/main.jsx` และ `src/App.jsx`; pages เรียก `src/services/api.js`, `memberAdminApi.js`, `ledApi.js`
PHP มี front controller `public/index.php`, bootstrap/Core, Router และ routes ใน `config/routes.php` ไม่ใช่ directory `routes/`
Router รวม middleware จาก group กับ route ก่อน dispatch; inventory effective middleware อยู่ `ROUTE_INVENTORY.md`
Controllers เรียก Services และ `App\Core\Database` (PDO); ไม่พบชั้น Models แยก ไม่จำเป็นต้องเพิ่มเพียงเพื่อเปลี่ยนรูปแบบ
View resolver ใน `app/Core/View.php` ชี้ root `views/` ซึ่งไม่พบใน checkout นี้

## โมดูลหลักและ dependencies

| Domain | React | PHP / data |
| --- | --- | --- |
| Authentication | AuthContext, LoginPage, AuthModal | Admin/AuthController, Core/Auth, Session, Request, Csrf; users/user_roles/roles |
| RBAC | StaffProtectedRoute, uncommitted access routes | RoleMiddleware, untracked PermissionMiddleware/AccessService/AccessController; permissions/role_permissions; migration 017 untracked |
| 2FA | pending redirect ใน AuthContext; ไม่พบ enrollment UI | TwoFactorService, showTwoFactor/verifyTwoFactor; users.two_factor_secret/enabled; view auth.two_factor หาย |
| Members | AdminMembers*, MemberDashboard/Profile | MemberManagementController, MemberAdminService, MemberPortalService, MemberRequestService; members, member_data_changes, profile requests |
| Annual dividend | MemberDividendsPage, AdminDividendImportPage | DividendImportController/DividendImportService; member_dividends, import/audit tables migration 013/014 |
| News / rich text | NewsPage, AdminDashboard, RichTextEditor/Content | News/Faq controllers, RichTextService, MediaService; news, categories, faqs, uploads |
| Rates / loans / board | DepositsPage, LoansPage, BoardPage, calculators, mockData | Public controllers, SpaApiController, admin CRUD, seed data; interest_rates, deposit_products, loan_products, boards |
| LED | AdminLed* and ledApi | LedApiController, LedService/Batch/Matcher/Schema; migration 010 |
| Audit / backup | Admin dashboard | AuditService, AuditLogController, BackupController; audit_logs, storage/backups |

Public controllers และ React pages เป็นการทำงานคู่ขนานที่อาจเป็น legacy compatibility ต้องตรวจ routing จริงก่อนลบ
ข้อมูลบางหน้ามาจาก mockData ขณะที่บางส่วนมาจาก DB/API เป็นสาเหตุที่ข้อมูลไม่ตรงกัน ต้องรวมแหล่งข้อมูลอย่างมี version/source attribution โดยไม่เปลี่ยน DB จริงอัตโนมัติ
README บางข้อกล่าวถึงความสามารถที่ยังไม่พิสูจน์ เช่น 2FA และ backup; เอกสารไม่ใช่หลักฐานว่าทำงานครบ

## Build และ deployment mapping

- `vite.config.js`: dev base `/`, production base `/app/`, output `public/app`, `emptyOutDir: true`; ไม่ใช่ static artifact แยกตาม Master Prompt 2.1
- root-subdomain URL ยังให้บริการ React ที่ `/` ได้ผ่าน server fallback แต่ asset base กับ Router basename ต้องตรวจร่วมกัน ไม่ถือว่า `/app/` เป็น defect เดี่ยว ๆ
- PHP source และ private config ต้องไม่รวม static artifact; ไม่ copy ทั้ง project ไป static root
- `.github/workflows/ci.yml` มี PHP lint/security, MySQL migration/seed บน CI และ React build; ยังไม่ใช่หลักฐาน OTP/recovery/restore E2E
- เป้าหมายที่ผู้ใช้แจ้ง `/var/www/rycoopweb/test-v2`, `test-v2-static`, ports 18081/18443, `test.rayongcoop.org`, DB `rycoopweb_test` เป็น reference ที่ยังไม่ได้ตรวจ VPS
- root DEPLOYMENT.md อ้าง PHP 8.4/โดเมนและ path เก่า ขณะที่เป้าหมายใหม่ PHP 8.3; ต้องจัด environment matrix หลังอ่าน config จริง โดยไม่แก้ Virtual Host ระหว่าง local audit

## สิ่งที่ยังไม่ทราบ

Revision ของ VPS, pending migrations จริง, effective feature flags, session cookie attributes จริง, key storage, scheduler, retention, backup encryption, restore results และ Basic Auth upstream routing
ไม่เรียกบริการ QR ภายนอกหรือสร้าง QR ที่มี secret ระหว่าง audit
