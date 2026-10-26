# ตรวจความปลอดภัยซ้ำ — 26 ตุลาคม 2569

## ขอบเขต

ตรวจ checkout หลัก `D:\rycoopweb`, branch `codex/member-portal-integration`, HEAD `38031d9`
ไม่ถือว่า source นี้ตรงกับ VPS จนกว่าจะยืนยัน deployed revision
มีงานค้างที่รักษาไว้: `.env.example`, `config/security.php`, `app/Services/QrCodeGenerator.php`, `config/google.php`, migration 022 และ QrCodeGeneratorTest
worktree CMS ใน `outputs/baseline-recovery` เป็นงานแยก ยังไม่ใช่หลักฐานว่าติดตั้งบน Production แล้ว

รอบนี้ตรวจแบบอ่านอย่างเดียวต่อ source/config/lockfile, รัน tests ที่ไม่ใช้ DB จริง, ทดสอบพฤติกรรมด้วยข้อมูลจำลองใน process และ GET เฉพาะหน้าแรกสาธารณะหนึ่งครั้ง
ไม่ได้รัน exploit บนเว็บจริง, brute force, login, เปลี่ยนรหัสผ่าน, migration, seed, แก้ VPS หรือแก้ dependency
ไม่พบหลักฐานการถูกโจมตีจากการตรวจนี้ และไม่ได้ทำ incident investigation หรืออ่าน access logs

## สรุป

ยังมีความเสี่ยงที่ต้องแก้ก่อนรับรองใช้งานจริง การผ่าน security suite เดิมไม่ครอบคลุมทุก finding
`npm audit` ล่าสุดรายงาน 8 package entries: Critical 1, High 2, Moderate 5 (ไม่ใช่จำนวนช่องทางโจมตีที่ยืนยันได้)
ระดับใน registry กับ advisory ต้นทางบางรายการไม่ตรงกัน ต้องพิจารณาเงื่อนไขโค้ดและไม่ใช้คะแนน audit เป็นข้อพิสูจน์ว่า exploit ได้

## Findings ที่ต้องแก้ก่อน

| ID | ระดับที่ประเมิน | หลักฐาน | ข้อจำกัด / วิธีแก้ |
| --- | --- | --- | --- |
| SEC-01 | High เมื่อ origin รับ header จาก client ได้ | Request::ip ที่ app/Core/Request.php:163 เลือก CF-Connecting-IP/X-Forwarded-For ก่อน REMOTE_ADDR โดยไม่มี trusted proxy check; RateLimitMiddleware ใช้ค่านี้เป็น key | local synthetic test ยืนยันว่า forwarded header เปลี่ยนค่า IP ได้ ไม่ได้ลองข้าม limit บน VPS; ใช้ REMOTE_ADDR เว้นแต่ peer อยู่ trusted proxy allowlist และตรวจ proxy/nginx ว่าล้าง header ที่ client ป้อน |
| SEC-02 | High | config/routes.php:217 สำหรับ POST /admin/2fa มี CSRF แต่ไม่มี OTP rate limiter; AuthController:228 เรียก verifyCode เพียง secret/code ไม่ส่ง lastUsedSlice/matchedSlice และไม่ persist accepted slice | local random-secret test ยืนยัน code เดิมผ่านสองครั้งหาก caller ไม่ส่ง replay state และ service ปฏิเสธเมื่อส่ง state; ต้องจำกัดทั้งบัญชี/pending challenge/IP และใช้ atomic consumed-step update ห้ามให้ concurrent requests ใช้ OTP เดียวกัน |
| SEC-03 | High แบบมีเงื่อนไขในงานใหม่ที่ยังไม่ commit | config/security.php:41 และ TwoFactorService::deriveKey มี fallback encryption key ที่กำหนดใน source | ไม่อ่าน/แสดง APP_KEY จริง ไม่ทราบว่า VPS ใช้ fallback หรือไม่; ต้อง fail closed เมื่อไม่มี key, ตรวจ length/format และจัดแผน re-encrypt/rotation ห้ามเปลี่ยน APP_KEY ดื้อ ๆ จน MFA เก่าถอดรหัสไม่ได้ |
| SEC-04 | High แบบมีเงื่อนไข | AuthController::changePassword รับ user_id/username จาก request; ไม่มี ownership/explicit admin authorization ในเมธอด และข้าม password_verify เมื่อ target password ว่าง | ไม่ได้เปลี่ยนรหัสใครจริง และยังไม่ทราบว่ามีบัญชี password ว่างหรือไม่; ผู้ที่มี hash ไม่ว่างยังต้องรู้รหัสของ target จึงไม่ใช่ unauthenticated reset ทั่วไป ต้องแยก self-change จาก admin reset, บังคับ owner และ reauthentication โดยเฉพาะเมื่อเพิ่ม Google login |
| SEC-05 | Medium | verifyRecoveryCode อ่าน unused codes แล้ว UPDATE used_at ตาม id อย่างเดียว ก่อน return true โดยไม่ตรวจ affected rows/lock | ยังไม่พบ recovery endpoint ใน routes ที่ตรวจ จึงเป็น latent race ของ service; ใช้ conditional update WHERE used_at IS NULL และตรวจ rowCount=1 หรือ transaction/row lock, เพิ่ม dedicated rate limiter ก่อนเชื่อมเข้าจริง |
| SEC-06 | Medium | Auth::verify2FA ไม่ regenerate session id; Session หมุน ID แต่ไม่ enforce absolute authenticated lifetime; Secure cookie default false | ไม่ยืนยันค่า cookie บน VPS เพราะ GET หน้าแรกไม่ส่ง Set-Cookie; แยก session rotation/auth lifetime, regenerate หลัง OTP และตั้ง host-only Secure cookie แยก TEST/Production |
| SEC-07 | Medium | Request::sanitizeArray trim ทุก string ก่อน AuthController นำ password ไป verify | password whitespace ไม่ถูกเก็บ byte-for-byte; ไม่ได้พิสูจน์ takeover แต่ทำให้พฤติกรรม auth ผิด ต้องใช้ raw password accessor พร้อม regression cases |
| SEC-08 | Medium | HTTPS หน้าแรก www.rayongcoop.org ตอบ 200 แต่ response ไม่มี Strict-Transport-Security และ Content-Security-Policy | ไม่ได้ตรวจ TLS config, redirect ทุกเส้นทาง หรือ subdomains; วางแผน HSTS หลังยืนยัน HTTPS ครบ และเริ่ม CSP Report-Only เพื่อไม่ทำลาย editor/fonts/รูป |

