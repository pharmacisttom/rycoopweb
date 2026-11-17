#!/usr/bin/env bash
# RYCOOPWEB TEST-only MySQL Data Dictionary export.
# Usage: DB_NAME=rycoopweb_test MYSQL_USE_SUDO=1 bash scripts/export-test-datasheet.sh
# Never exports table rows, user data, credentials, passwords or tokens.
set -Eeuo pipefail
DB_NAME="${DB_NAME:-rycoopweb_test}"
OUTPUT_DIR="${OUTPUT_DIR:-docs/database/generated}"
[[ "$DB_NAME" =~ ^(rycoop|rayongcoop)(web)?_(test|ci)(_[a-zA-Z0-9_]+)?$ ]] || {
  echo "REFUSED: only explicit TEST/CI database names allowed" >&2; exit 2;
}
[[ "$OUTPUT_DIR" != /* && "$OUTPUT_DIR" != *..* ]] || { echo "REFUSED: relative output required" >&2; exit 2; }
mkdir -p "$OUTPUT_DIR"
# Use local Unix-socket auth or MYSQL_* environment credentials; do not pass passwords on argv.
MYSQL=(mysql --batch --raw --skip-column-names)
if [[ "${MYSQL_USE_SUDO:-0}" == "1" ]]; then MYSQL=(sudo "${MYSQL[@]}"); fi
query() { "${MYSQL[@]}" information_schema -e "$1"; }
escaped_db="${DB_NAME//\'/\'\'}"
TABLES="$OUTPUT_DIR/TABLES.tsv"
COLUMN_FILE="$OUTPUT_DIR/COLUMNS.tsv"
KEYS="$OUTPUT_DIR/FOREIGN_KEYS.tsv"
INDEXES="$OUTPUT_DIR/INDEXES.tsv"
query "SELECT TABLE_NAME, ENGINE, TABLE_TYPE FROM TABLES WHERE TABLE_SCHEMA='${escaped_db}' ORDER BY TABLE_NAME;" > "$TABLES"
[[ -s "$TABLES" ]] || { echo "REFUSED: no tables found or database missing"; exit 3; }
query "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COALESCE(COLUMN_DEFAULT,'<NULL>'),COLUMN_KEY,EXTRA FROM COLUMNS WHERE TABLE_SCHEMA='${escaped_db}' ORDER BY TABLE_NAME,ORDINAL_POSITION;" > "$COLUMN_FILE"
query "SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME,CONSTRAINT_NAME FROM KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='${escaped_db}' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,ORDINAL_POSITION;" > "$KEYS"
query "SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME FROM STATISTICS WHERE TABLE_SCHEMA='${escaped_db}' ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX;" > "$INDEXES"
{
  echo "# RYCOOPWEB Database Datasheet — TEST schema"
  echo
  echo "Source: MySQL INFORMATION_SCHEMA for database \`${DB_NAME}\`. Structural metadata only; **not a Production schema claim**."
  echo
  echo "Generated UTC: $(date -u '+%Y-%m-%dT%H:%M:%SZ')"
  echo
  printf '| Table | Engine | Type |\n|---|---|---|\n'
  while IFS=$'\t' read -r a b c; do printf '| \`%s\` | %s | %s |\n' "$a" "$b" "$c"; done < "$TABLES"
  echo
  echo "## Columns"
  printf '| Table | Column | Type | Nullable | Default | Key | Extra |\n|---|---|---|---|---|---|---|\n'
  while IFS=$'\t' read -r a b c d e f g; do printf '| \`%s\` | \`%s\` | \`%s\` | %s | %s | %s | %s |\n' "$a" "$b" "$c" "$d" "$e" "$f" "$g"; done < "$COLUMN_FILE"
  echo
  echo "## Foreign keys"
  printf '| Table.column | References | Constraint |\n|---|---|---|\n'
  while IFS=$'\t' read -r a b c d e; do printf '| \`%s.%s\` | \`%s.%s\` | %s |\n' "$a" "$b" "$c" "$d" "$e"; done < "$KEYS"
  echo
  echo "Indexes: see \`INDEXES.tsv\`; the remaining TSV files are machine-readable companions."
} > "$OUTPUT_DIR/DATA_DICTIONARY.md"
printf 'PASS: schema %s, tables %s, columns %s\n' "$DB_NAME" "$(wc -l < "$TABLES")" "$(wc -l < "$COLUMN_FILE")"
printf 'Output: %s\n' "$OUTPUT_DIR/DATA_DICTIONARY.md"
