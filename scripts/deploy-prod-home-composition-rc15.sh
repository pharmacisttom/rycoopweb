#!/usr/bin/env bash
# RC15 selective production deployment. No source code on RC14 is replaced.
set -Eeuo pipefail
umask 077
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
test "$(id -u)" -eq 0
test "$RC15_DEPLOY" = YES
REPO=/var/tmp/rycoop-board-welfare-rc06
SHA="$RC15_SHA"
# The tested RC14 baseline contains a nullable deposit-source URL bug.
# Production hotfix changes that *single expression* to optional chaining.
# Reconstruct the allowed hotfix from its verified RC14 parent; never bypass source guard.
RC14_PARENT=/var/www/rycoopweb/production-releases/prod-board-rc14-validation-20261120T105523Z
RC14_HOTFIX=/var/www/rycoopweb/production-releases/prod-home-nullfix-20261120T130611Z
declare -A APPROVED_LIVE=(
  [src/pages/AdminHomepageLayoutPage.jsx]=1c70e9e258695448f542c9e3f180a0a1e8c3b074
)
BASE=/var/www/rycoopweb/production-releases
MAIN=/etc/nginx/sites-available/rycoopweb-main-ssl
LOOP=/etc/nginx/conf.d/rycoop-prod-loopback.conf
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="$BASE/prod-home-rc15-$STAMP"
BACKUP="/var/backups/rycoop-production/rc15-$STAMP"
CHANGED=0
SUCCESS=0
rollback(){
 rc=$?
 trap - EXIT
 if test "$CHANGED" -eq 1 && test "$SUCCESS" -eq 0; then
  echo RC15_ROLLBACK=BEGIN
  cp -a "$BACKUP/main.conf" "$MAIN"
  cp -a "$BACKUP/loop.conf" "$LOOP"
  if nginx -t && systemctl reload nginx; then echo RC15_ROLLBACK=PASS; else echo RC15_ROLLBACK=FAILED; fi
 fi
 exit "$rc"
}
trap rollback EXIT
http(){
 curl -sS -4 --noproxy '*' --connect-timeout 5 --max-time 20 \
  --resolve www.rayongcoop.org:443:127.0.0.1 \
  -o /dev/null -w '%{http_code}' "https://www.rayongcoop.org$1"
}
echo '===== 1. CHECK CURRENT PRODUCTION ====='
test -n "$SHA"
git -C "$REPO" cat-file -e "$SHA^{commit}"
A="$(grep -oE '/var/www/rycoopweb/production-releases/[^ ;]+/public' "$MAIN" | sort -u)"
B="$(grep -oE '/var/www/rycoopweb/production-releases/[^ ;]+/public' "$LOOP" | sort -u)"
test -n "$A"
test "$A" = "$B"
OLD="$(dirname "$A")"
test -d "$OLD"
test ! -e "$NEW"
test "$(grep -Fc "$A" "$MAIN")" -eq 1
test "$(grep -Fc "$A" "$LOOP")" -eq 1
grep -Eq '^DB_DATABASE=["'"'"']?rayongcoop_db["'"'"']?[[:space:]]*$' "$OLD/.env"
# Strictly guard the production-hotfix release and its one-line fix.
if test "$OLD" != "$RC14_HOTFIX"; then
  echo "UNEXPECTED_LIVE_RELEASE=$OLD"; exit 1
fi
test -f "$RC14_PARENT/src/pages/HomePage.jsx"
test -f "$RC14_HOTFIX/src/pages/HomePage.jsx"
test "$(git -C "$REPO" hash-object "$RC14_PARENT/src/pages/HomePage.jsx")" = 0f12bc8c4a4483d7f55aa44b1cf2467e4f46de40
export RC14_PARENT RC14_HOTFIX
python3 - <<'PY'
import os
from pathlib import Path
old=(Path(os.environ['RC14_PARENT'])/'src/pages/HomePage.jsx').read_text()
new=(Path(os.environ['RC14_HOTFIX'])/'src/pages/HomePage.jsx').read_text()
needle='href={INTEREST_RATES.depositSource.url}'
assert old.count(needle)==1, 'UNRECOGNIZED_PARENT_SOURCE'
expected=old.replace(needle,'href={INTEREST_RATES?.depositSource?.url}')
assert new==expected, 'UNREVIEWED_HOTFIX_DIFF'
print('HOTFIX_NULL_GUARD=PASS')
PY
for F in src/pages/AdminHomepageLayoutPage.jsx; do
 EXPECTED="${APPROVED_LIVE[$F]}"
 ACTUAL="$(git -C "$REPO" hash-object "$OLD/$F")"
 if test "$EXPECTED" != "$ACTUAL"; then
   echo "UNREVIEWED_LIVE_SOURCE=$F"; exit 1
 fi
 echo "REVIEWED_LIVE_SOURCE=PASS:$F"
