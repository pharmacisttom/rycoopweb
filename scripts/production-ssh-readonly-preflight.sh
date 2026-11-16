#!/usr/bin/env bash
# RYCOOP Production SSH connectivity probe. Read-only; no privileged writes.
set -Eeuo pipefail
umask 077
echo "===== RYCOOP VPS READ-ONLY SSH PREFLIGHT ====="
printf 'SSH_USER=%s\n' "$(id -un)"
printf 'HOST=%s\n' "$(hostname)"
printf 'GIT=%s\n' "$(git --version)"
printf 'PHP=%s\n' "$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo unavailable)"
BASE=/var/www/rycoopweb/production-releases
if [[ -d "$BASE" && -r "$BASE" && -x "$BASE" ]]; then
  echo 'RELEASE_ROOT=ACCESSIBLE'
  find "$BASE" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort | tail -n 8
else
  echo 'RELEASE_ROOT=INACCESSIBLE'
  exit 1
fi
# Never show private .env, session data, database variables, credentials or member records.
echo 'SSH_READONLY_PREFLIGHT=PASS'
echo 'NO_DEPLOY=YES'
echo 'NO_DB_WRITES=YES'
echo 'NO_NGINX_CHANGES=YES'
