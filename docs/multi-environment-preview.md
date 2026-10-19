# MAIN / TEST static preview deployment

ชุดนี้สำหรับเว็บประชาสัมพันธ์ผ่าน HTTP เท่านั้น ไม่มี PHP document root และไม่รับ Login/Protected API ใช้ source เดียว โดยยังเก็บระบบ backend เดิมไว้สำหรับ HTTPS ที่แยก deployment

## Build ในเครื่อง release หรือ CI

```bash
npm ci
composer install --no-interaction --prefer-dist
composer validate --strict
npm run build:main
npm run build:test
npm run test:preview
npx playwright install chromium
npm run test:browser
```

ได้ `dist/main/` และ `dist/test/` ทั้งสองชุดมีรูป, fonts ที่อยู่ใน public และ template ครบตาม whitelist ไม่รวม PHP, .env หรือ uploads ส่วนตัว ไม่ต้องคัดลอกรูปหลัง build

`npm run build` ยังคง legacy output `public/app/` สำหรับ deployment เดิม และไม่ได้เปิด preview mode โดยอัตโนมัติ

| ค่า | MAIN preview | TEST preview | legacy |
|---|---|---|---|
| Vite mode | main-preview | test-preview | production |
| asset base | / | /test/ | /app/ |
| Router basename | / | /test/ | / |
| API | ปิด | ปิด | same-origin หรือ VITE_API_BASE_URL |

VITE_* ทุกค่าเปิดเผยใน browser ได้ ห้ามใส่ APP_KEY, DB_PASSWORD หรือ token ใน VITE_* ไฟล์ `.env.main-preview`/`.env.test-preview` ใน Git มีเฉพาะ non-secret build settings เท่านั้น

## เตรียม Nginx โดยรักษาเว็บเดิม

1. ตรวจ `sudo nginx -T` และสำรองไฟล์ config ปัจจุบันใน directory สิทธิ์ 0700 นอก Git
2. ตรวจ server block ของ `119.59.116.32:80` ที่ใช้อยู่ ห้ามเพิ่ม server_name ซ้ำหรือแทนที่ HTTPS/Production block โดยไม่ตรวจ diff
3. ใช้ `deploy/nginx-static-preview.conf` เป็น config สำหรับ HTTP Preview เท่านั้น ไม่มี fastcgi_pass และไม่มี proxy ไป Login/API
4. โครงสร้างไฟล์:

```text
/var/www/rycoop-preview/
  main -> releases/main/<release-id>
  test -> releases/test/<release-id>
  releases/main/<release-id>/index.html
  releases/test/<release-id>/index.html
```

Nginx ใช้ main เป็น document root และ root override สำหรับ `/test/` รวม regex ของ test assets เพื่อป้องกันไปอ่าน assets ของ MAIN ผิดชุด

## Deploy TEST เท่านั้น

คำสั่งด้านล่างเป็นตัวอย่างให้ผู้ดูแลรันหลัง review PR และเตรียม artifact แล้ว ไม่ได้ถูกรันบน VPS โดย agent และไม่แตะ .env

```bash
# รันจากโฟลเดอร์ artifact ที่มี dist/test/ หลังส่งขึ้น VPS
set -euo pipefail
release_id="test-$(date -u +%Y%m%dT%H%M%SZ)"
release="/var/www/rycoop-preview/releases/test/$release_id"
sudo install -d /var/www/rycoop-preview/releases/test
sudo mkdir "$release"
sudo cp -a dist/test/. "$release/"
sudo chown -R root:www-data "$release"
sudo find "$release" -type d -exec chmod 755 {} \;
sudo find "$release" -type f -exec chmod 644 {} \;
readlink -f /var/www/rycoop-preview/test || true # จดไว้ใช้ rollback
sudo ln -s "$release" /var/www/rycoop-preview/.test-next
sudo mv -Tf /var/www/rycoop-preview/.test-next /var/www/rycoop-preview/test
sudo nginx -t
sudo systemctl reload nginx
curl -fsSI http://119.59.116.32/test/assets/img/logo.webp
curl -fsSI http://119.59.116.32/test/img/logo.webp
curl -s -o /dev/null -w '%{http_code}\n' http://119.59.116.32/test/assets/does-not-exist.webp
```

