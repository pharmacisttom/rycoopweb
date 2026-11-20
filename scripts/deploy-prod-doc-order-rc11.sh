#!/usr/bin/env bash
# RYCOOPWEB RC11 document ordering — based on active RC10, no schema migrations.
set -Eeuo pipefail
umask 077
SRC="${1:?Usage: sudo bash scripts/deploy-prod-doc-order-rc11.sh /var/tmp/rycoop-doc-order-XXXXXX}"
HOST="www.rayongcoop.org"
DB="rayongcoop_db"
OLD="/var/www/rycoopweb/production-releases/prod-popup-cms-rc10-20261120T022758Z"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="/var/www/rycoopweb/production-releases/prod-doc-order-rc11-$STAMP"
BACKUP="/var/backups/rycoop-production/doc-order-rc11-$STAMP"
MAIN="/etc/nginx/sites-available/rycoopweb-main-ssl"
LOOP="/etc/nginx/conf.d/rycoop-prod-loopback.conf"
SWITCHED=0
SUCCESS=0

status() {
 curl --noproxy '*' -4 -sS --max-time 20 --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$1"
}
cleanup() {
 rc=$?
 trap - EXIT
 if [[ "$SWITCHED" -eq 1 && "$SUCCESS" -ne 1 ]]; then
   echo "===== ROLLBACK ====="
   cp -a "$BACKUP/main.conf" "$MAIN"
   cp -a "$BACKUP/loop.conf" "$LOOP"
   if nginx -t && systemctl reload nginx; then echo "DOC_ORDER_ROLLBACK=PASS"; else echo "DOC_ORDER_ROLLBACK=FAILED"; fi
 fi
 if [[ "$SUCCESS" -ne 1 ]]; then echo "DOC_ORDER_RC11=FAILED"; fi
 exit "$rc"
}
trap cleanup EXIT

echo "===== 1. PRECHECK ====="
for file in \
  "$SRC/public/app/index.html" \
  "$SRC/app/Services/AdminCrudService.php" \
  "$SRC/app/Controllers/Api/SpaApiController.php" \
  "$SRC/src/pages/AdminStoredContentPage.jsx" \
  "$SRC/tests/DocumentOrderContractTest.php"; do
 test -f "$file"
done
test -d "$OLD"
test ! -e "$NEW"
test "$(grep -Fc "$OLD/public" "$MAIN")" -eq 1
test "$(grep -Fc "$OLD/public" "$LOOP")" -eq 1
grep -Fq '/app/assets/index-' "$SRC/public/app/index.html"
COL="$(mysql -N "$DB" -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documents' AND COLUMN_NAME='sort_order'")"
test "$COL" = 1 || { echo "DOCUMENT_SORT_COLUMN=MISSING"; exit 1; }
php -l "$SRC/app/Services/AdminCrudService.php"
php -l "$SRC/app/Controllers/Api/SpaApiController.php"
php -l "$SRC/tests/DocumentOrderContractTest.php"
(
 cd "$SRC"
 php tests/DocumentOrderContractTest.php
)
test "$(status /api/public/documents)" = 200
test "$(status /app/admin/documents)" = 200
test "$(status /api/public/popup-campaign)" = 200
OLD_MEMBER="$(status /api/member/dividends)"
OLD_ADMIN="$(status /api/admin/documents)"
case "$OLD_ADMIN" in 302|401|403) ;; *) echo "UNAUTH_ADMIN_UNEXPECTED=$OLD_ADMIN"; exit 1;; esac
mkdir -p "$BACKUP"
cp -a "$MAIN" "$BACKUP/main.conf"
cp -a "$LOOP" "$BACKUP/loop.conf"
echo "PRECHECK=PASS"

echo "===== 2. STAGE ====="
cp -a "$OLD" "$NEW"
cp -a "$SRC/app/Services/AdminCrudService.php" "$NEW/app/Services/AdminCrudService.php"
cp -a "$SRC/app/Controllers/Api/SpaApiController.php" "$NEW/app/Controllers/Api/SpaApiController.php"
cp -a "$SRC/src/pages/AdminStoredContentPage.jsx" "$NEW/src/pages/AdminStoredContentPage.jsx"
cp -a "$SRC/tests/DocumentOrderContractTest.php" "$NEW/tests/DocumentOrderContractTest.php"
test ! -L "$NEW/public/app"
rm -rf "$NEW/public/app"
mkdir -p "$NEW/public/app"
cp -a "$SRC/public/app/." "$NEW/public/app/"
find "$NEW/public/app" -type d -exec chmod 755 {} +
find "$NEW/public/app" -type f -exec chmod 644 {} +
cmp -s "$OLD/app/Core/Session.php" "$NEW/app/Core/Session.php"
cmp -s "$OLD/app/Core/Router.php" "$NEW/app/Core/Router.php"
cmp -s "$OLD/config/routes.php" "$NEW/config/routes.php"
cmp -s "$OLD/app/Controllers/Api/PopupCmsController.php" "$NEW/app/Controllers/Api/PopupCmsController.php"
(
 cd "$NEW"
 php tests/DocumentOrderContractTest.php
 php tests/PopupCmsContractTest.php
 php tests/RouterErrorContractTest.php
)
echo "STAGE_TEST=PASS"

echo "===== 3. SWITCH ====="
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
for route in /app/ /app/admin/documents /app/admin/popups /app/welfare /api/public/documents /api/public/popup-campaign /api/public/welfare; do
 code="$(status "$route")"
 echo "$route HTTP=$code"
 test "$code" = 200
done
test "$(status /api/member/dividends)" = "$OLD_MEMBER"
test "$(status /api/admin/documents)" = "$OLD_ADMIN"
curl --noproxy '*' -4 -fsS --max-time 20 --resolve "$HOST:443:127.0.0.1" \
 "https://$HOST/api/public/documents" | python3 -c '
import json,sys
data=json.load(sys.stdin)
assert data.get("success") is True
rows=data.get("data")
assert isinstance(rows,list)
key=lambda r:(int(r.get("sort_order") or 0),-(int(r.get("id") or 0)))
assert all(int(rows[i].get("sort_order") or 0)<=int(rows[i+1].get("sort_order") or 0) for i in range(len(rows)-1))
print("DOCUMENT_PUBLIC_ORDER=PASS")
print("DOCUMENT_COUNT="+str(len(rows)))
'
INDEX_ASSET="$(grep -oE '/app/assets/index-[A-Za-z0-9_-]+\.js' "$NEW/public/app/index.html" | head -1)"
test -n "$INDEX_ASSET"
test "$(status "$INDEX_ASSET")" = 200
nginx -t
SUCCESS=1
echo "DOC_ORDER_RC11=PASS"
echo "RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo "DATABASE=UNCHANGED"
echo "MEMBER_BACKEND=UNCHANGED"
echo "DOCUMENT_ORDER_BROWSER_UAT=PENDING"
