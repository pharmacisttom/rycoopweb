#!/usr/bin/env bash
# RC10: Popup CMS image preview / actionable CRUD errors, based on deployed RC09.
set -Eeuo pipefail
umask 077
SRC="${1:?Usage: sudo bash scripts/deploy-prod-popup-cms-rc10.sh /var/tmp/rycoop-popup-fix-XXXXXX}"
HOST="www.rayongcoop.org"
OLD="/var/www/rycoopweb/production-releases/prod-popup-cms-rc09-20261120T021542Z"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="/var/www/rycoopweb/production-releases/prod-popup-cms-rc10-$STAMP"
BACKUP="/var/backups/rycoop-production/popup-cms-rc10-$STAMP"
MAIN="/etc/nginx/sites-available/rycoopweb-main-ssl"
LOOP="/etc/nginx/conf.d/rycoop-prod-loopback.conf"
SWITCHED=0
SUCCESS=0
rollback() {
  rc=$?
  trap - EXIT
  if [[ "$SWITCHED" -eq 1 && "$SUCCESS" -ne 1 ]]; then
    echo "===== ROLLBACK ====="
    cp -a "$BACKUP/main.conf" "$MAIN"
    cp -a "$BACKUP/loop.conf" "$LOOP"
    if nginx -t && systemctl reload nginx; then echo "POPUP_RC10_ROLLBACK=PASS"; else echo "POPUP_RC10_ROLLBACK=FAILED"; fi
  fi
  if [[ "$SUCCESS" -ne 1 ]]; then echo "POPUP_RC10=FAILED"; fi
  exit "$rc"
}
trap rollback EXIT
status() {
  curl --noproxy '*' -4 -sS --max-time 20 --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$1"
}
echo "===== 1. PRECHECK ====="
test -f "$SRC/public/app/index.html"
test -f "$SRC/app/Controllers/Api/PopupCmsController.php"
test -f "$SRC/src/pages/AdminPopupCampaignPage.jsx"
test -f "$SRC/src/pages/AdminPopupCampaignPage.css"
test -d "$OLD"
test ! -e "$NEW"
test "$(grep -Fc "$OLD/public" "$MAIN")" -eq 1
test "$(grep -Fc "$OLD/public" "$LOOP")" -eq 1
grep -Fq '/app/assets/index-' "$SRC/public/app/index.html"
php -l "$SRC/app/Controllers/Api/PopupCmsController.php"
php -l "$SRC/config/routes.php"
php -l "$SRC/tests/PopupCmsContractTest.php"
test "$(status /api/public/popup-campaign)" = 200
test "$(status /api/public/welfare)" = 200
OLD_MEMBER="$(status /api/member/dividends)"
OLD_ADMIN="$(status /api/admin/popup-campaigns)"
case "$OLD_ADMIN" in 302|401|403) ;; *) echo "UNAUTH_ADMIN_UNEXPECTED=$OLD_ADMIN"; exit 1;; esac

mkdir -p "$BACKUP"
cp -a "$MAIN" "$BACKUP/main.conf"
cp -a "$LOOP" "$BACKUP/loop.conf"
echo "PRECHECK=PASS"

echo "===== 2. STAGE ====="
cp -a "$OLD" "$NEW"
cp -a "$SRC/app/Controllers/Api/PopupCmsController.php" "$NEW/app/Controllers/Api/PopupCmsController.php"
cp -a "$SRC/src/pages/AdminPopupCampaignPage.jsx" "$NEW/src/pages/AdminPopupCampaignPage.jsx"
cp -a "$SRC/src/pages/AdminPopupCampaignPage.css" "$NEW/src/pages/AdminPopupCampaignPage.css"
test ! -L "$NEW/public/app"
rm -rf "$NEW/public/app"
mkdir -p "$NEW/public/app"
cp -a "$SRC/public/app/." "$NEW/public/app/"
find "$NEW/public/app" -type d -exec chmod 755 {} +
find "$NEW/public/app" -type f -exec chmod 644 {} +
cmp -s "$NEW/app/Core/Router.php" "$OLD/app/Core/Router.php"
cmp -s "$NEW/app/Core/Session.php" "$OLD/app/Core/Session.php"
cmp -s "$NEW/config/routes.php" "$OLD/config/routes.php"
(
 cd "$NEW"
 php tests/PopupCmsContractTest.php
 php tests/RouterErrorContractTest.php
)
echo "STAGE=PASS"

echo "===== 3. ACTIVATE ====="
SWITCHED=1
python3 - "$MAIN" "$LOOP" "$OLD/public" "$NEW/public" <<'PY'
from pathlib import Path
import sys
main,loop,old,new=sys.argv[1:]
changes=[]
for name in (main,loop):
    p=Path(name)
    data=p.read_text()
    if data.count(old)!=1:raise RuntimeError("Unexpected release root: "+name)
    changes.append((p,data.replace(old,new)))
for p,data in changes:p.write_text(data)
print("RELEASE_SWITCH=PASS")
PY
nginx -t
systemctl reload nginx
sleep 3
echo "===== 4. REGRESSION ====="
for route in /app/ /app/admin/cms /app/admin/popups /app/welfare /api/public/welfare /api/public/popup-campaign; do
  code="$(status "$route")"
  echo "$route HTTP=$code"
  test "$code" = 200
done
test "$(status /api/admin/popup-campaigns)" = "$OLD_ADMIN"
test "$(status /api/member/dividends)" = "$OLD_MEMBER"
curl --noproxy '*' -4 -fsS --max-time 20 --resolve "$HOST:443:127.0.0.1" \
  "https://$HOST/api/public/popup-campaign" | python3 -c '
import json,sys
p=json.load(sys.stdin)
assert p.get("success") is True and (p.get("data") is None or isinstance(p["data"],dict))
print("PUBLIC_POPUP_API=PASS")
'
INDEX_ASSET="$(grep -oE '/app/assets/index-[A-Za-z0-9_-]+\.js' "$NEW/public/app/index.html" | head -1)"
test -n "$INDEX_ASSET"
test "$(status "$INDEX_ASSET")" = 200
nginx -t
SUCCESS=1
echo "POPUP_RC10=PASS"
echo "RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo "DATABASE=UNCHANGED"
echo "MEMBER_BACKEND=UNCHANGED"
echo "BROWSER_CRUD_UAT=PENDING"
