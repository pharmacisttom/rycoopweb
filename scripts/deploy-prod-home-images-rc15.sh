#!/usr/bin/env bash
# RC15 photographic highlight upgrade. Clones active RC15; never modifies live release or DB rows.
set -Eeuo pipefail
umask 027
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
test "$(id -u)" -eq 0
test "${RC15_IMAGE_DEPLOY:-}" = YES
REPO=/var/tmp/rycoop-board-welfare-rc06
SHA="${RC15_IMAGE_SHA:?missing RC15_IMAGE_SHA}"
BEFORE_SHA=079f9af43b97c8227a3912439c5e2eff8d5e496e
BASE=/var/www/rycoopweb/production-releases
OLD="$BASE/prod-home-rc15-20261120T141131Z"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="$BASE/prod-home-rc15-images-$STAMP"
MAIN=/etc/nginx/sites-available/rycoopweb-main-ssl
LOOP=/etc/nginx/conf.d/rycoop-prod-loopback.conf
BACKUP="/var/backups/rycoop-production/rc15-images-$STAMP"
CHANGED=0
SUCCESS=0
rollback(){
  code=$?
  trap - EXIT
  if test "$CHANGED" -eq 1 && test "$SUCCESS" -eq 0; then
    echo RC15_IMAGE_ROLLBACK=BEGIN
    cp -a "$BACKUP/main.conf" "$MAIN"
    cp -a "$BACKUP/loop.conf" "$LOOP"
    if nginx -t && systemctl reload nginx; then
      echo RC15_IMAGE_ROLLBACK=PASS
    else
      echo RC15_IMAGE_ROLLBACK=FAILED
    fi
  fi
  exit "$code"
}
trap rollback EXIT
http(){
 curl -4 -sS --noproxy '*' --connect-timeout 5 --max-time 20 \
 --resolve www.rayongcoop.org:443:127.0.0.1 \
 -o /dev/null -w '%{http_code}' "https://www.rayongcoop.org$1"
}
echo '===== 1. STRICT LIVE RC15 PREFLIGHT ====='
git -C "$REPO" cat-file -e "$SHA^{commit}"
test -d "$OLD"
test ! -e "$NEW"
for CFG in "$MAIN" "$LOOP"; do
 test "$(grep -Fc "$OLD/public" "$CFG")" -eq 1
done
for F in \
 app/Services/HomeCompositionService.php \
 src/components/home/HomeHighlightSlider.jsx \
 src/components/home/HomeHighlightSlider.css \
 src/components/admin/HomeCompositionEditor.jsx \
 src/components/admin/HomeCompositionEditor.css; do
 EXPECTED="$(git -C "$REPO" rev-parse "$BEFORE_SHA:$F")"
 ACTUAL="$(git -C "$REPO" hash-object "$OLD/$F")"
 if test "$ACTUAL" != "$EXPECTED"; then
  echo "LIVE_FILE_DIVERGED=$F"; exit 1
 fi
done
test "$(http /app/)" = 200
test "$(http /api/public/home-composition)" = 200
test "$(http /api/public/documents)" = 200
AUTH_BEFORE="$(http /api/admin/home-composition)"
case "$AUTH_BEFORE" in 302|401|403) ;; *) echo "AUTH_GUARD_FAIL=$AUTH_BEFORE"; exit 1;; esac
nginx -t
systemctl is-active --quiet nginx
systemctl is-active --quiet rycoop-prod-fpm
echo RC15_BASELINE=PASS

echo '===== 2. COPY ACTIVE PRODUCTION AND PATCH REVIEWED FILES ====='
mkdir -p "$BACKUP"
cp -a "$MAIN" "$BACKUP/main.conf"
cp -a "$LOOP" "$BACKUP/loop.conf"
cp -a "$OLD" "$NEW"
for F in \
 app/Services/HomeCompositionService.php \
 src/components/home/HomeHighlightSlider.jsx \
 src/components/home/HomeHighlightSlider.css \
 src/components/admin/HomeCompositionEditor.jsx \
 src/components/admin/HomeCompositionEditor.css; do
 mkdir -p "$(dirname "$NEW/$F")"
 git -C "$REPO" show "$SHA:$F" > "$NEW/$F"
 test "$(git -C "$REPO" hash-object "$NEW/$F")" = "$(git -C "$REPO" rev-parse "$SHA:$F")"
