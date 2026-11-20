#!/usr/bin/env bash
# RC14: Selective frontend-only deployment to TEST. Never changes production or any database.
set -Eeuo pipefail
umask 077
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
REPO="/var/tmp/rycoop-board-welfare-rc06"
BRANCH="feature/board-directory-table-bulk-edit-rc14"
EXPECTED_SHA="${RC14_SHA:?Specify the reviewed RC14 commit SHA}"
CONF="$(readlink -f /etc/nginx/sites-enabled/rycoopweb-test-v2)"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BASE="/var/www/rycoopweb/test-releases"
NEW="$BASE/rc14-board-table-$STAMP"
BACKUP="/var/backups/rycoop-test/rc14-board-$STAMP"
CHANGED=0
DONE=0
rollback(){
  rc=$?
  trap - EXIT
  if [[ "$CHANGED" -eq 1 && "$DONE" -eq 0 ]]; then
    printf '%s\n' 'RC14_TEST_ROLLBACK=BEGIN'
    cp -a "$BACKUP/nginx-test.conf" "$CONF"
    if nginx -t && systemctl reload nginx; then
      echo 'RC14_TEST_ROLLBACK=PASS'
    else
      echo 'RC14_TEST_ROLLBACK=FAILED -- investigate nginx immediately'
    fi
  fi
  exit "$rc"
}
trap rollback EXIT
[[ "$EUID" -eq 0 ]] || { echo 'ERROR: require sudo'; exit 1; }
[[ "$CONF" == /etc/nginx/*rycoopweb-test-v2* ]] || { echo 'TEST_CONFIG_GUARD=FAIL';exit 1; }
[[ -f "$CONF" && -d "$REPO/.git" && ! -e "$NEW" ]] || { echo 'SOURCE_GUARD=FAIL';exit 1; }
ACTIVE="$(grep -oE '/var/www/rycoopweb/test-releases/[^ ;]+/public' "$CONF" | sort -u)"
[[ -n "$ACTIVE" ]] || { echo 'ACTIVE_TEST_RELEASE_NOT_FOUND';exit 1; }
OLD="${ACTIVE%/public}"
[[ -d "$OLD" ]] || exit 1
[[ "$(grep -Fc "$OLD/public" "$CONF")" -eq 3 ]] || { echo 'TEST_ROUTING_COUNT_MISMATCH';exit 1; }
[[ -f "$OLD/.env" && -f "$OLD/public/app/index.html" && -f "$OLD/public/index.php" ]] || exit 1
grep -Eq '^DB_DATABASE=["'"'"']?rycoopweb_test["'"'"']?[[:space:]]*$' "$OLD/.env"
[[ -S /run/php/rycoop-test.sock ]] || exit 1
[[ "$(git -C "$REPO" rev-parse "$EXPECTED_SHA^{commit}")" == "$EXPECTED_SHA" ]] || exit 1
for FILE in src/pages/PublicContentAdminPage.jsx src/pages/BoardTermAdminRC06.css; do
  git -C "$REPO" cat-file -e "$EXPECTED_SHA:$FILE"
done
echo "ACTIVE_TEST=$OLD"
echo "RC14_SOURCE=$EXPECTED_SHA"
mkdir -p "$BACKUP"
cp -a "$CONF" "$BACKUP/nginx-test.conf"
cp -a "$OLD" "$NEW"
for FILE in src/pages/PublicContentAdminPage.jsx src/pages/BoardTermAdminRC06.css; do
  git -C "$REPO" show "$EXPECTED_SHA:$FILE" > "$NEW/$FILE"
done
cmp "$OLD/.env" "$NEW/.env"
cmp "$OLD/config/routes.php" "$NEW/config/routes.php"
cmp "$OLD/app/Services/PublicContentService.php" "$NEW/app/Services/PublicContentService.php"
cd "$NEW"
npm ci --no-audit --no-fund
npm run build
grep -qF 'board-admin-table' src/pages/PublicContentAdminPage.jsx
grep -qF 'removeBoardSelection' src/pages/PublicContentAdminPage.jsx
test -f public/app/index.html
grep -RFl 'board-admin-table' public/app/assets --include='*.js' >/dev/null
chown -R root:www-data "$NEW"
chmod 750 "$NEW"
chmod 640 "$NEW/.env"
find "$NEW/public/app" -type d -exec chmod 755 '{}' +
find "$NEW/public/app" -type f -exec chmod 644 '{}' +
runuser -u www-data -- test -r "$NEW/public/index.php"
runuser -u www-data -- test -r "$NEW/public/app/index.html"
runuser -u www-data -- test -r "$NEW/vendor/autoload.php"
nginx -t
export OLD NEW CONF
CHANGED=1
python3 - <<'PY'
import os
from pathlib import Path
import shutil
config=Path(os.environ['CONF'])
old=os.environ['OLD']+'/public'
new=os.environ['NEW']+'/public'
current=config.read_text()
if current.count(old)!=3 or new in current:
    raise SystemExit('TEST_RELEASE_SWITCH_GUARD=FAIL')
temp=config.with_name(config.name+'.rc14.tmp')
if temp.exists():
    raise SystemExit('TEMP_NGINX_CONFIG_ALREADY_EXISTS')
shutil.copy2(config,temp)
temp.write_text(current.replace(old,new))
os.replace(temp,config)
PY
nginx -t
systemctl reload nginx
test "$(grep -Fc "$NEW/public" "$CONF")" -eq 3
systemctl is-active nginx
systemctl is-active rycoop-test-fpm
for URL_PATH in /app/ /api/auth/me; do
  CODE="$(curl -sS --max-time 15 -o /dev/null -w '%{http_code}' "https://test.rayongcoop.org${URL_PATH}")"
  echo "TEST_HTTP ${URL_PATH} ${CODE}"
  [[ "$CODE" == '401' ]] || { echo 'TEST_BASIC_AUTH_GUARD_FAILED'; exit 1; }
done
DONE=1
echo 'RC14_TEST_ACTIVATION=PASS'
echo "ACTIVE_TEST_RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo 'PRODUCTION=UNCHANGED'
echo 'NEXT_GATE=AUTHORIZED_BROWSER_UAT'
