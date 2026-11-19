#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

WORK="$(cd "$(dirname "$0")/.." && pwd)"
BRANCH="fix/prod-real-content-news-media-rc02-20261119"
BASE="7d3f7e19ae2e59d0ffd65435770e45a4e79f9376"
OLD="/var/www/rycoopweb/production-releases/admin-navigation-rc01-20261119T081637Z"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
NEW="/var/www/rycoopweb/production-releases/real-content-rc02-$STAMP"
BACKUP="/var/backups/rycoop-production/real-content-rc02-$STAMP"
MAIN="/etc/nginx/sites-available/rycoopweb-main-ssl"
LOOP="/etc/nginx/conf.d/rycoop-prod-loopback.conf"
HOST="www.rayongcoop.org"
SWITCHED=0
SUCCESS=0

abort(){ echo "STOP: $*" >&2; exit 1; }
cleanup(){
  code=$?
  trap - EXIT
  if [ "$SWITCHED" = 1 ] && [ "$SUCCESS" != 1 ]; then
    cp -a "$BACKUP/main.conf" "$MAIN"
    cp -a "$BACKUP/loop.conf" "$LOOP"
    if nginx -t; then systemctl reload nginx; echo "ROLLBACK=PASS"; fi
  fi
  [ "$SUCCESS" = 1 ] || echo "DEPLOY=FAILED"
  exit "$code"
}
trap cleanup EXIT

[ "$(id -u)" -eq 0 ] || abort "Use sudo"
[ -f "$OLD/public/app/index.html" ] || abort "Unexpected old release"
[ -f "$MAIN" ] && [ -f "$LOOP" ] || abort "Nginx config missing"
[ "$(runuser -u coopadmin -- git -C "$WORK" branch --show-current)" = "$BRANCH" ] || abort "Wrong branch"
runuser -u coopadmin -- git -C "$WORK" merge-base --is-ancestor "$BASE" HEAD || abort "Incorrect baseline"
[ -z "$(runuser -u coopadmin -- git -C "$WORK" status --porcelain --untracked-files=no)" ] || abort "Uncommitted edits"

while IFS= read -r p; do
  [ -z "$p" ] && continue
  case "$p" in
    src/*|tests/PublicContentReferenceTest.mjs|config/routes.php|\
    app/Services/PublicContentService.php|app/Services/NewsGalleryService.php|\
    app/Controllers/Admin/PublicContentController.php|\
    app/Controllers/Api/SpaApiController.php|\
    app/Controllers/Api/NewsGalleryImageController.php|\
    scripts/deploy-prod-real-content-rc02.sh) ;;
    *) abort "Unexpected diff file: $p";;
  esac
done < <(runuser -u coopadmin -- git -C "$WORK" diff --name-only "$BASE" HEAD)

python3 - "$OLD" <<'PY'
from pathlib import Path
import sys
s=(Path(sys.argv[1])/"app/Controllers/Admin/GoogleAuthController.php").read_text()
assert s.count("Auth::login($authUser")==3
assert s.count("Auth::login($identity")==0
print("PRODUCTION_MFA_HOTFIX=PASS")
PY

cd "$WORK"
echo "===== BUILD, TEST AND PHP LINT ====="
# Keep generated dependencies owned by the unprivileged workspace user.
[ "$(stat -c '%U' "$WORK")" = "coopadmin" ] || abort "Unexpected workspace owner"
runuser -u coopadmin -- npm ci
runuser -u coopadmin -- node tests/PublicContentReferenceTest.mjs
runuser -u coopadmin -- npm run build
test -f public/app/index.html
for p in app/Services/PublicContentService.php \
         app/Services/NewsGalleryService.php \
         app/Controllers/Admin/PublicContentController.php \
         app/Controllers/Api/SpaApiController.php \
         app/Controllers/Api/NewsGalleryImageController.php \
         config/routes.php; do php -l "$p"; done
echo "LOCAL_TESTS=PASS"

echo "===== STAGE ====="
mkdir -p "$BACKUP"
cp -a "$MAIN" "$BACKUP/main.conf"
cp -a "$LOOP" "$BACKUP/loop.conf"
[ ! -e "$NEW" ] || abort "Release path exists"
cp -a "$OLD" "$NEW"
rm -rf "$NEW/public/app"
cp -a "$WORK/public/app" "$NEW/public/app"
# npm/Vite artifacts may be 0600 due to umask 077. Public SPA assets must
# be readable by nginx; do not touch the private .env or application tree.
if find "$NEW/public/app" -type l -print -quit | grep -q .; then
  abort "Unexpected symlink inside SPA build"
fi
chown -R root:www-data "$NEW/public/app"
find "$NEW/public/app" -type d -exec chmod 0755 {} +
find "$NEW/public/app" -type f -exec chmod 0644 {} +
runuser -u www-data -- test -r "$NEW/public/app/index.html" || abort "nginx cannot read SPA index"
if find "$NEW/public/app" -type f ! -perm /004 -print -quit | grep -q .; then
  abort "SPA contains unreadable assets"
fi
echo "SPA_NGINX_PERMISSIONS=PASS"
for p in app/Services/PublicContentService.php \
         app/Services/NewsGalleryService.php \
         app/Controllers/Admin/PublicContentController.php \
         app/Controllers/Api/SpaApiController.php \
         app/Controllers/Api/NewsGalleryImageController.php \
         config/routes.php; do
  install -D -m 0644 "$WORK/$p" "$NEW/$p"
done
cmp "$OLD/app/Controllers/Admin/GoogleAuthController.php" \
    "$NEW/app/Controllers/Admin/GoogleAuthController.php"
if [ -f "$OLD/.env" ]; then cmp "$OLD/.env" "$NEW/.env"; fi

echo "===== ACTIVATE ====="
SWITCHED=1
python3 - "$OLD" "$NEW" "$MAIN" "$LOOP" <<'PY'
from pathlib import Path
import sys
old,new,*files=sys.argv[1:]
sources=[Path(p).read_text() for p in files]
updates=[]
for source in sources:
    old_line=f"root {old}/public;"
    if source.count(old_line)!=1: raise SystemExit("Unexpected Nginx release root")
    updates.append(source.replace(old_line,f"root {new}/public;"))
for p,updated in zip(files,updates): Path(p).write_text(updated)
PY
nginx -t
systemctl reload nginx

echo "===== LIVE SMOKE ====="
for path in /app/ /app/admin/cms /api/public/home /api/public/content \
            /api/public/news /api/public/documents; do
  code="$(curl --noproxy '*' -sS --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" \
    -o /dev/null -w '%{http_code}' "https://$HOST$path")"
  echo "$path => HTTP $code"
  [ "$code" = 200 ] || abort "Failed API: $path"
done

curl --noproxy '*' -fsS --max-time 20 \
  --resolve "$HOST:443:127.0.0.1" \
  "https://$HOST/api/public/documents" > "$BACKUP/documents.json"
python3 - "$BACKUP/documents.json" <<'PY'
import json,sys
result=json.load(open(sys.argv[1]))
assert result.get('success') is True and isinstance(result.get('data'), list)
print("REAL_DOCUMENTS_VISIBLE=",len(result['data']))
assert len(result['data'])>0
PY

SUCCESS=1
echo "REAL_CONTENT_RC02_DEPLOY=PASS"
echo "RELEASE=$NEW"
echo "BACKUP=$BACKUP"
echo "SOURCE_COMMIT=$(runuser -u coopadmin -- git -C "$WORK" rev-parse HEAD)"
echo "DATABASE=UNCHANGED"
echo "MFA=PRESERVED"
