#!/usr/bin/env bash
# TEST-only layout and board RC01 deploy. Run as coopadmin from a detached reviewed worktree.
set -Eeuo pipefail
[[ "$(id -u)" -ne 0 ]] || { echo 'STOP: non-root only'; exit 1; }
WORK="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$WORK"
SHA="$(git rev-parse HEAD)"
[[ "${EXPECTED_SHA:-}" == "$SHA" && "$SHA" =~ ^[a-f0-9]{40}$ ]] || { echo 'STOP: SHA not approved'; exit 1; }
git diff --quiet && git diff --cached --quiet || { echo 'STOP: dirty worktree'; exit 1; }
BASE='/var/www/rycoopweb'
OLD="$BASE/test-releases/layout-board-rc01-3ef63634419eafa84727135188d892508108ef0d"
NEW="$BASE/test-releases/layout-board-rc01-$SHA"
SHARED="$BASE/shared-test"
CONF='/etc/nginx/sites-enabled/rycoopweb-test-v2'
PROD_CONF='/etc/nginx/sites-enabled/rycoopweb-main-ssl'
REAL="$(readlink -f "$CONF")"
TMP="$(mktemp)"
AUDIT="$(mktemp)"
HEADERS="$(mktemp)"
BACKUP="/etc/nginx/rycoop-layout-board-rc01-$(date -u +%Y%m%dT%H%M%SZ).bak"
CHANGED=0
SUCCESS=0
finish() {
  rc=$?
  trap - EXIT
  if [[ "$CHANGED" == 1 && "$SUCCESS" != 1 ]]; then
    echo 'ROLLBACK: restoring previous TEST Nginx'
    sudo cp "$BACKUP" "$REAL"
    sudo nginx -t
    sudo systemctl reload nginx
  fi
  rm -f "$TMP" "$AUDIT" "$HEADERS"
  exit "$rc"
}
trap finish EXIT
echo '===== 1. TEST-ONLY PREFLIGHT ====='
[[ -f "$CONF" && -f "$PROD_CONF" && "$REAL" != "$(readlink -f "$PROD_CONF")" ]] || { echo 'STOP: unsafe virtual host'; exit 1; }
sudo -v
sudo test -r "$SHARED/.env"
sudo test -d "$SHARED/uploads"
sudo test -d "$SHARED/private-activity-photos"
sudo test -f "$OLD/public/app/index.html"
sudo test ! -e "$NEW"
sudo nginx -t
sudo cat "$REAL" > "$TMP"
python3 - "$TMP" "$OLD" "$NEW" <<'PY'
from pathlib import Path
import sys
path, old, new = sys.argv[1:]
config = Path(path).read_text()
if config.count(old) != 3 or new in config:
    raise SystemExit('STOP: active TEST release differs from approved current TEST RC01 release')
if 'server_name test.rayongcoop.org;' not in config:
    raise SystemExit('STOP: incorrect TEST host')
if 'RYCOOPWEB TEST - Authorized Access' not in config:
    raise SystemExit('STOP: TEST Basic Auth missing')
Path(path).write_text(config.replace(old, new))
print('PASS: three TEST release paths prepared')
PY
echo '===== 2. BUILD WITH SECURITY CHECK ====='
test -s package-lock.json && test -s composer.json
composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader
npm ci --include=dev --no-fund --no-audit
npm audit --omit=dev --audit-level=moderate
set +e
npm audit --json > "$AUDIT"
audit_status=$?
set -e
[[ "$audit_status" -le 1 ]] || { echo 'STOP: audit command error'; exit 1; }
if [[ "$audit_status" -eq 1 ]]; then node scripts/check-test-only-npm-advisory-exception.mjs "$AUDIT"; fi
python3 tests/AdminSidebarPublicContentRouteTest.py
python3 tests/HomepageLayoutBoardContractTest.py
php -l config/routes.php
php -l app/Services/HomepageLayoutService.php
php -l app/Services/SiteNavigationCopyService.php
php -l app/Controllers/Admin/HomepageLayoutController.php
php -l app/Controllers/Admin/SiteNavigationCopyController.php
node tests/AdminLteIntegrationTest.mjs
npm run build
test -s public/app/index.html && test -d public/app/assets
test -s vendor/autoload.php
php tests/AdminUiMemberNonRegressionTest.php
echo '===== 3. CREATE IMMUTABLE TEST RELEASE ====='
sudo install -d -o root -g www-data -m 0750 "$NEW"
sudo rsync -a --exclude='.git/' --exclude='.env' --exclude='.env.*' --exclude='node_modules/' --exclude='storage/' --exclude='*.sql' --exclude='*.log' "$WORK/" "$NEW/"
sudo install -o root -g www-data -m 0640 "$SHARED/.env" "$NEW/.env"
sudo grep -qx 'APP_ENV=test' "$NEW/.env"
sudo grep -qx 'DB_DATABASE=rycoopweb_test' "$NEW/.env"
sudo install -d -o root -g www-data -m 2750 "$NEW/storage"
for d in logs cache tmp; do sudo install -d -o www-data -g www-data -m 2770 "$NEW/storage/$d"; done
for d in uploads private-activity-photos; do sudo ln -s "$SHARED/$d" "$NEW/storage/$d"; done
sudo -u www-data test -r "$NEW/public/index.php"
sudo -u www-data test -r "$NEW/public/app/index.html"
sudo -u www-data test -r "$NEW/vendor/autoload.php"
sudo -u www-data test -r "$NEW/.env"
sudo mysql --skip-column-names rycoopweb_test -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='rycoopweb_test' AND TABLE_NAME IN ('site_settings','public_content_revisions','audit_logs','media')" | grep -qx '4'
echo '===== 4. ACTIVATE TEST ONLY ====='
sudo cp "$REAL" "$BACKUP"
sudo chmod 0600 "$BACKUP"
sudo install -o root -g root -m 0644 "$TMP" "$REAL"
CHANGED=1
sudo nginx -t
sudo systemctl reload nginx
sudo grep -Fq "$NEW/public/index.php" "$REAL"
[[ "$(sudo grep -Fc "root $NEW/public;" "$REAL")" == 2 ]]
HTTP="$(curl --noproxy '*' -sS --max-time 15 --resolve 'test.rayongcoop.org:443:127.0.0.1' -D "$HEADERS" -o /dev/null -w '%{http_code}' 'https://test.rayongcoop.org/app/')"
[[ "$HTTP" == 401 ]] || { echo "STOP: gateway returned $HTTP"; exit 1; }
grep -qi '^WWW-Authenticate: Basic' "$HEADERS"
SUCCESS=1
echo 'PASS: LAYOUT-BOARD RC01 DEPLOYED TO TEST'
echo "COMMIT=$SHA"
echo "ACTIVE_STAGE=$NEW"
echo "PREVIOUS_STAGE=$OLD"
echo "NGINX_BACKUP=$BACKUP"
echo "GATEWAY_HTTP=$HTTP (Basic Auth expected)"
echo 'PRODUCTION UNCHANGED'
echo 'BROWSER AND DATABASE UAT REQUIRED BEFORE PRODUCTION'
