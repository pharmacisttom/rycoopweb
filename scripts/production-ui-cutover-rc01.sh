#!/usr/bin/env bash
# RYCOOP production UI-only cutover. Root runs manually, with a fully pinned
# immutable release artifact. NEVER modifies backend, DB, /api, /storage or PHP.
set -Eeuo pipefail
umask 077
SHA='91cd65a6f8d877cc849f56ce2ca73718c4fd8af8'
SSH_USER='coopadmin'
ROOT='/var/www/rycoopweb/production-releases'
ACTIVE="$ROOT/member-calculator-rc03-20261111-public"
NEW="$ROOT/admin-responsive-rc01-${SHA:0:12}-public"
STAGE="/home/$SSH_USER/.local/share/rycoop-deploy-staging/$SHA"
FILE="$STAGE/release.tgz"
CONF='/etc/nginx/sites-enabled/rycoopweb-main-ssl'
BASE='/var/www/rycoopweb'
BACKUP="$BASE/rollback-admin-ui-${SHA:0:12}"
if [[ "${EUID}" -ne 0 ]]; then echo 'ERROR=RUN_AS_ROOT_REQUIRED'; exit 1; fi
if [[ ! -s "$FILE" ]]; then echo 'ERROR=STAGED_RELEASE_NOT_FOUND'; exit 1; fi
if [[ ! -d "$ACTIVE/app" || ! -f "$ACTIVE/app/index.html" ]]; then echo 'ERROR=BASELINE_NOT_FOUND'; exit 1; fi
if [[ -e "$NEW" || -e "$BACKUP" ]]; then echo 'ERROR=PREVIOUS_ATTEMPT_EXISTS_STOP_FOR_MANUAL_REVIEW'; exit 1; fi
CONF_REAL="$(readlink -f "$CONF")"
if [[ -z "$CONF_REAL" || ! -f "$CONF_REAL" ]]; then echo 'ERROR=NGINX_SITE_NOT_FOUND'; exit 1; fi
if ! grep -Fqx "    root $ACTIVE;" "$CONF_REAL"; then echo 'ERROR=UNEXPECTED_ACTIVE_FRONTEND_ROOT'; exit 1; fi
# Verify artifact against manifest created by the trusted GitHub workflow.
CHECKSUMS="$STAGE/rollback/sha256sums"
if [[ ! -s "$CHECKSUMS" ]]; then echo 'ERROR=STAGING_MANIFEST_NOT_FOUND'; exit 1; fi
EXPECTED="$(awk -v target="$FILE" '$2==target {print $1}' "$CHECKSUMS")"
if [[ ! "$EXPECTED" =~ ^[a-f0-9]{64}$ ]]; then echo 'ERROR=STAGING_SHA256_MISSING'; exit 1; fi
ACTUAL="$(sha256sum "$FILE" | awk '{print $1}')"
if [[ "$EXPECTED" != "$ACTUAL" ]]; then echo 'ERROR=STAGED_ARCHIVE_SHA256_MISMATCH'; exit 1; fi
# Tar archive is produced by git archive + vendor + Vite builder. Confirm
# paths are relative, contain no ../ components and have no symlink/hardlink.
python3 - "$FILE" <<'PY'
import pathlib,sys,tarfile
with tarfile.open(sys.argv[1],'r:gz') as t:
  members=t.getmembers()
  if not members:raise SystemExit('EMPTY_ARCHIVE')
  for m in members:
    p=pathlib.PurePosixPath(m.name)
    if p.is_absolute() or '..' in p.parts or not (m.isfile() or m.isdir()):
      raise SystemExit('UNSAFE_ARCHIVE_MEMBER')
  names={m.name.lstrip('./') for m in members}
  if 'public/app/index.html' not in names: raise SystemExit('NO_APP_INDEX')
print('RELEASE_ARCHIVE_SAFE=PASS')
PY
mkdir -p "$BACKUP"
chmod 700 "$BACKUP"
cp -a "$CONF_REAL" "$BACKUP/nginx-site.conf"
tar -czf "$BACKUP/active-frontend.tgz" -C "$ACTIVE" .
test -s "$BACKUP/nginx-site.conf"
test -s "$BACKUP/active-frontend.tgz"
NEW_TMP="$(mktemp -d "$ROOT/.admin-responsive-rc01.XXXXXXXX")"
EXTRACT="$(mktemp -d "$BASE/.admin-ui-extract.XXXXXXXX")"
EDIT="$(mktemp "$BASE/.admin-ui-nginx.XXXXXXXX")"
CHANGED=0
cleanup() {
  rc=$?
  if [[ $rc -ne 0 && $CHANGED -eq 1 ]]; then
    cp -a "$BACKUP/nginx-site.conf" "$CONF_REAL"
    /usr/sbin/nginx -t && /usr/bin/systemctl reload nginx || true
    echo 'PRODUCTION_ROLLBACK=ATTEMPTED'
  fi
  if [[ -d "$EXTRACT" ]]; then rm -rf -- "$EXTRACT"; fi
  if [[ -f "$EDIT" ]]; then rm -f -- "$EDIT"; fi
  if [[ -d "$NEW_TMP" ]]; then rm -rf -- "$NEW_TMP"; fi
}
trap cleanup EXIT
tar -xzf "$FILE" -C "$EXTRACT"
test -s "$EXTRACT/public/app/index.html"
# Preserve all existing public files including login, logos, assets and
# maintenance pages. Replace ONLY /app/ with QA-passed Vite build.
cp -a "$ACTIVE/." "$NEW_TMP/"
rm -rf -- "$NEW_TMP/app"
cp -a "$EXTRACT/public/app" "$NEW_TMP/app"
test -s "$NEW_TMP/app/index.html"
grep -q '/app/assets/' "$NEW_TMP/app/index.html"
chown -R root:root "$NEW_TMP"
chmod -R u=rwX,go=rX "$NEW_TMP"
mv "$NEW_TMP" "$NEW"
python3 - "$CONF_REAL" "$EDIT" "$ACTIVE" "$NEW" <<'PY'
import pathlib,sys
src,dst,old,new=sys.argv[1:]
data=pathlib.Path(src).read_text()
needle='    root '+old+';'
assert data.count(needle)==1,'UNEXPECTED_NGINX_ROOT_OCCURRENCES'
pathlib.Path(dst).write_text(data.replace(needle,'    root '+new+';',1))
PY
chown --reference="$CONF_REAL" "$EDIT"
chmod --reference="$CONF_REAL" "$EDIT"
cp "$EDIT" "$CONF_REAL"
CHANGED=1
/usr/sbin/nginx -t
/usr/bin/systemctl reload nginx
sleep 2
# Do not assert that the existing broken news media URLs are fixed by this UI-only release.
for url in 'https://www.rayongcoop.org/app/admin/login' 'https://www.rayongcoop.org/app/news' 'https://www.rayongcoop.org/api/public/news'; do
  status="$(curl -L --max-time 15 -sS -o /dev/null -w '%{http_code}' "$url")"
  echo "SMOKE_HTTP=$status"
  if [[ "$status" != '200' ]]; then echo 'POST_CUTOVER_SMOKE_FAILED'; exit 1; fi
done
CHANGED=0
echo "UI_RELEASE_SHA=$SHA"
echo "UI_PRODUCTION_CUTOVER=PASS"
echo "UI_ROLLBACK_DIR=$BACKUP"
echo "API_BACKEND=UNCHANGED"
echo "DATABASE=UNCHANGED"