## Dependencies ล่าสุด

เวอร์ชันจาก package-lock.json: React 18.3.1, DOMPurify 3.4.16, Vite 6.4.3, react-router/react-router-dom 6.30.6, Quill 2.0.2

### DOMPurify

Registry รายงาน Critical สำหรับ GHSA-cg2g-xh64-v4q7 และ High สำหรับ GHSA-mrv2-3pqr-j5jr แต่หน้าประกาศ upstream ที่ตรวจแสดง Low สำหรับทั้งสองรายการ
GHSA-cg2g-xh64-v4q7 ระบุ affected 3.4.4–3.4.16 และ patched 3.4.17
เงื่อนไขต้องมี SAFE_FOR_TEMPLATES ร่วม DOM-returning/IN_PLACE และ shadow root รวมทั้ง downstream evaluation
โค้ด src/utils/richText.js ส่ง String(value), ใช้ allowlist HTML/attributes, ไม่เปิด SAFE_FOR_TEMPLATES/RETURN_DOM/IN_PLACE และไม่พบ addHook ดังกล่าวใน src
จึงยืนยันได้ว่ามี package เวอร์ชันอยู่ใน affected range แต่ยังไม่พบ call path ที่ตรงเงื่อนไข exploit นี้ในแอป
ควรอัปเดต patch และรัน editor/publication/XSS regression ก่อน deploy ไม่เปลี่ยนระดับเป็น Critical ของระบบโดยอัตโนมัติ

### Vite

Vite 6.4.3 มี advisories เพิ่ม รวม dev-server custom middleware และ filesystem boundary; GHSA-9jrq-w75r-8gcw ระบุ patched 6.4.4
เงื่อนไข custom middleware ไม่เหมือน static production artifact; ต้องตรวจว่า VPS เปิด Vite dev server จริงหรือไม่ก่อนอ้างว่า production ถูกกระทบ
Audit ยังรายงาน launch-editor issues ระดับ High ใน dependency entry นี้; ประเมินแพลตฟอร์ม/การใช้ editor endpoint และอัปเดตเวอร์ชันที่แก้ครบ แล้ว audit ซ้ำ

### React Router / build dependencies

