#!/usr/bin/env bash
# RYCOOPWEB Production Admin UI & SweetAlert2 RC16
# Replaces revisions card list with Data Table, standardizes SweetAlert2, and verifies Term 38 intact.
set -Eeuo pipefail
umask 077
SRC="${1:?Usage: sudo bash scripts/deploy-prod-admin-swal2-rc16.sh /var/tmp/rycoop-rc16-XXXXXX}"
HOST="www.rayongcoop.org"
DB="rayongcoop_db"
OLD="/var/www/rycoopweb/production-releases/prod-home-rc15-upload-20261120T151414Z"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="/var/www/rycoopweb/production-releases/prod-admin-swal2-rc16-$STAMP"
BACKUP="/var/backups/rycoop-production/admin-swal2-rc16-$STAMP"
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
  if nginx -t && systemctl reload nginx; then echo "RC16_ROLLBACK=PASS"; else echo "RC16_ROLLBACK=FAILED"; fi
 fi
 [[ "$SUCCESS" -eq 1 ]] || echo "RC16_DEPLOY=FAILED"
 exit "$rc"
}
trap cleanup EXIT

echo "===== 1. READ-ONLY PRECHECK ====="
for file in \
 "$SRC/public/app/index.html" \
 "$SRC/app/Services/PublicContentService.php" \
 "$SRC/src/pages/PublicContentAdminPage.jsx" \
 "$SRC/src/utils/swal.js" \
 "$SRC/tests/PublicContentWorkflowTest.php"; do
 test -f "$file"
done
test -d "$OLD"
test ! -e "$NEW"
test "$(grep -Fc "$OLD/public" "$MAIN")" -eq 1
test "$(grep -Fc "$OLD/public" "$LOOP")" -eq 1
grep -Fq '/app/assets/index-' "$SRC/public/app/index.html"

# Verify Board Term 38 intact
TERM38_COUNT=$(mysql -N "$DB" -e "SELECT COUNT(*) FROM board_terms WHERE term_number = 38 AND status = 'published' AND is_current = 1")
test "$TERM38_COUNT" -eq 1 || { echo "PRECHECK_FAIL: Board Term 38 missing or not published"; exit 1; }

TERM38_MEMBERS=$(mysql -N "$DB" -e "SELECT COUNT(*) FROM board_members bm JOIN board_terms bt ON bm.term_id = bt.id WHERE bt.term_number = 38")
test "$TERM38_MEMBERS" -eq 21 || { echo "PRECHECK_FAIL: Board Term 38 member count expected 21, got $TERM38_MEMBERS"; exit 1; }

php -l "$SRC/app/Services/PublicContentService.php"
php -l "$SRC/tests/PublicContentWorkflowTest.php"

test "$(status /app/)" = 200
test "$(status /api/public/documents)" = 200
test "$(status /api/public/popup-campaign)" = 200

mkdir -p "$BACKUP"
cp -a "$MAIN" "$BACKUP/main.conf"
cp -a "$LOOP" "$BACKUP/loop.conf"
echo "PRECHECK=PASS"

echo "===== 2. IMMUTABLE RELEASE ====="
cp -a "$OLD" "$NEW"
cp -a "$SRC/app/Services/PublicContentService.php" "$NEW/app/Services/PublicContentService.php"

test ! -L "$NEW/public/app"
rm -rf "$NEW/public/app"
mkdir -p "$NEW/public/app"
cp -a "$SRC/public/app/." "$NEW/public/app/"
find "$NEW/public/app" -type d -exec chmod 755 {} +
find "$NEW/public/app" -type f -exec chmod 644 {} +

# Copy src source files for auditability
mkdir -p "$NEW/src"
cp -a "$SRC/src/." "$NEW/src/"
cp -a "$SRC/package.json" "$NEW/package.json"
test -f "$SRC/package-lock.json" && cp -a "$SRC/package-lock.json" "$NEW/package-lock.json"

(cd "$NEW" && php tests/PublicContentWorkflowTest.php && php tests/PopupCmsContractTest.php && php tests/RouterErrorContractTest.php)
echo "RELEASE_TEST=PASS"

echo "===== 3. NGINX SWITCH ====="
SWITCHED=1
python3 - "$MAIN" "$LOOP" "$OLD/public" "$NEW/public" <<'PY'
from pathlib import Path
import sys
files=[Path(p) for p in sys.argv[1:3]]
old_root, new_root = sys.argv[3], sys.argv[4]
for f in files:
    content = f.read_text(encoding='utf-8')
    assert old_root in content, f"missing {old_root} in {f}"
    f.write_text(content.replace(old_root, new_root), encoding='utf-8')
PY

nginx -t
systemctl reload nginx
echo "NGINX_RELOAD=PASS"

echo "===== 4. POST-DEPLOY VERIFICATION ====="
test "$(status /app/)" = 200
test "$(status /api/public/documents)" = 200
test "$(status /api/public/popup-campaign)" = 200

# Postcheck Board Term 38 intact
POST_TERM38=$(mysql -N "$DB" -e "SELECT COUNT(*) FROM board_terms WHERE term_number = 38 AND status = 'published' AND is_current = 1")
test "$POST_TERM38" -eq 1

POST_MEMBERS=$(mysql -N "$DB" -e "SELECT COUNT(*) FROM board_members bm JOIN board_terms bt ON bm.term_id = bt.id WHERE bt.term_number = 38")
test "$POST_MEMBERS" -eq 21

SUCCESS=1
echo "RC16_DEPLOY=SUCCESS"
echo "NEW_RELEASE=$NEW"