รูปทั้งสองต้อง 200 และ Content-Type image/webp; รูปที่ไม่มีต้อง 404 ห้ามได้ text/html พร้อม status 200 ตรวจ `/test/about`, `/test/news`, `/test/calculator` และ 404 ใน browser แล้วทำ Security smoke: `/test/login`, `/test/api/auth/me`, `/test/api/admin/members` ต้อง 403 และ POST ต้องไม่ส่งถึง PHP

โครงสร้างนี้เปลี่ยน symlink TEST อย่างเดียว ไม่สลับ MAIN และไม่แก้ database ส่วน MAIN ต้องได้รับอนุมัติเป็นครั้ง ๆ ก่อนใช้ขั้นตอนที่เทียบเท่ากันกับ releases/main และ symlink main

## Rollback TEST

ใช้ path release เดิมที่บันทึกจาก readlink ตรวจด้วย `realpath` ว่าอยู่ใต้ `/var/www/rycoop-preview/releases/test/` และมี index.html ก่อนสลับ symlink:

```bash
previous=/var/www/rycoop-preview/releases/test/REPLACE_WITH_VERIFIED_PREVIOUS_RELEASE
case "$(realpath "$previous")" in /var/www/rycoop-preview/releases/test/*) ;; *) exit 1;; esac
test -f "$previous/index.html"
sudo ln -s "$previous" /var/www/rycoop-preview/.test-rollback
sudo mv -Tf /var/www/rycoop-preview/.test-rollback /var/www/rycoop-preview/test
sudo nginx -t
sudo systemctl reload nginx
```

ไม่ลบ release เก่าระหว่าง deploy ถ้าเปลี่ยน Nginx แล้วตรวจไม่ผ่าน ให้คืน config ที่สำรองก่อน reload ไม่ต้อง rollback database เพราะรอบนี้ไม่มี migration

## Backend และ DB environments

HTTP preview ไม่เปิด auth แม้ frontend feature flag ถูกเปลี่ยน Backend guardไม่เชื่อ X-Forwarded-Proto จาก client ถ้าใช้ TLS terminator ภายนอกให้ตั้ง HTTPS FastCGI parameter จาก proxy ที่เชื่อถือได้อย่างชัดเจน ทดสอบปลอม forwarded headers ก่อนเปิดใช้งาน

HTTPS backend ใช้ APP_ENV=production, APP_DEBUG=false และ SESSION_SECURE=true ตรวจค่าบนเครื่องจริงโดยไม่พิมพ์ secrets ค่า default ในโค้ดเป็น secure นอก local/testing แต่ไม่แทนการตรวจ .env ที่มีอยู่

ใช้ DB_TEST กับ DB_PRODUCTION แยกฐานและแยกบัญชี credentials อย่า overwrite .env บน VPS ด้วยไฟล์จาก Git หรือใช้ production DB ใน tests ชุด tests/require-test-database.php จะปฏิเสธหากไม่ได้กำหนด APP_ENV=testing และชื่อ DB_DATABASE ที่อนุญาต

Migration compatibility: รอบนี้ไม่เพิ่ม/แก้ schema และไม่รัน migration จริง ส่วน legacy migration/seed ที่มีอยู่ต้อง review backup และ target database ก่อนอนุมัติแยก

## เงื่อนไขก่อน Production

ยังเหลือ npm audit 9 รายการที่ต้องแก้หรืออนุมัติความเสี่ยงอย่างชัดเจน ห้ามถือว่า Build ผ่านเท่ากับพร้อม Production ต้องทดสอบ nginx -t, MIME/Routes/Security บน TEST VPS จริง และ DB integration บนฐานทดสอบที่แยกก่อน ไม่มี automatic production deployment ใน CI ชุดนี้
