#!/usr/bin/env bash
# RC15: direct image uploads in the Homepage Composition Editor. Only UI files change.
set -Eeuo pipefail
umask 027
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
test "$(id -u)" -eq 0
test "${RC15_DIRECT_UPLOAD_DEPLOY:-}" = YES
REPO=/var/tmp/rycoop-board-welfare-rc06
SHA="${RC15_DIRECT_UPLOAD_SHA:?missing SHA}"
BASE=/var/www/rycoopweb/production-releases
OLD="$BASE/prod-home-rc15-upload-20261120T150132Z"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="$BASE/prod-home-rc15-upload-$STAMP"
MAIN=/etc/nginx/sites-available/rycoopweb-main-ssl
LOOP=/etc/nginx/conf.d/rycoop-prod-loopback.conf
BACKUP="/var/backups/rycoop-production/rc15-direct-upload-$STAMP"
CHANGED=0
SUCCESS=0
rollback(){
 status=$?
 trap - EXIT
 if test "$CHANGED" -eq 1 && test "$SUCCESS" -eq 0; then
  cp -a "$BACKUP/main.conf" "$MAIN"
  cp -a "$BACKUP/loop.conf" "$LOOP"
  if nginx -t && systemctl reload nginx; then
   echo RC15_DIRECT_UPLOAD_ROLLBACK=PASS
  else
   echo RC15_DIRECT_UPLOAD_ROLLBACK=FAILED
  fi
 fi
 exit "$status"
}
trap rollback EXIT
http(){
 curl -4 -sS --noproxy '*' --connect-timeout 5 --max-time 20 \
  --resolve www.rayongcoop.org:443:127.0.0.1 \
  -o /dev/null -w '%{http_code}' "https://www.rayongcoop.org$1"
}
echo '===== 1. READ-ONLY PRECHECK ====='
git -C "$REPO" cat-file -e "$SHA^{commit}"
test -d "$OLD"
test ! -e "$NEW"
for CONFIG in "$MAIN" "$LOOP"; do
 test "$(grep -Fc "$OLD/public" "$CONFIG")" -eq 1
done
test "$(git -C "$REPO" hash-object "$OLD/src/components/admin/HomeCompositionEditor.jsx")" = f8056702c26f6d52186ccfda18b9ec1aa7d1d1e6
test "$(git -C "$REPO" hash-object "$OLD/src/components/admin/HomeCompositionEditor.css")" = dc301369930a4f7f0d733ad8ad6009255a42cbb1
test -f "$OLD/src/services/mediaLibraryApi.js"
test -f "$OLD/app/Controllers/Api/MediaLibraryController.php"
test "$(http /app/)" = 200
test "$(http /api/public/home-composition)" = 200
test "$(http /api/public/documents)" = 200
ADMIN_CODE="$(http /api/admin/media)"
case "$ADMIN_CODE" in 302|401|403) echo RC15_MEDIA_AUTH_GUARD=PASS;; *) echo "RC15_MEDIA_AUTH_GUARD_FAIL=$ADMIN_CODE";exit 1;; esac
nginx -t
systemctl is-active --quiet nginx
systemctl is-active --quiet rycoop-prod-fpm
echo RC15_DIRECT_UPLOAD_BASELINE=PASS

echo '===== 2. CLONE AND PATCH ONLY EDITOR ====='
mkdir -p "$BACKUP"
cp -a "$MAIN" "$BACKUP/main.conf"
cp -a "$LOOP" "$BACKUP/loop.conf"
cp -a "$OLD" "$NEW"
for FILE in src/components/admin/HomeCompositionEditor.jsx src/components/admin/HomeCompositionEditor.css; do
 git -C "$REPO" show "$SHA:$FILE" > "$NEW/$FILE"
 test "$(git -C "$REPO" hash-object "$NEW/$FILE")" = "$(git -C "$REPO" rev-parse "$SHA:$FILE")"
done
cmp "$OLD/.env" "$NEW/.env"
cmp "$OLD/config/routes.php" "$NEW/config/routes.php"
cmp "$OLD/app/Services/HomeCompositionService.php" "$NEW/app/Services/HomeCompositionService.php"
cmp "$OLD/app/Controllers/Api/MediaLibraryController.php" "$NEW/app/Controllers/Api/MediaLibraryController.php"
cmp "$OLD/src/pages/PublicContentAdminPage.jsx" "$NEW/src/pages/PublicContentAdminPage.jsx"
cmp "$OLD/composer.lock" "$NEW/composer.lock"

echo '===== 3. BUILD FRONTEND ====='
cd "$NEW"
node scripts/build-adminlte-scoped.mjs
npm run build
ASSET="$(grep -oE '/app/assets/index-[A-Za-z0-9_-]+\.js' public/app/index.html | head -1)"
test -n "$ASSET"
test -s "public$ASSET"
chown -R root:www-data "$NEW"
chmod 750 "$NEW"
chmod 640 "$NEW/.env"
find "$NEW/public/app" -type d -exec chmod 755 '{}' +
find "$NEW/public/app" -type f -exec chmod 644 '{}' +
runuser -u www-data -- test -r "$NEW/public/app/index.html"
runuser -u www-data -- test -r "$NEW/public$ASSET"
nginx -t

echo '===== 4. SWITCH AND VERIFY WITH ROLLBACK ====='
export OLD NEW MAIN LOOP
CHANGED=1
python3 - <<'PY'
import os,shutil
from pathlib import Path
old=os.environ['OLD']+'/public'
new=os.environ['NEW']+'/public'
targets=[Path(os.environ['MAIN']),Path(os.environ['LOOP'])]
changes=[]
for target in targets:
 source=target.read_text()
 if source.count(old)!=1 or new in source: raise SystemExit('DIRECT_UPLOAD_ROUTE_GUARD_FAILED')
 changes.append((target,source.replace(old,new)))
for target,content in changes:
 temp=target.with_name(target.name+'.direct-upload.tmp')
 if temp.exists(): raise SystemExit('TEMP_CONFIG_EXISTS')
 shutil.copy2(target,temp)
 temp.write_text(content)
 os.replace(temp,target)
PY
nginx -t
systemctl reload nginx
for P in /app/ /app/admin/layout /api/public/home-composition /api/public/documents "$ASSET"; do
 CODE="$(http "$P")"
 echo "$P HTTP=$CODE"
 test "$CODE" = 200
done
AFTER_ADMIN="$(http /api/admin/media)"
case "$AFTER_ADMIN" in 302|401|403) echo RC15_MEDIA_AUTH_GUARD_AFTER=PASS;; *) echo RC15_MEDIA_AUTH_GUARD_AFTER=FAILED;exit 1;; esac
test "$(grep -Fc "$NEW/public" "$MAIN")" -eq 1
test "$(grep -Fc "$NEW/public" "$LOOP")" -eq 1
nginx -t
SUCCESS=1
echo RC15_DIRECT_UPLOAD_PRODUCTION_DEPLOY=PASS
echo "ACTIVE_RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo 'DATABASE_SCHEMA=UNCHANGED'
echo 'RC14_BOARD_SOURCE=UNCHANGED'
echo 'NEXT=AUTHORIZED_UPLOAD_THEN_MFA_DRAFT_PUBLISH_UAT'
