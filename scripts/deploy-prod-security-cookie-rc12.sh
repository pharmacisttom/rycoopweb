#!/usr/bin/env bash
# RYCOOPWEB Security Center + cookie consent RC12; preserves active RC11, no migrations.
set -Eeuo pipefail
umask 077
SRC="${1:?Usage: sudo bash scripts/deploy-prod-security-cookie-rc12.sh /var/tmp/rycoop-rc12-XXXXXX}"
HOST="www.rayongcoop.org"
DB="rayongcoop_db"
OLD="/var/www/rycoopweb/production-releases/prod-doc-order-rc11-20261120T025318Z"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="/var/www/rycoopweb/production-releases/prod-security-cookie-rc12-$STAMP"
BACKUP="/var/backups/rycoop-production/security-cookie-rc12-$STAMP"
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
  if nginx -t && systemctl reload nginx; then echo "RC12_ROLLBACK=PASS"; else echo "RC12_ROLLBACK=FAILED"; fi
 fi
 [[ "$SUCCESS" -eq 1 ]] || echo "RC12_DEPLOY=FAILED"
 exit "$rc"
}
trap cleanup EXIT

echo "===== 1. READ-ONLY PRECHECK ====="
for file in \
 "$SRC/public/app/index.html" \
 "$SRC/src/components/common/CookieConsent.jsx" \
 "$SRC/src/pages/AdminControlCenterPage.jsx" \
 "$SRC/app/Controllers/Public/ApiController.php" \
 "$SRC/app/Services/AdminControlCenterService.php" \
 "$SRC/config/routes.php" \
 "$SRC/tests/SecurityCookieRc12ContractTest.php"; do
 test -f "$file"
done
test -d "$OLD"
test ! -e "$NEW"
test "$(grep -Fc "$OLD/public" "$MAIN")" -eq 1
test "$(grep -Fc "$OLD/public" "$LOOP")" -eq 1
grep -Fq '/app/assets/index-' "$SRC/public/app/index.html"
for col in anonymous_consent_id consent_version necessary functional analytics marketing third_party ip_hash user_agent consented_at updated_at; do
 N="$(mysql -N "$DB" -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cookie_consents' AND COLUMN_NAME='$col'")"
 test "$N" = 1 || { echo "COOKIE_COLUMN_MISSING=$col"; exit 1; }
done
for t in security_audit_logs audit_logs login_logs cookie_consents popup_events; do
 N="$(mysql -N "$DB" -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t'")"
 test "$N" = 1 || { echo "LOG_TABLE_MISSING=$t"; exit 1; }
done
php -l "$SRC/app/Controllers/Public/ApiController.php"
php -l "$SRC/app/Services/AdminControlCenterService.php"
php -l "$SRC/config/routes.php"
php -l "$SRC/tests/SecurityCookieRc12ContractTest.php"
(cd "$SRC" && php tests/SecurityCookieRc12ContractTest.php)
test "$(status /app/)" = 200
test "$(status /api/public/documents)" = 200
test "$(status /api/public/popup-campaign)" = 200
OLD_MEMBER="$(status /api/member/dividends)"
OLD_SECURITY="$(status /api/admin/security/overview)"
case "$OLD_SECURITY" in 302|401|403) ;; *) echo "UNAUTH_SECURITY_UNEXPECTED=$OLD_SECURITY"; exit 1;; esac
mkdir -p "$BACKUP"
cp -a "$MAIN" "$BACKUP/main.conf"
cp -a "$LOOP" "$BACKUP/loop.conf"
echo "PRECHECK=PASS"

