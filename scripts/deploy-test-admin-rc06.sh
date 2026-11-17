#!/usr/bin/env bash
# TEST-only, guarded Admin RC06 deployment. Execute as coopadmin, never as root.
# Requires a detached worktree checked out at a reviewed commit of origin/Test.
set -Eeuo pipefail

if [ "$(id -u)" -eq 0 ]; then
  echo "STOP: run as the normal deploy user, not root" >&2
  exit 1
fi

WORK="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$WORK"
SHA="$(git rev-parse HEAD)"
BASE="/var/www/rycoopweb"
STAGE="$BASE/test-releases/admin-rc06-$SHA"
SHARED="$BASE/shared-test"
CONF="/etc/nginx/sites-enabled/rycoopweb-test-v2"
REAL="$(readlink -f "$CONF")"
HOST="test.rayongcoop.org"
OLD="$BASE/test-releases/adminlte-rc04-651e3893a058b0ea04f9d11c82ef139190957cd8"
TMP="$(mktemp)"
HEADERS="$(mktemp)"
BACKUP="/etc/nginx/rycoop-admin-rc06-$(date -u +%Y%m%dT%H%M%SZ).bak"
CHANGED=0
SUCCESS=0

cleanup() {
  rc=$?
  trap - EXIT
  if [ "$CHANGED" -eq 1 ] && [ "$SUCCESS" -ne 1 ]; then
    echo "ROLLBACK: restoring previous TEST nginx config"
    sudo cp "$BACKUP" "$REAL"
    sudo nginx -t
    sudo systemctl reload nginx
  fi
  rm -f "$TMP" "$HEADERS"
  exit "$rc"
}
trap cleanup EXIT

echo "===== 1. PREFLIGHT (TEST ONLY) ====="
sudo -v
git diff --quiet
git diff --cached --quiet
test -s package-lock.json
test -s scripts/build-adminlte-scoped.mjs
test -s tests/AdminLteIntegrationTest.mjs
test -n "$REAL"
test "$REAL" != "/etc/nginx/sites-enabled/rycoopweb-main-ssl"
sudo test -r "$SHARED/.env"
sudo test -d "$SHARED/uploads"
sudo test -d "$SHARED/private-activity-photos"
sudo nginx -t
sudo cat "$REAL" > "$TMP"

python3 - "$TMP" "$OLD" "$STAGE" <<'PY'
import sys
from pathlib import Path
p, old, new = sys.argv[1:]
config = Path(p).read_text()
if config.count(old) != 3:
    raise SystemExit("STOP: active TEST release differs from approved baseline")
if new in config:
    raise SystemExit("STOP: RC06 release is already active")
if "server_name test.rayongcoop.org;" not in config:
    raise SystemExit("STOP: incorrect virtual host")
if "RYCOOPWEB TEST - Authorized Access" not in config:
    raise SystemExit("STOP: TEST Basic Auth missing")
Path(p).write_text(config.replace(old,new))
print("PASS: 3 TEST release paths prepared; no PROD config edits")
PY

echo "===== 2. INSTALL REAL ADMINLTE PACKAGE ====="
composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
npm ci --include=dev --no-audit --no-fund
node tests/AdminLteIntegrationTest.mjs
npm run build
php tests/AdminUiMemberNonRegressionTest.php
php tests/MediaLibraryApiRouteTest.php
node tests/AdminTypographyLegacyRegressionTest.mjs
node tests/AdminHealthRenderRegressionTest.mjs

echo "===== 3. PREPARE IMMUTABLE RELEASE ====="
test -s public/index.php
test -s public/app/index.html
test -s vendor/autoload.php
sudo test ! -e "$STAGE"
sudo install -d -o root -g www-data -m 0750 "$STAGE"
sudo rsync -a --exclude='.git/' --exclude='.env' \
  --exclude='node_modules/' --exclude='storage/' \
  "$WORK/" "$STAGE/"
sudo install -o root -g www-data -m 0640 "$SHARED/.env" "$STAGE/.env"
sudo grep -qx 'APP_ENV=test' "$STAGE/.env"
sudo grep -qx 'DB_DATABASE=rycoopweb_test' "$STAGE/.env"
sudo install -d -o root -g www-data -m 2750 "$STAGE/storage"
for dir in logs cache tmp; do
  sudo install -d -o www-data -g www-data -m 2770 "$STAGE/storage/$dir"
done
for dir in uploads private-activity-photos; do
  sudo ln -s "$SHARED/$dir" "$STAGE/storage/$dir"
done
sudo -u www-data test -r "$STAGE/public/index.php"
sudo -u www-data test -r "$STAGE/public/app/index.html"
sudo -u www-data test -r "$STAGE/vendor/autoload.php"
sudo -u www-data test -r "$STAGE/.env"
sudo -u www-data test -w "$STAGE/storage/uploads"

echo "===== 4. BACKUP AND ACTIVATE TEST ====="
sudo cp "$REAL" "$BACKUP"
sudo chmod 0600 "$BACKUP"
sudo install -o root -g root -m 0644 "$TMP" "$REAL"
CHANGED=1
sudo nginx -t
sudo systemctl reload nginx
sudo grep -Fq "$STAGE/public/index.php" "$REAL"
test "$(sudo grep -Fc "root $STAGE/public;" "$REAL")" -eq 2

echo "===== 5. VERIFY TEST GATEWAY ====="
HTTP="$(curl --noproxy '*' -sS --max-time 15 \
  --resolve "$HOST:443:127.0.0.1" \
  -D "$HEADERS" -o /dev/null -w '%{http_code}' \
  "https://$HOST/app/")"
test "$HTTP" = "401"
grep -qi '^WWW-Authenticate: Basic' "$HEADERS"

SUCCESS=1
echo "PASS: ADMINLTE RC06 TEST CUTOVER"
echo "COMMIT=$SHA"
echo "STAGE=$STAGE"
echo "HTTP=$HTTP (TEST gateway authentication expected)"
echo "BACKUP=$BACKUP"
echo "PRODUCTION UNCHANGED"
echo "NOTE: Browser admin/member/PDPA/dividend UAT is still required."