done
# Null-safe TopBar change is permitted only against the known RC14 component.
test "$(git -C "$REPO" hash-object "$OLD/src/components/layout/TopBar.jsx")" = d57fd3291df35d119b1a5d8d4357e23cb98f2a08 || {
 echo "TOPBAR_LIVE_SOURCE_CONFLICT"; exit 1;
}
grep -qF '// New dynamic homepage CMS: isolated from legacy six visibility toggles.' "$OLD/config/routes.php"
if grep -qF '/api/public/home-composition' "$OLD/config/routes.php"; then echo RC15_ALREADY_CONFIGURED; exit 1; fi
mysql --batch --skip-column-names rayongcoop_db -e "
SELECT IF(COUNT(*)=5,'PASS','FAIL') FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA='rayongcoop_db' AND TABLE_NAME='site_settings'
AND COLUMN_NAME IN ('key','value','group','is_public','updated_at');" | grep -qx PASS
nginx -t
systemctl is-active --quiet nginx
systemctl is-active --quiet rycoop-prod-fpm
for P in /app/ /app/admin/layout /api/public/documents; do test "$(http "$P")" = 200; done
AUTH_BEFORE="$(http /api/admin/layout)"
case "$AUTH_BEFORE" in 302|401|403) ;; *) echo "AUTH_GUARD_UNEXPECTED=$AUTH_BEFORE";exit 1;; esac
echo "EXISTING_ADMIN_AUTH_HTTP=$AUTH_BEFORE"
echo "ACTIVE_PRODUCTION=$OLD"

echo '===== 2. BACKUP AND SELECTIVE PATCH ====='
mkdir -p "$BACKUP"
cp -a "$MAIN" "$BACKUP/main.conf"
cp -a "$LOOP" "$BACKUP/loop.conf"
cp -a "$OLD" "$NEW"
for F in \
 app/Services/HomeCompositionService.php \
 app/Controllers/Admin/HomeCompositionController.php \
 src/components/admin/HomeCompositionEditor.jsx \
 src/components/admin/HomeCompositionEditor.css \
 src/components/home/HomeHighlightSlider.jsx \
 src/components/home/HomeHighlightSlider.css \
 src/components/layout/TopBar.jsx \
 src/pages/HomePage.jsx \
 src/pages/AdminHomepageLayoutPage.jsx; do
 mkdir -p "$(dirname "$NEW/$F")"
 git -C "$REPO" show "$SHA:$F" > "$NEW/$F"
 test "$(git -C "$REPO" hash-object "$NEW/$F")" = "$(git -C "$REPO" rev-parse "$SHA:$F")"
done
export NEW
python3 - <<'PY'
import os
from pathlib import Path
p=Path(os.environ['NEW'])/'config/routes.php'
s=p.read_text()
anchor='// New dynamic homepage CMS: isolated from legacy six visibility toggles.'
assert s.count(anchor)==1
insert=r"""// RC15: published public, CSRF and MFA for privileged writes
$router->get('/api/public/home-composition', 'Admin\\HomeCompositionController@published');
$router->group(['prefix'=>'api/admin/home-composition','middleware'=>[AuthMiddleware::class]], function(\App\Core\Router $r) {
    $r->get('', 'Admin\\HomeCompositionController@show');
    $r->post('/draft', 'Admin\\HomeCompositionController@draft', [CsrfMiddleware::class]);
    $r->post('/publish', 'Admin\\HomeCompositionController@publish', [CsrfMiddleware::class]);
});

"""
p.write_text(s.replace(anchor,insert+anchor,1))
PY
cmp "$OLD/.env" "$NEW/.env"
cmp "$OLD/public/index.php" "$NEW/public/index.php"
cmp "$OLD/composer.lock" "$NEW/composer.lock"
diff -qr "$OLD/app/Core" "$NEW/app/Core" >/dev/null
cmp "$OLD/src/pages/PublicContentAdminPage.jsx" "$NEW/src/pages/PublicContentAdminPage.jsx"
php -l "$NEW/config/routes.php"
php -l "$NEW/app/Services/HomeCompositionService.php"
php -l "$NEW/app/Controllers/Admin/HomeCompositionController.php"

echo '===== 3. BUILD FRONTEND ====='
cd "$NEW"
node scripts/build-adminlte-scoped.mjs
npm run build
ASSET="$(grep -oE '/app/assets/index-[A-Za-z0-9_-]+\.js' public/app/index.html | head -1)"
test -n "$ASSET"
test -s "public$ASSET"
grep -RFl 'home-highlight-slider' public/app/assets --include='*.js' >/dev/null
chown -R root:www-data "$NEW"
chmod 750 "$NEW"
chmod 640 "$NEW/.env"
find "$NEW/public/app" -type d -exec chmod 755 '{}' +
find "$NEW/public/app" -type f -exec chmod 644 '{}' +
runuser -u www-data -- test -r "$NEW/public/index.php"
runuser -u www-data -- test -r "$NEW/public/app/index.html"
runuser -u www-data -- test -r "$NEW/public$ASSET"
nginx -t

