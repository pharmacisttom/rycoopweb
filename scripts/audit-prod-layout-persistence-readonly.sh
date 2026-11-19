#!/usr/bin/env bash
# RYCOOPWEB read-only production/test layout persistence audit.
# No data writes, no credentials or JSON values are printed.
set -Eeuo pipefail
umask 077
MAIN="/etc/nginx/sites-enabled/rycoopweb-main-ssl"
test -r "$MAIN" || { echo "ERROR: nginx config missing" >&2; exit 1; }
LIVE_ROOT="$(sed -n 's/^[[:space:]]*root \(\/var\/www\/rycoopweb\/production-releases\/[^ ;]*\)\/public;$/\1/p' "$MAIN" | head -1)"
test -n "$LIVE_ROOT" && test -f "$LIVE_ROOT/.env" || { echo "ERROR: cannot resolve active release/env" >&2; exit 1; }
echo "===== ACTIVE PRODUCTION ====="
echo "RELEASE=$LIVE_ROOT"
INDEX_ASSET="$(grep -oE '/app/assets/[^" ]+\.js' "$LIVE_ROOT/public/app/index.html" | head -1 || :)"
echo "INDEX_ASSET=$INDEX_ASSET"
echo "===== SAFE ENV LABELS (no credentials) ====="
for key in APP_ENV APP_URL DB_HOST DB_PORT DB_DATABASE; do
  value="$(sed -n "s/^${key}=//p" "$LIVE_ROOT/.env" | tail -1 | tr -d '\r' | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")"
  if [[ "$key" == "DB_HOST" ]]; then
    case "$value" in 127.0.0.1|localhost|::1) echo "$key=LOCALHOST";; '') echo "$key=UNSET";; *) echo "$key=NON_LOCAL_REDACTED";; esac
  else echo "$key=${value:-UNSET}"; fi
done
echo "===== NGINX ROUTING EVIDENCE ====="
grep -nE 'root /var/www/rycoopweb|fastcgi_pass|proxy_pass|include.*rycoop|location .*api' "$MAIN" | head -65 || :
echo "===== DATABASE METADATA ====="
mysql --batch --skip-column-names -e "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('rayongcoop_db','rycoopweb_test','rayongcoop_test') ORDER BY SCHEMA_NAME" | while IFS= read -r db; do
  echo "DATABASE=$db"
  mysql --batch --table "$db" -e "
    SELECT \`key\`, updated_at, CHAR_LENGTH(\`value\`) AS value_length,
      LEFT(SHA2(\`value\`,256),16) AS value_sha256_prefix
    FROM site_settings
    WHERE \`key\` IN (
      'site_layout_home_v1','site_layout_home_draft_v1',
      'site_chrome_visibility_v1','site_chrome_visibility_draft_v1',
      'dynamic_home_sections_v1','dynamic_home_sections_draft_v1',
      'site_navigation_copy_v1','site_navigation_copy_draft_v1'
    ) ORDER BY \`key\`;"
  mysql --batch --table "$db" -e "
    SELECT module, action, COUNT(*) AS actions_count, MAX(created_at) AS latest_event
    FROM audit_logs WHERE module IN ('site_layout','site_chrome','dynamic_layout','site_copy')
    GROUP BY module,action ORDER BY module,action;"
done
echo "===== FRONTEND STALE ENV LABELS ====="
if grep -q 'ฐานข้อมูล TEST\|หน้าเว็บ TEST' "$LIVE_ROOT/src/pages/AdminHomepageLayoutPage.jsx" 2>/dev/null; then
  echo "PRODUCTION_SOURCE_CONTAINS_TEST_LABEL=YES"
else
  echo "PRODUCTION_SOURCE_CONTAINS_TEST_LABEL=NO_OR_SOURCE_NOT_PRESENT"
fi
echo "READ_ONLY_AUDIT=PASS"
