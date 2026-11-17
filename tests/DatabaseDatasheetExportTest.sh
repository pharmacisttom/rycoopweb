#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/bin"
cat > "$WORK/bin/mysql" <<'MOCK'
#!/usr/bin/env bash
case "$*" in
  *"FROM TABLES WHERE"*) printf 'media\tInnoDB\tBASE TABLE\n' ;;
  *"FROM COLUMNS WHERE"*) printf 'media\tid\tbigint unsigned\tNO\t<NULL>\tPRI\tauto_increment\n' ;;
  *"FROM KEY_COLUMN_USAGE WHERE"*) printf 'media\tuploaded_by\tusers\tid\tfk_media_user\n' ;;
  *"FROM STATISTICS WHERE"*) printf 'media\tPRIMARY\t0\t1\tid\n' ;;
  *) echo "Unexpected query: $*" >&2; exit 1 ;;
esac
MOCK
chmod +x "$WORK/bin/mysql"
(
 cd "$WORK"
 PATH="$WORK/bin:$PATH" DB_NAME=rycoopweb_test OUTPUT_DIR=out \
   bash "$ROOT/scripts/export-test-datasheet.sh"
 test -s out/TABLES.tsv
 test -s out/COLUMNS.tsv
 test -s out/FOREIGN_KEYS.tsv
 test -s out/INDEXES.tsv
 test -s out/DATA_DICTIONARY.md
 test "$(wc -l < out/COLUMNS.tsv)" -eq 1
 grep -q 'media.id' out/DATA_DICTIONARY.md
)
echo 'PASS: TEST datasheet files and column path'
