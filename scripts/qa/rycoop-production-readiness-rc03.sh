#!/usr/bin/env bash
# RYCOOPWEB RC03: READ-ONLY readiness report on the production VPS.
# Does not deploy, migrate, restore, reload services, or modify either DB.
set -Eeuo pipefail
umask 077
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
HOST="$(hostname -s)"
EXPECTED_HOST="metrabyte"
PROD_DB="rayongcoop_db"
TEST_DB="rycoopweb_test"
TEST_URL="https://test.rayongcoop.org"
PROD_ROOT="/var/www/rycoopweb/production-releases"
MARKER="RC03_PROD_READINESS"
blockers=0
warn() { printf 'PENDING: %s\n' "$*"; blockers=$((blockers+1)); }
pass() { printf 'PASS: %s\n' "$*"; }
if [[ "$HOST" != "$EXPECTED_HOST" ]]; then
  echo "STOP: unexpected host $HOST; expected $EXPECTED_HOST"
  exit 2
fi
for cmd in sudo mysql nginx php stat find grep; do
  command -v "$cmd" >/dev/null || { echo "STOP: missing $cmd"; exit 2; }
done
echo "===== $MARKER ====="
echo "HOST=$HOST"
echo "OPERATION=READ_ONLY"
echo "===== ENVIRONMENT ISOLATION ====="
for db in "$PROD_DB" "$TEST_DB"; do
  count="$(sudo mysql -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db' AND table_type='BASE TABLE';")"
  [[ "$count" =~ ^[0-9]+$ ]] || { echo "STOP: DB inventory failed"; exit 2; }
  echo "DB=$db TABLES=$count"
done
echo "===== PRODUCTION NGINX TARGET (NO CHANGES) ====="
sudo nginx -t >/dev/null && pass "NGINX_SYNTAX"
# Show only server configuration paths; avoid emitting environment or credentials.
sudo grep -RhsE '^[[:space:]]*(root|alias|fastcgi_pass)[[:space:]]+' \
 /etc/nginx/sites-enabled/rycoopweb-main-ssl 2>/dev/null | head -12 || true
echo "===== GALLERY TABLES ====="
for db in "$PROD_DB" "$TEST_DB"; do
  found="$(sudo mysql -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db' AND table_name IN ('activity_albums','activity_photos');")"
  echo "DB=$db ACTIVITY_TABLES=$found"
  if [[ "$db" == "$TEST_DB" && "$found" != 2 ]]; then warn "TEST gallery schema incomplete"; fi
done
echo "===== BACKUP PRESENCE ====="
BACKUP="/var/backups/rycoop-production/20261118-085443/database.sql.gz"
if sudo test -s "$BACKUP" && sudo gzip -t "$BACKUP"; then
 pass "KNOWN_PROD_DB_BACKUP_ARCHIVE_READABLE"
else
 warn "Known production DB backup absent, empty or corrupt"
fi
# Count-only check; do not display member-level information.
echo "===== PRODUCTION INVARIANT COUNTS (OBSERVATION ONLY) ====="
sudo mysql --table "$PROD_DB" <<'SQL'
SELECT 'members' AS entity, COUNT(*) AS row_count FROM members
UNION ALL SELECT 'member_dividends', COUNT(*) FROM member_dividends
UNION ALL SELECT 'users', COUNT(*) FROM users
UNION ALL SELECT 'board_terms', COUNT(*) FROM board_terms
UNION ALL SELECT 'news', COUNT(*) FROM news;
SQL
echo "===== BLOCKING RELEASE EVIDENCE ====="
warn "Authenticated CSRF invalid/missing token, pending-MFA and denied-RBAC TEST results not attached"
warn "Full isolated production data restore drill and recovery timing not evidenced"
warn "Media/storage + config backup completeness and rollback rehearsal not evidenced"
warn "Production vs TEST migration ledger drift review and 032 application plan not approved"
warn "Security dependency advisories and final release SHA freeze not evidenced"
printf 'READINESS_BLOCKERS=%d\n' "$blockers"
echo "RELEASE_DECISION=HOLD"
echo "NO_DEPLOY_NO_DB_WRITES_NO_SERVICE_RELOAD"
