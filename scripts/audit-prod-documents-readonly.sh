#!/usr/bin/env bash
# Read-only reconciliation of the production document catalog and stored files.
# Does not download documents, modify MySQL, or edit the media storage.
set -Eeuo pipefail
umask 077

DB="${DB:-rayongcoop_db}"
BASE="${BASE:-/var/www/rycoopweb}"
SHARED="$BASE/shared-production/uploads"
OUT="${OUT:-/var/tmp/rycoop-documents-audit-$(date -u +%Y%m%dT%H%M%SZ)}"
mkdir -p "$OUT"
chmod 700 "$OUT"
command -v mysql >/dev/null || { echo "mysql missing" >&2; exit 1; }
test -d "$SHARED" || { echo "Shared production uploads not found" >&2; exit 1; }

echo "===== CATALOG COUNTS ====="
mysql --batch --raw --database="$DB" -e "
SELECT COUNT(*) AS total,
SUM(d.status='active' AND d.deleted_at IS NULL AND c.status='active' AND c.deleted_at IS NULL) AS published,
SUM(d.status='active' AND d.deleted_at IS NULL AND (c.status<>'active' OR c.deleted_at IS NOT NULL)) AS blocked_category,
SUM(d.status='active' AND d.deleted_at IS NULL AND (d.file_path IS NULL OR d.file_path='')) AS published_missing_path
FROM documents d LEFT JOIN document_categories c ON c.id=d.category_id;
" | tee "$OUT/counts.tsv"

mysql --batch --raw --skip-column-names --database="$DB" -e "
SELECT d.id, REPLACE(REPLACE(COALESCE(d.file_path,''),CHAR(9),''),CHAR(10),'')
FROM documents d JOIN document_categories c ON c.id=d.category_id
WHERE d.status='active' AND d.deleted_at IS NULL AND c.status='active' AND c.deleted_at IS NULL
ORDER BY d.id;
" > "$OUT/published-paths.tsv"

echo -e "document_id\tresult\tpath" > "$OUT/file-results.tsv"
while IFS=$'\t' read -r id stored; do
  result="MISSING"
  safe="${stored#/}"
  safe="${safe#storage/}"
  safe="${safe#uploads/}"
  if [[ -z "$stored" ]]; then result="NO_PATH"
  elif [[ "$stored" = /* || "$stored" == *'..'* || "$stored" == *'\\'* || "$stored" == *$'\n'* || "$stored" == *$'\r'* ]]; then
    result="UNSAFE_PATH"
  elif [[ -f "$SHARED/$safe" ]]; then
    if runuser -u www-data -- test -r "$SHARED/$safe"; then result="READABLE"; else result="UNREADABLE"; fi
  elif [[ -f "$BASE/shared-production/storage/uploads/$safe" ]]; then
    if runuser -u www-data -- test -r "$BASE/shared-production/storage/uploads/$safe"; then result="READABLE_ALT"; else result="UNREADABLE"; fi
  fi
  printf '%s\t%s\t%s\n' "$id" "$result" "$stored" >> "$OUT/file-results.tsv"
done < "$OUT/published-paths.tsv"

echo "===== FILE RESOLUTION SUMMARY ====="
awk -F '\t' 'NR>1 {a[$2]++} END {for (k in a) print k, a[k]}' "$OUT/file-results.tsv" | sort
echo "===== FILES REQUIRING RESTORATION ====="
awk -F '\t' 'NR>1 && $2 !~ /^READABLE/ {print "DOCUMENT_ID=" $1 " STATUS=" $2}' "$OUT/file-results.tsv"
echo "AUDIT_RESULTS=$OUT"
echo "NO_DATABASE_WRITES=PASS"
