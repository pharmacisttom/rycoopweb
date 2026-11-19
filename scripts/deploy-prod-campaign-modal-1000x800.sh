#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
WORK="$(cd "$(dirname "$0")/.." && pwd)"
OLD="/var/www/rycoopweb/production-releases/rc02-document-download-hotfix-20261119T113804Z"
MAIN="/etc/nginx/sites-available/rycoopweb-main-ssl"
LOOP="/etc/nginx/conf.d/rycoop-prod-loopback.conf"
BRANCH="hotfix/prod-campaign-modal-1000x800-20261119"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="/var/www/rycoopweb/production-releases/prod-campaign-modal-1000x800-$STAMP"
BACKUP="/var/backups/rycoop-production/prod-campaign-modal-1000x800-$STAMP"
HOST="www.rayongcoop.org"
SWITCHED=0; SUCCESS=0
abort(){ echo "STOP: $*" >&2; exit 1; }
cleanup(){
  code=$?; trap - EXIT
  if [[ "$SWITCHED" == 1 && "$SUCCESS" != 1 ]]; then
    echo "===== ROLLBACK ====="
    cp -a "$BACKUP/main.conf" "$MAIN"
    cp -a "$BACKUP/loop.conf" "$LOOP"
    if nginx -t && systemctl reload nginx; then echo "NGINX_ROLLBACK=PASS"; else echo "NGINX_ROLLBACK=FAILED"; fi
  fi
  [[ "$SUCCESS" == 1 ]] || echo "CAMPAIGN_MODAL_1000X800=FAILED"
  exit "$code"
}
trap cleanup EXIT
[[ $(id -u) -eq 0 ]] || abort "Run with sudo"
[[ -f "$OLD/public/app/index.html" ]] || abort "Expected baseline release missing"
[[ -f "$MAIN" && -f "$LOOP" ]] || abort "Nginx config missing"
[[ $(stat -c '%U' "$WORK") == coopadmin ]] || abort "Unexpected workspace owner"
[[ $(runuser -u coopadmin -- git -C "$WORK" branch --show-current) == "$BRANCH" ]] || abort "Wrong branch"
runuser -u coopadmin -- git -C "$WORK" merge-base --is-ancestor 9ba1e1d087ad6617b4aec7d25faf6df0a5cb81fa HEAD || abort "Wrong release ancestry"
[[ -z $(runuser -u coopadmin -- git -C "$WORK" status --porcelain --untracked-files=no) ]] || abort "Uncommitted files"
python3 - "$MAIN" "$LOOP" "$OLD" <<'PY'
from pathlib import Path
import sys
main,loop,old=sys.argv[1:]
for file in (main,loop):
    data=Path(file).read_text()
    assert data.count(f"root {old}/public;")==1, "Unexpected live release: "+file
assert "RYCOOP_PUBLIC_GALLERY_RC02" in Path(main).read_text()
PY
grep -Fq 'width: min(100%, 1000px)' "$WORK/src/components/common/CampaignModal.css" || abort "Modal width missing"
grep -Fq 'height: min(800px, calc(100dvh - 32px))' "$WORK/src/components/common/CampaignModal.css" || abort "Modal height missing"
grep -Fq 'aria-modal="true"' "$WORK/src/components/common/CampaignModal.jsx" || abort "Accessible dialog missing"

echo "===== BUILD ====="
cd "$WORK"
runuser -u coopadmin -- npm ci
runuser -u coopadmin -- node tests/PublicContentReferenceTest.mjs
runuser -u coopadmin -- npm run build
test -f "$WORK/public/app/index.html"
echo "BUILD=PASS"

echo "===== STAGE ====="
mkdir -p "$BACKUP"; cp -a "$MAIN" "$BACKUP/main.conf"; cp -a "$LOOP" "$BACKUP/loop.conf"
[[ ! -e "$NEW" ]] || abort "Destination exists"
cp -a "$OLD" "$NEW"
rm -rf "$NEW/public/app"
cp -a "$WORK/public/app" "$NEW/public/app"
# Frontend-only update: preserve live PHP routes, MFA and all database settings.
chown -R root:www-data "$NEW/public/app"
find "$NEW/public/app" -type d -exec chmod 0755 {} +
find "$NEW/public/app" -type f -exec chmod 0644 {} +
runuser -u www-data -- test -r "$NEW/public/app/index.html" || abort "Index unreadable"
cmp "$OLD/.env" "$NEW/.env" || abort "ENV changed"
echo "STAGE=PASS"
echo "===== ACTIVATE ====="
SWITCHED=1
python3 - "$MAIN" "$LOOP" "$OLD" "$NEW" <<'PY'
from pathlib import Path
import sys
main,loop,old,new=sys.argv[1:]
for file in (main,loop):
    p=Path(file); data=p.read_text()
    assert data.count(f"root {old}/public;")==1
    p.write_text(data.replace(f"root {old}/public;",f"root {new}/public;"))
PY
nginx -t
systemctl reload nginx
sleep 2
for path in /app/ /api/public/documents /api/public/content /api/public/news /storage/uploads/news/gallery/ed86f61151ef5a1c23fac7691e184819-src.webp; do
  status="$(curl --noproxy '*' -4 -sS --max-time 20 --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$path")"
  echo "$path HTTP=$status"
  [[ "$status" == 200 ]] || abort "Smoke failed: $path"
done
# A guaranteed non-existent id must not throw a PHP exception or expose a file.
status="$(curl --noproxy '*' -4 -sS --max-time 20 --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST/api/public/documents/2147483647/download")"
echo "MISSING_DOCUMENT_HTTP=$status"
[[ "$status" == 404 ]] || abort "Missing document should return 404, got $status"
SUCCESS=1
echo "CAMPAIGN_MODAL_1000X800=PASS"
echo "RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo "SOURCE_COMMIT=$(runuser -u coopadmin -- git -C "$WORK" rev-parse HEAD)"
echo "DB=UNCHANGED"
echo "MFA=UNCHANGED"