echo "===== 2. IMMUTABLE RELEASE ====="
cp -a "$OLD" "$NEW"
cp -a "$SRC/app/Controllers/Public/ApiController.php" "$NEW/app/Controllers/Public/ApiController.php"
cp -a "$SRC/app/Services/AdminControlCenterService.php" "$NEW/app/Services/AdminControlCenterService.php"
cp -a "$SRC/config/routes.php" "$NEW/config/routes.php"
cp -a "$SRC/src/components/common/CookieConsent.jsx" "$NEW/src/components/common/CookieConsent.jsx"
cp -a "$SRC/src/pages/AdminControlCenterPage.jsx" "$NEW/src/pages/AdminControlCenterPage.jsx"
cp -a "$SRC/tests/SecurityCookieRc12ContractTest.php" "$NEW/tests/SecurityCookieRc12ContractTest.php"
test ! -L "$NEW/public/app"
rm -rf "$NEW/public/app"
mkdir -p "$NEW/public/app"
cp -a "$SRC/public/app/." "$NEW/public/app/"
find "$NEW/public/app" -type d -exec chmod 755 {} +
find "$NEW/public/app" -type f -exec chmod 644 {} +
cmp -s "$OLD/app/Core/Session.php" "$NEW/app/Core/Session.php"
cmp -s "$OLD/app/Core/Auth.php" "$NEW/app/Core/Auth.php"
cmp -s "$OLD/app/Core/Router.php" "$NEW/app/Core/Router.php"
cmp -s "$OLD/app/Controllers/Api/PopupCmsController.php" "$NEW/app/Controllers/Api/PopupCmsController.php"
cmp -s "$OLD/app/Controllers/Admin/DocumentCategoryController.php" "$NEW/app/Controllers/Admin/DocumentCategoryController.php"
(cd "$NEW" && php tests/SecurityCookieRc12ContractTest.php && php tests/DocumentOrderContractTest.php && php tests/DocumentCategoriesContractTest.php && php tests/PopupCmsContractTest.php && php tests/RouterErrorContractTest.php)
echo "RELEASE_TEST=PASS"

echo "===== 3. NGINX SWITCH ====="
SWITCHED=1
python3 - "$MAIN" "$LOOP" "$OLD/public" "$NEW/public" <<'PY'
from pathlib import Path
import sys
files=[Path(p) for p in sys.argv[1:3]]
old,new=sys.argv[3:]
changes=[]
for p in files:
  data=p.read_text()
  if data.count(old)!=1: raise RuntimeError("Unexpected release root "+str(p))
  changes.append((p,data.replace(old,new)))
for p,data in changes:p.write_text(data)
print("RELEASE_SWITCH=PASS")
PY
nginx -t
systemctl reload nginx
sleep 3

echo "===== 4. REGRESSION ====="
for path in /app/ /app/admin/security /app/admin/audit-logs /app/admin/documents /app/admin/popups /api/public/documents /api/public/popup-campaign /api/public/welfare; do
 HTTP="$(status "$path")"
 echo "$path HTTP=$HTTP"
 test "$HTTP" = 200
done
test "$(status /api/member/dividends)" = "$OLD_MEMBER"
test "$(status /api/admin/security/overview)" = "$OLD_SECURITY"
# Only the CSRF guard rejects this unauthenticated POST; zero database writes.
NO_CSRF="$(curl --noproxy '*' -4 -sS --max-time 20 --resolve "$HOST:443:127.0.0.1" \
 -X POST -H 'Accept: application/json' -H 'Content-Type: application/json' \
 --data '{}' -o /dev/null -w '%{http_code}' "https://$HOST/api/cookie-consent")"
test "$NO_CSRF" = 419 || { echo "COOKIE_CSRF_UNEXPECTED=$NO_CSRF"; exit 1; }
echo "COOKIE_CSRF_GUARD=PASS"
ASSET="$(grep -oE '/app/assets/index-[A-Za-z0-9_-]+\.js' "$NEW/public/app/index.html" | head -1)"
test -n "$ASSET"
test "$(status "$ASSET")" = 200
nginx -t
SUCCESS=1
echo "SECURITY_COOKIE_RC12_DEPLOY=PASS"
echo "RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo "DATABASE_SCHEMA=UNCHANGED"
echo "MEMBER_PORTAL_CONFIG=UNCHANGED"
echo "COOKIE_WRITE_BROWSER_UAT=PENDING"
