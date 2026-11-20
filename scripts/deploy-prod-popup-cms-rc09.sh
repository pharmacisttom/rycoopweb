#!/usr/bin/env bash
# RYCOOPWEB Popup Manager RC09 — guarded Production release (no DB migrations).
# Usage: sudo bash scripts/deploy-prod-popup-cms-rc09.sh /var/tmp/rycoop-popup-build-XXXXXX
set -Eeuo pipefail
umask 077

SOURCE="${1:?Usage: $0 /path/to/isolated-source-build}"
HOST="www.rayongcoop.org"
DB="rayongcoop_db"
BASE="/var/www/rycoopweb/production-releases"
OLD="$BASE/prod-cookie-consent-rc08-20261120T014141Z"
MAIN="/etc/nginx/sites-available/rycoopweb-main-ssl"
LOOP="/etc/nginx/conf.d/rycoop-prod-loopback.conf"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="$BASE/prod-popup-cms-rc09-$STAMP"
BACKUP="/var/backups/rycoop-production/popup-cms-rc09-$STAMP"
SWITCHED=0
SUCCESS=0

status() {
  curl --noproxy '*' -4 -sS --max-time 20 --resolve "$HOST:443:127.0.0.1" \
    -o /dev/null -w '%{http_code}' "https://$HOST$1"
}
rollback() {
  rc=$?
  trap - EXIT
  if [[ "$SWITCHED" -eq 1 && "$SUCCESS" -ne 1 ]]; then
    echo "===== ROLLBACK ====="
    cp -a "$BACKUP/main.conf" "$MAIN"
    cp -a "$BACKUP/loop.conf" "$LOOP"
    if nginx -t && systemctl reload nginx; then
      echo "POPUP_CMS_ROLLBACK=PASS"
    else
      echo "POPUP_CMS_ROLLBACK=FAILED"
    fi
  fi
  if [[ "$SUCCESS" -ne 1 ]]; then echo "POPUP_CMS_RC09=FAILED"; fi
  exit "$rc"
}
trap rollback EXIT

echo "===== 1. PRECHECK ====="
test -f "$OLD/app/Core/Router.php"
test -f "$SOURCE/public/app/index.html"
test -f "$SOURCE/app/Controllers/Api/PopupCmsController.php"
test -f "$SOURCE/tests/PopupCmsContractTest.php"
test -f "$SOURCE/config/routes.php"
test ! -e "$NEW"
test "$(grep -Fc "$OLD/public" "$MAIN")" -eq 1
test "$(grep -Fc "$OLD/public" "$LOOP")" -eq 1
grep -Fq '/app/assets/index-' "$SOURCE/public/app/index.html"

# Stop safely if the legacy database schema differs from the existing controller.
EXPECTED="id title content button_text button_url desktop_image display_mode delay_seconds frequency priority status start_at created_by created_at updated_by deleted_at"
ACTUAL="$(mysql -N "$DB" -e "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='popups'")"
for col in $EXPECTED; do
  grep -Fxq "$col" <<< "$ACTUAL" || { echo "SCHEMA_MISSING_COLUMN=$col"; exit 1; }
done
echo "POPUP_DB_SCHEMA=PASS"

php -l "$SOURCE/app/Controllers/Api/PopupCmsController.php"
php -l "$SOURCE/config/routes.php"
php -l "$SOURCE/tests/PopupCmsContractTest.php"
OLD_MEMBER="$(status /api/member/dividends)"
OLD_ADMIN="$(status /api/admin/welfare)"
test "$(status /app/admin/cms)" = 200
test "$(status /api/public/welfare)" = 200

mkdir -p "$BACKUP"
cp -a "$MAIN" "$BACKUP/main.conf"
cp -a "$LOOP" "$BACKUP/loop.conf"
echo "PRECHECK=PASS"

echo "===== 2. STAGE ====="
cp -a "$OLD" "$NEW"
cp -a "$SOURCE/config/routes.php" "$NEW/config/routes.php"
cp -a "$SOURCE/app/Controllers/Api/PopupCmsController.php" "$NEW/app/Controllers/Api/PopupCmsController.php"
cp -a "$SOURCE/tests/PopupCmsContractTest.php" "$NEW/tests/PopupCmsContractTest.php"
# The contract test checks the exact frontend source shipped in this build.
# RC08's source tree is older, so stage these two RC09 source files as well.
mkdir -p "$NEW/src/components/common" "$NEW/src/pages"
cp -a "$SOURCE/src/components/common/CampaignModal.jsx" "$NEW/src/components/common/CampaignModal.jsx"
cp -a "$SOURCE/src/pages/AdminDashboardPage.jsx" "$NEW/src/pages/AdminDashboardPage.jsx"
test ! -L "$NEW/public/app"
rm -rf "$NEW/public/app"
mkdir -p "$NEW/public/app"
cp -a "$SOURCE/public/app/." "$NEW/public/app/"
find "$NEW/public/app" -type d -exec chmod 755 {} +
find "$NEW/public/app" -type f -exec chmod 644 {} +

# Preserve the proven RC07 router and application security code.
cmp -s "$OLD/app/Core/Router.php" "$NEW/app/Core/Router.php"
cmp -s "$OLD/app/Core/Session.php" "$NEW/app/Core/Session.php"

(
  cd "$NEW"
  php tests/PopupCmsContractTest.php
  php tests/RouterErrorContractTest.php
)
echo "STAGE_TEST=PASS"

echo "===== 3. ACTIVATE ====="
# Arm rollback before the first Nginx file is changed.
SWITCHED=1
python3 - "$MAIN" "$LOOP" "$OLD/public" "$NEW/public" <<'PY'
from pathlib import Path
import sys
main,loop,old,new=sys.argv[1:]
prepared=[]
for filename in (main,loop):
    p=Path(filename)
    source=p.read_text()
    if source.count(old)!=1: raise RuntimeError("Unexpected active release: "+filename)
    prepared.append((p,source.replace(old,new)))
for p,content in prepared: p.write_text(content)
print("RELEASE_ROOT_SWITCHED=PASS")
PY
nginx -t
systemctl reload nginx
sleep 3

echo "===== 4. SMOKE TEST ====="
for route in /app/ /app/admin/cms /app/admin/popups /app/welfare /api/public/welfare /api/public/popup-campaign; do
  code="$(status "$route")"
  echo "$route HTTP=$code"
  test "$code" = 200
done
ADMIN="$(status /api/admin/popup-campaigns)"
case "$ADMIN" in 302|401|403) ;; *) echo "UNAUTH_ADMIN_API=$ADMIN"; exit 1;; esac
test "$(status /api/member/dividends)" = "$OLD_MEMBER"
test "$(status /api/admin/welfare)" = "$OLD_ADMIN"
curl --noproxy '*' -4 -fsS --max-time 20 --resolve "$HOST:443:127.0.0.1" \
  "https://$HOST/api/public/popup-campaign" | python3 -c '
import json,sys
data=json.load(sys.stdin)
assert data.get("success") is True
assert data.get("data") is None or isinstance(data.get("data"),dict)
print("PUBLIC_POPUP_CONTRACT=PASS")
'
INDEX_ASSET="$(grep -oE '/app/assets/index-[A-Za-z0-9_-]+\.js' "$NEW/public/app/index.html" | head -1)"
test -n "$INDEX_ASSET"
test "$(status "$INDEX_ASSET")" = 200
nginx -t
SUCCESS=1
echo "POPUP_CMS_RC09=PASS"
echo "RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo "DATABASE_SCHEMA=UNCHANGED"
echo "MEMBER_BACKEND=UNCHANGED"
