#!/usr/bin/env bash
# Read-only source lint for RYCOOPWEB. Run from a clean git checkout or staging release.
set -uo pipefail
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
cd "$ROOT" || exit 2
DATE="$(date -u +%Y%m%dT%H%M%SZ)"
REPORT_DIR="${RYCOOP_LINT_REPORT_DIR:-/var/tmp/rycoop-lint-$DATE}"
mkdir -p "$REPORT_DIR"
FAILED=0
WARNINGS=0
PASS=0

record() {
  local name="$1" rc="$2"
  if [[ "$rc" -eq 0 ]]; then
    echo "PASS $name" | tee -a "$REPORT_DIR/summary.txt"
    PASS=$((PASS+1))
  else
    echo "FAIL $name (exit=$rc)" | tee -a "$REPORT_DIR/summary.txt"
    FAILED=$((FAILED+1))
  fi
}
echo "RYCOOPWEB_SOURCE_LINT_START=$(date -u +%FT%TZ)" > "$REPORT_DIR/summary.txt"
echo "SOURCE_DIR=$ROOT" >> "$REPORT_DIR/summary.txt"
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo 'ERROR: Requires git checkout to avoid untracked private uploads and vendor.'
  exit 2
fi
echo '===== PHP syntax: all tracked source files ====='
if ! command -v php >/dev/null 2>&1; then
  echo 'PHP_EXECUTABLE_MISSING' > "$REPORT_DIR/php-error.log"
  record php:available 1
else
  while IFS= read -r -d '' file; do
    case "$file" in vendor/*|node_modules/*|storage/*|public/app/assets/*) continue;; esac
    if ! php -l "$file" >> "$REPORT_DIR/php.log" 2>&1; then
      echo "$file" >> "$REPORT_DIR/php-failed.txt"
    fi
  done < <(git ls-files -z '*.php')
  if [[ -s "$REPORT_DIR/php-failed.txt" ]]; then
    record php:syntax 1
  else
    record php:syntax 0
  fi
fi

echo '===== JS syntax: .js/.mjs/.cjs (JSX handled by Vite) ====='
if ! command -v node >/dev/null 2>&1; then
  record node:available 1
else
  while IFS= read -r -d '' file; do
    case "$file" in node_modules/*|public/app/assets/*|public/assets/*) continue;; esac
    if ! node --check "$file" >> "$REPORT_DIR/javascript.log" 2>&1; then
      echo "$file" >> "$REPORT_DIR/javascript-failed.txt"
    fi
  done < <(git ls-files -z '*.js' '*.mjs' '*.cjs')
  if [[ -s "$REPORT_DIR/javascript-failed.txt" ]]; then
    record javascript:syntax 1
  else
    record javascript:syntax 0
  fi
fi

echo '===== JSON syntax: tracked configuration and content ====='
if command -v python3 >/dev/null 2>&1; then
  python3 - "$REPORT_DIR" <<'PY'
import json, subprocess, sys
from pathlib import Path
reports = Path(sys.argv[1])
proc = subprocess.run(['git','ls-files','-z','*.json'],capture_output=True,check=True)
bad=[]
for b in proc.stdout.split(b'\0'):
    if not b: continue
    path=Path(b.decode('utf-8','surrogateescape'))
    if str(path).startswith(('node_modules/','vendor/','public/app/assets/')): continue
    try: json.loads(path.read_text(encoding='utf-8-sig'))
    except Exception as e: bad.append(f'{path}: {e}')
(reports/'json-errors.txt').write_text('\n'.join(bad),encoding='utf-8')
sys.exit(bool(bad))
PY
  record json:syntax "$?"
else
  record json:syntax 1
fi

echo '===== PHP dependency manifest ====='
if command -v composer >/dev/null 2>&1 && [[ -f composer.json ]]; then
  composer validate --no-check-publish > "$REPORT_DIR/composer.log" 2>&1
  record composer:validate "$?"
else
  echo "SKIP composer validate (composer unavailable)" | tee -a "$REPORT_DIR/summary.txt"
fi

echo '===== RC15 contracts / validation ====='
for testcase in tests/HomeCompositionRc15ContractTest.php tests/HomeCompositionRc15ValidationTest.php; do
  if [[ -f "$testcase" && -f vendor/autoload.php ]]; then
    php "$testcase" > "$REPORT_DIR/$(basename "$testcase").log" 2>&1
    record "contract:$testcase" "$?"
  elif [[ -f "$testcase" ]]; then
    php "$testcase" > "$REPORT_DIR/$(basename "$testcase").log" 2>&1
    record "contract:$testcase" "$?"
  fi
done

echo '===== Vite compile: JSX, imports and CSS ====='
if command -v npm >/dev/null 2>&1 && [[ -d node_modules ]]; then
  npm run build > "$REPORT_DIR/vite.log" 2>&1
  record vite:build "$?"
else
  echo 'SKIP vite:build (run npm ci in an isolated checkout)' | tee -a "$REPORT_DIR/summary.txt"
fi

echo "TOTAL_PASS=$PASS" | tee -a "$REPORT_DIR/summary.txt"
echo "TOTAL_FAILURE_GROUPS=$FAILED" | tee -a "$REPORT_DIR/summary.txt"
echo "REPORT_DIR=$REPORT_DIR" | tee -a "$REPORT_DIR/summary.txt"
echo "RYCOOPWEB_SOURCE_LINT_COMPLETE" | tee -a "$REPORT_DIR/summary.txt"
test "$FAILED" -eq 0