6.30.6 อยู่ใน affected range ของ GHSA-wrjc-x8rr-h8h6 (unexpected redirect) และ GHSA-337j-9hxr-rhxg (SSR hydration)
แอปมี same-origin redirect helper ที่ tests ผ่าน และไม่พบ production SSR hydration pipeline จากโครงสร้าง React/Vite SPA ที่อ่าน จึงยังไม่พิสูจน์ exploit ของทั้งสองรายการ
GHSA-jjmj-jmhj-qwj2 เดิมแก้ใน 6.30.6 แล้ว ห้ามนับรายการนั้นว่า version ปัจจุบันยัง vulnerable
GHSA-wrjc-x8rr-h8h6 ระบุ fix >=7.18.0 ซึ่งเป็น major upgrade จาก 6 ต้องทดสอบ route/redirect ทั้งระบบก่อนเปลี่ยน
source-map-js เป็น High ที่เกี่ยวกับการประมวลผล source maps; postcss-nested/postcss-selector-parser/tailwindcss เป็น Moderate ต้องแยก build-time exposure จาก production request handling
ไม่พบ next หรือ react-server-dom-* ใน lockfile จึงไม่อ้างว่า React Server Components RCE กระทบแอปนี้เพียงเพราะใช้ React

## Tests / หลักฐานที่รันจริง

| คำสั่ง | ผล |
| --- | --- |
| npm audit --json | exit 1; 8 entries: Critical 1 / High 2 / Moderate 5 |
| composer validate --no-check-publish | PASS |
| composer audit --locked --format=json | PASS; advisories=[] และ abandoned=[] |
| php tests/SecurityRegressionTest.php | PASS บน source ปัจจุบัน; ไม่ครอบคลุม OTP replay/trusted proxy/recovery concurrency |
| node tests/NavigationRedirectTest.mjs | PASS 8 cases |
| php tests/QrCodeGeneratorTest.php | PASS SVG/data URI generation; ไม่เท่ากับทดสอบการสแกน QR กับ authenticator จริง |
| Synthetic Request IP check | caller-supplied forwarded address ถูกเลือกแทน peer address |
| Synthetic TOTP replay check | verifyCode เดิมผ่านซ้ำเมื่อ caller ไม่ส่ง replay state; ส่ง last-used state แล้วถูกปฏิเสธ |
| HTTPS GET / | 200; X-Frame-Options=SAMEORIGIN, X-Content-Type-Options=nosniff, Referrer-Policy=strict-origin-when-cross-origin; ไม่มี HSTS/CSP; ไม่ส่ง cookie ใน response นี้ |

โค้ด QR เดิมที่เรียก external service ถูกเปลี่ยนเป็น local generator แล้ว จึงไม่รายงานว่าปัจจุบันยังส่ง secret ไป qrserver
แต่ QrCodeGenerator.php และ migration 022 ยัง untracked ใน checkout นี้ จึงไม่ถือว่า release/package ติดตั้งครบหรือ mandatory MFA ทำงานจริง

## ลำดับแก้ที่เสนอ

1. แก้ trusted proxy/rate limiter, ownership ของ password change, OTP atomic replay/rate limiting และนำ pending-MFA workflow มาทดสอบเป็นชุดเดียวก่อน deploy
2. ยกเลิก default MFA encryption key ผ่าน configuration validation โดยวางแผน compatibility/rotation ก่อน ไม่เปลี่ยน credentials จริงอัตโนมัติ
3. อัปเดต DOMPurify และ Vite patch ที่แก้ advisory รัน clean install/build/rich text/route tests และ npm audit ซ้ำ
4. แยก PR major upgrade React Router พร้อม negative redirect tests และทดสอบ browser E2E
5. ตรวจ deployed revision, proxy header filtering, TLS/headers, cookies, private upload/backup access และ restore บน TEST ด้วยการอนุมัติที่เหมาะสม

รอบนี้เป็นรายงานตรวจ ไม่ได้แก้ source/lockfile/config หรือ deploy จึงไม่มี schema rollback; ถอนเฉพาะเอกสารฉบับนี้ได้โดยไม่แตะงานค้าง

## Primary advisories

- https://github.com/cure53/DOMPurify/security/advisories/GHSA-cg2g-xh64-v4q7
- https://github.com/cure53/DOMPurify/security/advisories/GHSA-mrv2-3pqr-j5jr
- https://github.com/vitejs/vite/security/advisories/GHSA-9jrq-w75r-8gcw
- https://github.com/remix-run/react-router/security/advisories/GHSA-wrjc-x8rr-h8h6
- https://github.com/remix-run/react-router/security/advisories/GHSA-jjmj-jmhj-qwj2
