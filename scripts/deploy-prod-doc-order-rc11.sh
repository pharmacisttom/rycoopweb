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
  "$SRC/tests/DocumentOrderContractTest.php" \
  "$SRC/tests/DocumentCategoriesContractTest.php" \
  "$SRC/app/Controllers/Admin/DocumentCategoryController.php" \
  "$SRC/app/Controllers/Admin/DocumentController.php" \
  "$SRC/config/routes.php"; do
 test -f "$file"
done
test -d "$OLD"
test ! -e "$NEW"
test "$(grep -Fc "$OLD/public" "$MAIN")" -eq 1
test "$(grep -Fc "$OLD/public" "$LOOP")" -eq 1
grep -Fq '/app/assets/index-' "$SRC/public/app/index.html"
COL="$(mysql -N "$DB" -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='documents' AND COLUMN_NAME='sort_order'")"
test "$COL" = 1 || { echo "DOCUMENT_SORT_COLUMN=MISSING"; exit 1; }
# These are existing production columns: no migration is permitted.
for column in id name slug sort_order status deleted_at created_at updated_at; do
  found="$(mysql -N "$DB" -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='document_categories' AND COLUMN_NAME='$column'")"
  test "$found" = 1 || { echo "CATEGORY_COLUMN_MISSING=$column"; exit 1; }
done
echo "CATEGORY_DB_SCHEMA=PASS"
php -l "$SRC/app/Services/AdminCrudService.php"
php -l "$SRC/app/Controllers/Api/SpaApiController.php"
php -l "$SRC/tests/DocumentOrderContractTest.php"
php -l "$SRC/tests/DocumentCategoriesContractTest.php"
php -l "$SRC/app/Controllers/Admin/DocumentCategoryController.php"
php -l "$SRC/app/Controllers/Admin/DocumentController.php"
php -l "$SRC/config/routes.php"
(
 cd "$SRC"
 php tests/DocumentOrderContractTest.php
 php tests/DocumentCategoriesContractTest.php
)
test "$(status /api/public/documents)" = 200
test "$(status /app/admin/documents)" = 200
test "$(status /api/public/popup-campaign)" = 200
OLD_MEMBER="$(status /api/member/dividends)"
OLD_ADMIN="$(status /api/admin/documents)"
OLD_CATEGORY="$(status /api/admin/document-categories)"
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
cp -a "$SRC/tests/DocumentCategoriesContractTest.php" "$NEW/tests/DocumentCategoriesContractTest.php"
cp -a "$SRC/app/Controllers/Admin/DocumentCategoryController.php" "$NEW/app/Controllers/Admin/DocumentCategoryController.php"
cp -a "$SRC/app/Controllers/Admin/DocumentController.php" "$NEW/app/Controllers/Admin/DocumentController.php"
cp -a "$SRC/config/routes.php" "$NEW/config/routes.php"
test ! -L "$NEW/public/app"
rm -rf "$NEW/public/app"
mkdir -p "$NEW/public/app"
cp -a "$SRC/public/app/." "$NEW/public/app/"
find "$NEW/public/app" -type d -exec chmod 755 {} +
find "$NEW/public/app" -type f -exec chmod 644 {} +
cmp -s "$OLD/app/Core/Session.php" "$NEW/app/Core/Session.php"
cmp -s "$OLD/app/Core/Router.php" "$NEW/app/Core/Router.php"
# Routes differ only by the new guarded category management endpoints.
grep -Fq "DocumentCategoryController@update" "$NEW/config/routes.php"
cmp -s "$OLD/app/Controllers/Api/PopupCmsController.php" "$NEW/app/Controllers/Api/PopupCmsController.php"
(
 cd "$NEW"
 php tests/DocumentOrderContractTest.php
 php tests/DocumentCategoriesContractTest.php
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
test "$(status /api/admin/document-categories)" = "$OLD_CATEGORY"
curl --noproxy '*' -4 -fsS --max-time 20 --resolve "$HOST:443:127.0.0.1" \
 "https://$HOST/api/public/documents" | python3 -c '
import json,sys
data=json.load(sys.stdin)
assert data.get("success") is True
rows=data.get("data")
assert isinstance(rows,list)
key=lambda r:(int(r.get("category_sort_order") or 0),int(r.get("category_id") or 0),int(r.get("sort_order") or 0))
assert all(key(rows[i])<=key(rows[i+1]) for i in range(len(rows)-1))
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
