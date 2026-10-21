# Risk register — 21 ตุลาคม 2569

Verified หมายถึงพบใน source/คำสั่งจริง ไม่ใช่การยืนยัน exploit บน Production; Suspected ต้องทดสอบเพิ่มเติม
ไม่พบหลักฐานเพียงพอจัดเป็น Critical ในรอบนี้ ไม่เท่ากับรับรองว่าไม่มี Critical

| ID | ระดับ | สถานะ / หลักฐาน | ผลกระทบและแนวทาง |
| --- | --- | --- | --- |
| H01 | High | Verified: `src/App.jsx` import AdminAccessPage/AdminWorkspacePage ที่ไม่มี; build ล้มเหลว | ไม่มี release จาก working tree นี้ได้; จัดการงาน RBAC ค้างก่อน security refactor ไม่ลบ route ทิ้งเพื่อให้ผ่าน |
| H02 | High | Verified: AuthController render auth.two_factor; View resolver ต้องใช้ views/auth/two_factor.php แต่ไม่มี views/ | Pending-2FA flow อาจจบด้วย error เมื่อ PHP route ทำงาน; ทดสอบ React/PHP routing และเพิ่มหน้าที่เข้ากันได้ |
| H03 | High | Verified latent: TwoFactorService::getQrCodeUrl ใส่ otpauth secret ใน api.qrserver.com URL; ไม่พบ call site ใน source ที่ค้น | หากนำ helper ไปใช้ secret จะออกนอกระบบ; ยังไม่อ้างว่าเกิด leak จริง; ใช้ QR ภายในและทดสอบไม่มี external request |
| H04 | High | Verified gaps: verifyCode ใช้ time ± discrepancy และ hash_equals แต่ไม่มี accepted-step/replay storage, dedicated OTP limiter, recovery/enrollment flow ที่ค้นพบ | ต้องออกแบบ lifecycle, pending TTL, AEAD/key management, enrollment confirmation และ one-time recovery พร้อม DB tests ก่อนเปิด mandatory 2FA |
| H05 | High | Verified: Request::sanitizeArray trim ทุก string; AuthController อ่าน password ผ่าน input | รหัสผ่านหัวท้าย whitespace เปลี่ยนก่อน verify; ต้องมี raw password accessor แยกจากการ sanitize และทดสอบอักขระเดิม |
| H06 | High | Verified gap: Auth::verify2FA ตั้ง flag แต่ไม่ regenerate session; Session age rotation รีเซ็ต created timestamp แทน absolute authenticated lifetime | ต้องแยก auth start/rotation timestamp และ regenerate หลัง OTP; ห้ามลด idle/session controls |
| H07 | High | Verified: 10 RoleMiddleware assertions FAIL หลัง uncommitted PermissionMiddleware routes; exploitation unproven | ต้องสร้าง matrix role+permission จริงและทดสอบ direct API ทั้ง allow/deny, IDOR; ห้ามถอด middleware/แก้ assertion อย่างเดียว |
| H08 | High | Verified: npm audit 11 packages, 7 High/4 Moderate | รวม Vite, Tailwind dependency chain และ router; ประเมิน exposure แยก build tool/runtime อัปเดตอย่างมี tests ไม่ใช้ audit fix --force |
| H09 | High | Verified source mismatch: public data mock/seed vs rayongcoop.com; seeded boards บางชื่อเป็นตำแหน่งงาน | เนื้อหาการเงินคลาดเคลื่อน; งานแก้แยกยังไม่เสร็จ ต้องเทียบ approved source/DB/API/UI และไม่สมมติอัตราปันผล |
| H10 | High | Verified: BackupController import App\Services\Logger แต่ logger จริงอยู่ App\Core; SQL dump มี DROP TABLE และ restore exec dump โดยตรง | เกิด error ใน logging path; restore เสี่ยง partial schema change; ยังไม่พิสูจน์ backup consistency/restore; ต้องทดสอบใน DB แยกก่อนใช้งานจริง |
| H11 | High | Verified: OS error 112 disk full และ MySQL temporary-table setup errno 135 | local QA/integration ไม่สำเร็จ ต้องตรวจ capacity โดยผู้ดูแล; ไม่ลบข้อมูลเพื่อทำให้ tests ผ่าน |
| M01 | Medium | Verified: session secure default false, name คงที่; domain/save_path ใช้ env | ความแยก TEST/Prod และ Secure cookie ต้องตรวจค่าจริง; host-only default ว่างเป็นผลดี แต่ไม่อ้างว่า VPS ตั้งถูกแล้ว |
| M02 | Medium | Verified: AuthMiddleware pending-2FA redirect ก่อน AJAX branch; /api/auth/me เรียก Auth::check จึงยัง 401 | pending protected API ไม่ได้ machine-readable TWO_FACTOR_REQUIRED; me ไม่ให้ authenticated ก่อน OTP ตาม source; ต้อง integration test ก่อนรับรอง |
| M03 | Medium | Verified: Auth::check เช็ก session flag ไม่ query status จน Auth::user; multiple roles SELECT LIMIT 1 ไม่มี deterministic precedence | ต้องตรวจ disabled-account session และ multi-role semantics ของทุก consumer; ยังไม่ยืนยัน privilege escalation |
| M04 | Medium | Verified: DividendImportTest ล้มที่แม่แบบว่าง; normalize ใช้ float/round แปลง cents | ไม่อ้างความผิดสูตรจาก float อย่างเดียว; ต้อง independent fixtures, decimal boundary, duplicate confirm/transactions concurrency |
| M05 | Medium | Verified: ขาด views ทั้ง directory; 96 literal render references และ React/PHP duplicate routes | ต้อง map reachable handlers เพื่อแยก legacy ที่ยังใช้; ห้ามลบ module จาก static scan อย่างเดียว |
| M06 | Medium | Verified: production Vite base /app/ และ output public/app ไม่ตรง static artifact แยกที่ร้องขอ | เตรียม environment build โดยรักษา legacy compatibility และไม่ล้าง PHP public root |
| M07 | Medium | Verified gap: DB tests บางไฟล์เชื่อม source connection เพื่อ clone schema โดยไม่มี APP_ENV/test DB guard ก่อนเชื่อม | ห้ามรันกับ config ที่ไม่ทราบ; เพิ่ม guard ก่อน integration รอบหน้า |
| M08 | Medium | Suspected: backup encryption, retention, consistent snapshot, restore checksums ไม่มีผลทดสอบให้รับรอง | ขอหลักฐาน scheduler/permissions และทำ restore drill บน isolated DB; dashboard ขึ้น Auto Backup ไม่ใช่หลักฐาน |
| L01 | Low | Verified: PHP CLI 8.2.12, docs อ้าง 8.4, เป้าหมาย 8.3 | ต้องทดสอบจริง PHP 8.3 ก่อน release |
| L02 | Low | Verified: mojibake ใน uncommitted SpaApiController | แก้ encoding เป็น patch เฉพาะ หลัง checkpoint และ regression ไม่ rewrite ทั้งไฟล์ทับงานค้าง |

## Positive evidence / ข้อจำกัด

Auth::login regenerate session; password_verify มีใช้; Auth::user กรอง active/deleted; /api/auth/me ใช้ Auth::check; role ที่หายไม่ fallback เป็น super_admin
Router รวม middleware group จริง; CSRF middleware ตรวจ state-changing methods และไม่พบการ bypass AJAX login จาก security tests
Dividend import มี preview token/expiry, transaction/rollback, FOR UPDATE และ UNIQUE member/year ใน migration ที่ต้องตรวจ integration ต่อ
ไม่ได้สรุปว่า method spoofing bypass CSRF: Router เปลี่ยน dispatch method แต่ Request ยังรายงาน POST ซึ่ง CSRF ตรวจอยู่ ต้องมี negative test เพิ่ม
ไม่ได้ตรวจช่องโหว่บน VPS หรือใช้ข้อมูลสมาชิกจริงใน automated tests