echo '===== 4. NGINX SWITCH WITH AUTOMATIC ROLLBACK ====='
export OLD MAIN LOOP
CHANGED=1
python3 - <<'PY'
import os, shutil
from pathlib import Path
old=os.environ['OLD']+'/public';new=os.environ['NEW']+'/public'
files=[Path(os.environ['MAIN']),Path(os.environ['LOOP'])]
patch=[]
for f in files:
 s=f.read_text()
 if s.count(old)!=1 or new in s: raise SystemExit('ROUTING_GUARD_FAILED')
 patch.append((f,s.replace(old,new)))
for f,s in patch:
 tmp=f.with_name(f.name+'.rc15.tmp')
 if tmp.exists(): raise SystemExit('TEMP_CONFIG_EXISTS')
 shutil.copy2(f,tmp);tmp.write_text(s);os.replace(tmp,f)
PY
nginx -t && systemctl reload nginx

echo '===== 5. SMOKE / AUTH / PUBLIC API ====='
# Nginx intentionally redirects the root URL to the React app.
# Accept only the exact established canonical destination, never arbitrary 302.
ROOT_HEADERS="$(curl --noproxy '*' -4 -sS --connect-timeout 5 --max-time 20 \
 --resolve www.rayongcoop.org:443:127.0.0.1 \
 -o /dev/null -D - https://www.rayongcoop.org/)"
ROOT_CODE="$(printf '%s\n' "$ROOT_HEADERS" | awk 'index(toupper($1),"HTTP/")==1 {code=$2} END {print code}')"
ROOT_LOCATION="$(printf '%s\n' "$ROOT_HEADERS" | awk 'tolower($1)=="location:" {v=$2;gsub(sprintf("%c",13),"",v);print v}' | tail -1)"
echo "ROOT_HTTP=$ROOT_CODE"
echo "ROOT_LOCATION=$ROOT_LOCATION"
test "$ROOT_CODE" = 302
test "$ROOT_LOCATION" = "https://www.rayongcoop.org/app/"
for P in /app/ /app/admin/layout /app/admin/security /api/public/documents "$ASSET"; do
 CODE="$(http "$P")";echo "$P HTTP=$CODE";test "$CODE" = 200
done
PUBLIC_CODE="$(http /api/public/home-composition)"
ADMIN_CODE="$(http /api/admin/home-composition)"
echo "RC15_PUBLIC_COMPOSITION_HTTP=$PUBLIC_CODE"
echo "RC15_ADMIN_COMPOSITION_HTTP=$ADMIN_CODE"
echo "RC15_EXPECTED_ADMIN_GUARD=$AUTH_BEFORE"
if test "$PUBLIC_CODE" != 200; then
 echo "RC15_PUBLIC_COMPOSITION_GATE=FAILED"
 echo "Inspect PHP-FPM / Nginx logs for the request; do not expose credentials."
 exit 1
fi
# The legacy /api/admin/layout and the new endpoint may use different
# unauthorized response styles (401 JSON versus 302 redirect); both must DENY.
# Do not require their HTTP status codes to be identical.
case "$ADMIN_CODE" in
  302|401|403) echo "RC15_ADMIN_AUTH_GUARD=PASS" ;;
  *) echo "RC15_ADMIN_GUARD_GATE=FAILED"; exit 1 ;;
esac
curl --noproxy '*' -4 -fsS --max-time 20 \
 --resolve www.rayongcoop.org:443:127.0.0.1 \
 https://www.rayongcoop.org/api/public/home-composition |
 python3 -c 'import sys,json; x=json.load(sys.stdin); assert x["success"] is True; d=x["data"]; assert len(d["order"])==8 and "layers" in d and "slides" in d'
test "$(grep -Fc "$NEW/public" "$MAIN")" -eq 1
test "$(grep -Fc "$NEW/public" "$LOOP")" -eq 1
nginx -t
SUCCESS=1
echo RC15_PRODUCTION_DEPLOY=PASS
echo "ACTIVE_RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo 'RC14_BOARD_SOURCE=UNCHANGED'
echo 'DATABASE_SCHEMA=UNCHANGED'
echo 'HIGHLIGHTS=VISIBLE_ONLY_AFTER_APPROVED_CONTENT_PUBLISH'
echo 'NEXT=AUTHORIZED_MFA_DRAFT_PUBLISH_UAT'
