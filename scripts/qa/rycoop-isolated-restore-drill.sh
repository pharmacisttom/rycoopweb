#!/usr/bin/env bash
# RYCOOPWEB isolated restore drill; NEVER run on Production.
set -Eeuo pipefail
umask 077

: "${RYCOOP_EXPECT_LAB_HOST:?Set explicitly to the independently verified Lab hostname}"
: "${RYCOOP_BACKUP_FILE:?Path to encrypted-in-transit, access-controlled database.sql.gz on Lab}"
: "${RYCOOP_MIGRATION_FILE:?Path to reviewed 032_create_activity_photo_albums.sql on Lab}"
: "${RYCOOP_BACKUP_SHA256:?SHA256 of the transferred compressed dump, copied from trusted manifest}"
: "${RYCOOP_LAB_ACK:?Set RYCOOP_LAB_ACK=ISOLATED-LAB-ONLY after verifying host isolation}"

[[ "$RYCOOP_LAB_ACK" == "ISOLATED-LAB-ONLY" ]] || { echo "STOP: Lab acknowledgement missing"; exit 10; }
[[ "$(hostname -s)" == "$RYCOOP_EXPECT_LAB_HOST" ]] || { echo "STOP: unexpected hostname"; exit 11; }
[[ "$(hostname -s)" != "metrabyte" ]] || { echo "STOP: Production hostname"; exit 12; }
[[ -f "$RYCOOP_BACKUP_FILE" && -f "$RYCOOP_MIGRATION_FILE" ]] || { echo "STOP: files missing"; exit 13; }
for tool in mysql gzip sha256sum grep; do command -v "$tool" >/dev/null || exit 14; done

# No source PROD/TEST database may exist on the target SQL instance.
DBS="$(sudo mysql -Nse 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA')"
if printf '%s\n' "$DBS" | grep -Eq '^(rayongcoop_db|rycoopweb_test|rycoop_restore_lab)$'; then
  echo "STOP: protected or existing restore database found"; exit 15
fi

printf '%s  %s\n' "$RYCOOP_BACKUP_SHA256" "$RYCOOP_BACKUP_FILE" | sha256sum --check --status || {
  echo "STOP: backup checksum mismatch"; exit 16;
}
gzip -t "$RYCOOP_BACKUP_FILE"
printf '%s  %s\n' 'c6748f4429259f948a711f4341805114387a64b1bffacf4eabae6e2da2a0f725' "$RYCOOP_MIGRATION_FILE" | sha256sum --check --status || {
  echo "STOP: migration checksum mismatch"; exit 17;
}

# Original dump should have no database-selection or schema destruction commands.
# This heuristic check is NOT a SQL parser and does not make arbitrary dumps safe.
if gzip -dc "$RYCOOP_BACKUP_FILE" | grep -Eim1 '^[[:space:]]*(USE[[:space:]]+|CREATE[[:space:]]+DATABASE|DROP[[:space:]]+DATABASE|CHANGE[[:space:]]+MASTER|CHANGE[[:space:]]+REPLICATION)' >/dev/null; then
  echo "STOP: database-selection / replication statements detected"; exit 18;
fi

DB='rycoop_restore_lab'
START="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "===== CREATE LAB DB ====="
sudo mysql -e "CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

echo "===== RESTORE FULL BACKUP INTO LAB ====="
# Explicitly target the LAB database and fail on any SQL error.
gzip -dc "$RYCOOP_BACKUP_FILE" | sudo mysql --database="$DB"

baseline="$(sudo mysql -Nse "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='BASE TABLE'")"
echo "BASELINE_TABLES=$baseline"
[[ "$baseline" == 71 ]] || { echo "FAIL: baseline not 71"; exit 20; }

echo "===== RESTORED SNAPSHOT COUNTS (not current Production counts) ====="
sudo mysql --table "$DB" <<'SQL'
SELECT 'members' AS table_name,COUNT(*) AS row_count FROM members
UNION ALL SELECT 'member_dividends',COUNT(*) FROM member_dividends
UNION ALL SELECT 'users',COUNT(*) FROM users
UNION ALL SELECT 'board_terms',COUNT(*) FROM board_terms
UNION ALL SELECT 'news',COUNT(*) FROM news;
SQL

echo "===== APPLY ADDITIVE MIGRATION 032 IN LAB ONLY ====="
sudo mysql --database="$DB" < "$RYCOOP_MIGRATION_FILE"
final="$(sudo mysql -Nse "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB' AND TABLE_TYPE='BASE TABLE'")"
[[ "$final" == 73 ]] || { echo "FAIL: final table count is $final, expected 73"; exit 21; }

echo "===== INTEGRITY GATES ====="
sudo mysql --table "$DB" <<'SQL'
SELECT TABLE_NAME,ENGINE,TABLE_COLLATION FROM information_schema.TABLES
WHERE TABLE_SCHEMA='rycoop_restore_lab'
AND TABLE_NAME IN ('activity_albums','activity_photos');
SELECT TABLE_NAME,CONSTRAINT_NAME,REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA='rycoop_restore_lab'
AND TABLE_NAME='activity_photos'
AND REFERENCED_TABLE_NAME='activity_albums';
SELECT (SELECT COUNT(*) FROM activity_albums) AS albums,
       (SELECT COUNT(*) FROM activity_photos) AS photos;
SQL
fk="$(sudo mysql -Nse "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='$DB' AND TABLE_NAME='activity_photos' AND CONSTRAINT_NAME='fk_activity_photos_album' AND REFERENCED_TABLE_NAME='activity_albums'")"
[[ "$fk" == 1 ]] || { echo "FAIL: gallery FK missing"; exit 22; }

echo "RESTORE_DRILL_DB_PASS=TRUE"
echo "BASELINE_TABLES=$baseline"
echo "FINAL_TABLES=$final"
echo "STARTED_UTC=$START"
echo "COMPLETED_UTC=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "WARNING: This does not cover application E2E, upload storage, or off-site encrypted backup validation."
echo "WARNING: Restore DB contains sensitive real Production data; restrict access and securely destroy Lab after review."
