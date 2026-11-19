#!/usr/bin/env bash
# Read-only HTTP + MySQL TEST Activity Gallery disclosure gate.
# Must be executed on metrabyte; performs no DB/API mutations.
set -Eeuo pipefail
umask 077
[[ "$(hostname -s)" == "metrabyte" ]] || { echo "STOP: unexpected host"; exit 2; }
BASE="https://test.rayongcoop.org"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
read -r -p "TEST Basic Auth username: " U
read -r -s -p "TEST Basic Auth password: " P
printf '\n'
[[ -n "$U" && -n "$P" ]] || { echo "STOP: missing Basic Auth credentials"; exit 3; }
# Credentials confined to temporary 0600 netrc file instead of shell args.
python3 - "$WORK/netrc" "$U" "$P" <<'PY'
import pathlib,sys
p=pathlib.Path(sys.argv[1])
p.write_text('machine test.rayongcoop.org login '+sys.argv[2].replace('\\','\\\\').replace('"','\\"')+
             ' password "'+sys.argv[3].replace('\\','\\\\').replace('"','\\"')+'"\n')
p.chmod(0o600)
PY
unset P U
request() {
  local name="$1" url="$2" want="$3" response_type="${4:-}"
  local code
  code="$(curl --silent --show-error --max-time 20 --netrc-file "$WORK/netrc" \
     -H 'Accept: application/json' -H 'X-Requested-With: XMLHttpRequest' \
     -D "$WORK/$name.headers" -o "$WORK/$name.body" -w '%{http_code}' "$BASE$url")"
  echo "$name HTTP=$code expected=$want"
  [[ "$code" == "$want" ]] || { echo "FAIL: $name HTTP status"; return 1; }
  if [[ -n "$response_type" ]]; then
    grep -Eiq "^Content-Type:[[:space:]]*$response_type" "$WORK/$name.headers" || {
      echo "FAIL: $name content type"; return 1;
    }
  fi
}
echo "===== EXISTING PUBLISHED ALBUM ====="
request "public-list" "/api/public/activities" 200 'application/json'
python3 - "$WORK/public-list.body" <<'PY'
import json,sys
data=json.load(open(sys.argv[1]))
assert isinstance(data,dict) and isinstance(data.get('data'),list)
print("PUBLIC_LIST_JSON=PASS")
print("PUBLIC_ALBUM_COUNT=",len(data["data"]))
PY
echo "===== ANONYMOUS ADMIN API ====="
request "admin-anon" "/api/admin/activities" 401 'application/json'
python3 - "$WORK/admin-anon.body" <<'PY'
import json,sys
obj=json.load(open(sys.argv[1]))
assert obj.get("code")=="UNAUTHORIZED", "Incorrect anonymous admin response"
print("ANON_ADMIN_GUARD=PASS")
PY
echo "===== DRAFT ALBUM HIDING ====="
draft_album="$(sudo mysql -Nse "
 SELECT id FROM rycoopweb_test.activity_albums
 WHERE status='draft' AND deleted_at IS NULL
 ORDER BY id DESC LIMIT 1")"
if [[ "$draft_album" =~ ^[1-9][0-9]*$ ]]; then
  request draft-album "/api/public/activities/$draft_album" 404
  draft_photo="$(sudo mysql -Nse "
  SELECT id FROM rycoopweb_test.activity_photos
  WHERE album_id=$draft_album ORDER BY id LIMIT 1")"
  if [[ "$draft_photo" =~ ^[1-9][0-9]*$ ]]; then
    request draft-photo "/api/public/activities/$draft_album/photos/$draft_photo" 404
    echo "DRAFT_IMAGE_GUARD=PASS"
  else
    echo "DRAFT_IMAGE_GUARD=NOT_TESTED_NO_DRAFT_PHOTO"
  fi
  echo "DRAFT_ALBUM_GUARD=PASS"
else
  echo "DRAFT_ALBUM_GUARD=NOT_TESTED_NO_DRAFT"
fi
echo "===== ARCHIVED ALBUM HIDING ====="
archived_album="$(sudo mysql -Nse "
 SELECT id FROM rycoopweb_test.activity_albums
 WHERE deleted_at IS NOT NULL ORDER BY id DESC LIMIT 1")"
if [[ "$archived_album" =~ ^[1-9][0-9]*$ ]]; then
  request archived-album "/api/public/activities/$archived_album" 404
  archived_photo="$(sudo mysql -Nse "
  SELECT id FROM rycoopweb_test.activity_photos
  WHERE album_id=$archived_album ORDER BY id LIMIT 1")"
  if [[ "$archived_photo" =~ ^[1-9][0-9]*$ ]]; then
    request archived-photo "/api/public/activities/$archived_album/photos/$archived_photo" 404
    echo "ARCHIVED_IMAGE_GUARD=PASS"
  else
    echo "ARCHIVED_IMAGE_GUARD=NOT_TESTED_NO_ARCHIVED_PHOTO"
  fi
  echo "ARCHIVED_ALBUM_GUARD=PASS"
else
  echo "ARCHIVED_ALBUM_GUARD=NOT_TESTED_NO_ARCHIVED"
fi
echo "===== TEST UAT STATUS ====="
echo "READ_ONLY_GALLERY_GATES_COMPLETE"
echo "NOTE: NOT_TESTED gates require separate authorized Draft/Archive browser UAT."
echo "NOTE: Authenticated admin MFA/CSRF/CRUD not verified by this script."
