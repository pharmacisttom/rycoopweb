#!/usr/bin/env bash
# RC03 non-invasive TEST acceptance preflight. No imports, seeders, HTTP mutations or deploys.
set -Eeuo pipefail

EXPECTED_SHA="4d0a9f946999f63db57d6c6fd199f85934b65b75"
STAGE="${RC03_STAGE:-/var/tmp/rycoop-rc03-review-20261116}"
LIVE="${RC03_LIVE:-/var/www/rycoopweb/test-v2}"
DB="rycoopweb_test"

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
pass() { printf 'PASS: %s\n' "$*"; }
command -v git >/dev/null || fail "git is required"
command -v php >/dev/null || fail "php is required"
command -v mysql >/dev/null || fail "mysql CLI is required"
[[ -d "$STAGE/.git" ]] || fail "missing isolated staging checkout"
[[ "$STAGE" != "$LIVE" ]] || fail "refusing live directory as staging"
[[ "$STAGE" != /var/www/* ]] || fail "stage must not be under live /var/www"
[[ "$(git -C "$STAGE" rev-parse HEAD)" == "$EXPECTED_SHA" ]] || fail "staging must be exact reviewed PR #28 SHA"
pass "exact PR #28 commit"
for file in app/Services/BoardTermService.php app/Services/BoardTermWorkflow.php app/Controllers/Admin/BoardTermWorkflowController.php app/Controllers/Admin/DocumentController.php app/Controllers/Public/MediaController.php app/Services/PublicContentService.php config/routes.php; do
  [[ -f "$STAGE/$file" ]] || fail "missing $file"
  php -l "$STAGE/$file" >/dev/null || fail "syntax $file"
done
pass "all required PHP components lint"
[[ "$(grep -Fc '/api/public/media/{folder}/{filename}' "$STAGE/config/routes.php")" -eq 1 ]] || fail "public media route missing/duplicated"
for suffix in preview download; do
  [[ "$(grep -Fc "'/documents/{id}/$suffix','Admin" "$STAGE/config/routes.php")" -eq 1 ]] || fail "document $suffix route missing/duplicated"
done
pass "Media + Document route cardinality"
git -C "$STAGE" diff --check || fail "whitespace errors"
printf 'TEST DB structural preflight (explicit target only):\n'
mysql --batch --skip-column-names "$DB" -e "SELECT DATABASE(), @@transaction_isolation" |
  grep -q "^rycoopweb_test[[:space:]]" || fail "database target is not TEST"
mysql --batch --skip-column-names information_schema -e "
SELECT COUNT(*) FROM TABLES WHERE TABLE_SCHEMA='rycoopweb_test'
AND TABLE_NAME IN ('board_terms','board_term_members','board_term_groups','board_term_group_members')
AND ENGINE='InnoDB'" | grep -qx 4 || fail "four InnoDB tables required"
mysql --batch --skip-column-names information_schema -e "
SELECT COUNT(DISTINCT TABLE_NAME, COLUMN_NAME) FROM KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA='rycoopweb_test'
AND (TABLE_NAME,COLUMN_NAME) IN
 (('board_term_members','term_id'),('board_term_groups','term_id'),('board_term_group_members','group_id'),('board_term_group_members','member_id'))
AND REFERENCED_TABLE_NAME IS NOT NULL" | grep -qx 4 || fail "four foreign keys required"
mysql --batch --skip-column-names information_schema -e "
SELECT COUNT(*) FROM STATISTICS
WHERE TABLE_SCHEMA='rycoopweb_test' AND TABLE_NAME='board_terms'
AND INDEX_NAME='uq_board_terms_term_number' AND COLUMN_NAME='term_number'
AND NON_UNIQUE=0" | grep -qx 1 || fail "unique term_number required"
pass "TEST database schema, FK and unique index"
mysql --batch "$DB" -e "
SELECT term_number,status,is_current,COUNT(*) AS rows_in_group
FROM board_terms GROUP BY term_number,status,is_current ORDER BY term_number;
SELECT 'terms' AS board_table,COUNT(*) AS row_count FROM board_terms
UNION ALL SELECT 'members',COUNT(*) FROM board_term_members
UNION ALL SELECT 'groups',COUNT(*) FROM board_term_groups
UNION ALL SELECT 'group_members',COUNT(*) FROM board_term_group_members;"
printf 'PASS: read-only preflight complete; staging unchanged; no deploy/import/approval/publication\n'
