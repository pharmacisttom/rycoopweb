#!/usr/bin/env bash
# Read-only TEST CMS data contract inventory. Never modifies MySQL or Nginx.
set -Eeuo pipefail

[[ $(id -u) -ne 0 ]] || { echo 'STOP: run as coopadmin, not root' >&2; exit 1; }
STAGE='/var/www/rycoopweb/test-releases/frontend-baseline-rc01-20261118T015616Z'
CONF='/etc/nginx/sites-enabled/rycoopweb-test-v2'
[[ -f "$CONF" ]] || { echo 'STOP: TEST config not found' >&2; exit 1; }
sudo grep -Fq "$STAGE/public" "$CONF" || { echo 'STOP: TEST active stage changed; review target first' >&2; exit 1; }
sudo grep -qx 'APP_ENV=test' "$STAGE/.env" || { echo 'STOP: APP_ENV not test' >&2; exit 1; }
sudo grep -qx 'DB_DATABASE=rycoopweb_test' "$STAGE/.env" || { echo 'STOP: DB not rycoopweb_test' >&2; exit 1; }
sudo test -f "$STAGE/public/app/index.html" || { echo 'STOP: no TEST frontend build' >&2; exit 1; }

echo '===== CMS SCHEMA (TEST ONLY) ====='
sudo mysql --batch --table rycoopweb_test <<'SQL'
SELECT TABLE_NAME AS cms_table
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'rycoopweb_test'
 AND TABLE_NAME IN ('site_settings','public_content_revisions','hero_slides','media','news','boards','staff')
ORDER BY TABLE_NAME;
SELECT `key`, JSON_VALID(`value`) AS valid_json
FROM site_settings
WHERE `key` IN ('public_content_catalog','site_layout_home_v1','site_layout_home_draft_v1');
SELECT section, state, COUNT(*) AS revision_count
FROM public_content_revisions
WHERE section IN ('boards','groups','staff','profile','loan_rates','products')
GROUP BY section,state
ORDER BY section,state;
SELECT folder, COUNT(*) AS image_count FROM media
WHERE deleted_at IS NULL AND folder IN ('board','general')
GROUP BY folder ORDER BY folder;
SQL
echo 'PASS: read-only TEST CMS schema inventory complete'
echo 'No Production, TEST config, or database records were modified'
