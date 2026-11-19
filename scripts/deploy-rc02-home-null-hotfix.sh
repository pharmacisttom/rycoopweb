#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

WORK="$(cd "$(dirname "$0")/.." && pwd)"
BRANCH="hotfix/rc02-home-null-rate-source-20261119"
OLD="/var/www/rycoopweb/production-releases/real-content-rc02-20261119T110048Z"
MAIN="/etc/nginx/sites-available/rycoopweb-main-ssl"
LOOP="/etc/nginx/conf.d/rycoop-prod-loopback.conf"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="/var/www/rycoopweb/production-releases/rc02-home-null-hotfix-$STAMP"
BACKUP="/var/backups/rycoop-production/rc02-home-null-hotfix-$STAMP"
HOST="www.rayongcoop.org"
SWITCHED=0
SUCCESS=0

abort() { echo "STOP: $*" >&2; exit 1; }
cleanup() {
  rc=$?
  trap - EXIT
  if [[ "$SWITCHED" == 1 && "$SUCCESS" != 1 ]]; then
    echo "===== ROLLBACK ====="
    cp -a "$BACKUP/main.conf" "$MAIN"
    cp -a "$BACKUP/loop.conf" "$LOOP"
    if nginx -t && systemctl reload nginx; then echo "ROLLBACK=PASS"; else echo "ROLLBACK=FAILED"; fi
  fi
  [[ "$SUCCESS" == 1 ]] || echo "HOTFIX=FAILED"
  exit "$rc"
}
trap cleanup EXIT

[[ $(id -u) -eq 0 ]] || abort "Run with sudo"
[[ -f "$OLD/public/app/index.html" ]] || abort "Original RC02 release missing"
[[ -f "$MAIN" && -f "$LOOP" ]] || abort "Nginx config missing"
[[ $(stat -c '%U' "$WORK") == coopadmin ]] || abort "Unexpected workspace owner"
[[ $(runuser -u coopadmin -- git -C "$WORK" branch --show-current) == "$BRANCH" ]] || abort "Wrong branch"
runuser -u coopadmin -- git -C "$WORK" merge-base --is-ancestor e1bf66bdda02fc3fe159bfd70b4ae584ea4216b3 HEAD || abort "Missing RC02 baseline"
[[ -z $(runuser -u coopadmin -- git -C "$WORK" status --porcelain --untracked-files=no) ]] || abort "Uncommitted changes"
python3 - "$MAIN" "$LOOP" "$OLD" <<'PY'
from pathlib import Path
import sys
main,loop,old=sys.argv[1:]
for p in [main,loop]:
    c=Path(p).read_text()
    assert c.count(f"root {old}/public;")==1, "Unexpected active production root in "+p
assert "RYCOOP_PUBLIC_GALLERY_RC02" in Path(main).read_text(), "Gallery route missing"
PY
echo "===== BUILD ====="
cd "$WORK"
runuser -u coopadmin -- npm ci
runuser -u coopadmin -- node tests/PublicContentReferenceTest.mjs
runuser -u coopadmin -- npm run build
test -f "$WORK/public/app/index.html"
grep -q "INTEREST_RATES?.depositSource?.url" "$WORK/src/pages/HomePage.jsx" || abort "Null guard missing"
echo "BUILD=PASS"

echo "===== STAGE FRONTEND-ONLY HOTFIX ====="
mkdir -p "$BACKUP"
cp -a "$MAIN" "$BACKUP/main.conf"
cp -a "$LOOP" "$BACKUP/loop.conf"
[[ ! -e "$NEW" ]] || abort "Release already exists"
cp -a "$OLD" "$NEW"
rm -rf "$NEW/public/app"
cp -a "$WORK/public/app" "$NEW/public/app"
if find "$NEW/public/app" -type l -print -quit | grep -q .; then abort "Unexpected asset symlink"; fi
chown -R root:www-data "$NEW/public/app"
find "$NEW/public/app" -type d -exec chmod 0755 {} +
find "$NEW/public/app" -type f -exec chmod 0644 {} +
runuser -u www-data -- test -r "$NEW/public/app/index.html" || abort "Nginx cannot read SPA"
cmp "$OLD/.env" "$NEW/.env" || abort "Environment changed"
echo "STAGE=PASS"

echo "===== ACTIVATE ====="
SWITCHED=1
python3 - "$MAIN" "$LOOP" "$OLD" "$NEW" <<'PY'
from pathlib import Path
import sys
main,loop,old,new=sys.argv[1:]
for p in [main,loop]:
    path=Path(p)
    c=path.read_text()
    before=f"root {old}/public;"
    assert c.count(before)==1
    path.write_text(c.replace(before,f"root {new}/public;"))
PY
nginx -t
systemctl reload nginx
sleep 2
echo "===== SMOKE TEST ====="
for path in /app/ /app/admin/cms /api/public/home /api/public/content /api/public/news /api/public/documents /storage/uploads/news/gallery/ed86f61151ef5a1c23fac7691e184819-src.webp /storage/uploads/news/gallery/ed86f61151ef5a1c23fac7691e184819-thumbnail.webp; do
  code="$(curl --noproxy '*' -4 -sS --max-time 20 --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$path")"
  echo "$path => HTTP $code"
  [[ "$code" == 200 ]] || abort "Smoke failed: $path"
done
SUCCESS=1
echo "RC02_HOMEPAGE_HOTFIX=PASS"
echo "RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo "SOURCE_COMMIT=$(runuser -u coopadmin -- git -C "$WORK" rev-parse HEAD)"
echo "DATABASE=UNCHANGED"
echo "MFA=UNCHANGED"