done
git -C "$REPO" show "$SHA:tests/HomeCompositionRc15ValidationTest.php" > "$NEW/tests/HomeCompositionRc15ValidationTest.php"
# Explicitly preserve RC14 board directory, routes, auth, FPM config and all DB data.
cmp "$OLD/.env" "$NEW/.env"
cmp "$OLD/config/routes.php" "$NEW/config/routes.php"
cmp "$OLD/src/pages/PublicContentAdminPage.jsx" "$NEW/src/pages/PublicContentAdminPage.jsx"
cmp "$OLD/app/Controllers/Admin/HomeCompositionController.php" "$NEW/app/Controllers/Admin/HomeCompositionController.php"
cmp "$OLD/composer.lock" "$NEW/composer.lock"
php -l "$NEW/app/Services/HomeCompositionService.php"
(cd "$NEW" && php tests/HomeCompositionRc15ValidationTest.php)
chown -R root:www-data "$NEW"
chmod 750 "$NEW" "$NEW/app" "$NEW/app/Services"
chmod 640 "$NEW/.env" "$NEW/app/Services/HomeCompositionService.php"
runuser -u www-data -- test -r "$NEW/app/Services/HomeCompositionService.php"
runuser -u www-data -- php -r 'require $argv[1]."/vendor/autoload.php";if(!class_exists("App\\Services\\HomeCompositionService"))exit(1);echo "RC15_IMAGES_AUTOLOAD=PASS\n";' "$NEW"

echo '===== 3. FRONTEND BUILD ====='
cd "$NEW"
node scripts/build-adminlte-scoped.mjs
npm run build
ASSET="$(grep -oE '/app/assets/index-[A-Za-z0-9_-]+\.js' public/app/index.html | head -1)"
test -n "$ASSET"
test -s "public$ASSET"
find "$NEW/public/app" -type d -exec chmod 755 '{}' +
find "$NEW/public/app" -type f -exec chmod 644 '{}' +
runuser -u www-data -- test -r "$NEW/public/app/index.html"
runuser -u www-data -- test -r "$NEW/public$ASSET"
nginx -t

echo '===== 4. SWITCH WITH ROLLBACK ====='
export OLD NEW MAIN LOOP
CHANGED=1
python3 - <<'PY'
import os,shutil
from pathlib import Path
old=os.environ['OLD']+'/public'
new=os.environ['NEW']+'/public'
for key in ['MAIN','LOOP']:
 p=Path(os.environ[key]); value=p.read_text()
 if value.count(old)!=1 or new in value: raise SystemExit('RC15_IMAGE_SWITCH_GUARD_FAILED')
 temp=p.with_name(p.name+'.rc15-image.tmp')
 if temp.exists(): raise SystemExit('RC15_IMAGE_TEMP_CONFIG_EXISTS')
 shutil.copy2(p,temp);temp.write_text(value.replace(old,new));os.replace(temp,p)
PY
nginx -t && systemctl reload nginx

echo '===== 5. POST-SWITCH API SMOKE ====='
for P in /app/ /app/admin/layout /api/public/documents /api/public/home-composition "$ASSET"; do
 CODE="$(http "$P")"; echo "$P HTTP=$CODE"
 test "$CODE" = 200
done
AUTH_AFTER="$(http /api/admin/home-composition)"
case "$AUTH_AFTER" in 302|401|403) ;; *) echo "AUTH_GUARD_FAIL=$AUTH_AFTER";exit 1;; esac
curl -4 -fsS --noproxy '*' --max-time 20 \
 --resolve www.rayongcoop.org:443:127.0.0.1 \
 https://www.rayongcoop.org/api/public/home-composition |
 python3 -c 'import json,sys;x=json.load(sys.stdin);assert x["success"] is True;d=x["data"];assert isinstance(d["background"],dict) and "image_url" in d["background"];assert all("image_url" in s for s in d["slides"])'
test "$(grep -Fc "$NEW/public" "$MAIN")" -eq 1
test "$(grep -Fc "$NEW/public" "$LOOP")" -eq 1
nginx -t
SUCCESS=1
echo RC15_IMAGE_PRODUCTION_DEPLOY=PASS
echo "ACTIVE_RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo DATABASE_SCHEMA=UNCHANGED
echo RC14_BOARD_SOURCE=UNCHANGED
echo NEXT=AUTHORIZED_MFA_DRAFT_PUBLISH_UAT
