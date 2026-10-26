#!/usr/bin/env bash
set -euo pipefail

# These values are supplied by the release approver after CI, TEST UAT and an isolated restore drill.
: "${DEPLOY_APPROVED_COMMIT:?Set the full commit approved for this production release}"
: "${DEPLOY_TESTED_COMMIT:?Set the full commit that passed CI and TEST UAT}"
: "${DEPLOY_RESTORE_VERIFIED_COMMIT:?Set the release commit whose backup restore was verified}"
: "${DEPLOY_HEALTH_URL:?Set an HTTPS public health endpoint}"
[[ "$DEPLOY_APPROVED_COMMIT" =~ ^[0-9a-f]{40}$ ]] || exit 1
[[ "$DEPLOY_APPROVED_COMMIT" == "$DEPLOY_TESTED_COMMIT" && "$DEPLOY_APPROVED_COMMIT" == "$DEPLOY_RESTORE_VERIFIED_COMMIT" ]] || exit 1
[[ "$DEPLOY_HEALTH_URL" == https://* ]] || exit 1
cd "${DEPLOY_ROOT:-/var/www/rayongcoop}"
[[ -z "$(git status --porcelain)" ]] || { echo 'Refusing deployment: working tree is not clean.' >&2; exit 1; }

previous_commit="$(git rev-parse HEAD)"
printf 'Previous commit: %s\n' "$previous_commit"
php bin/console backup:run
git fetch https://github.com/pharmacisttom/rycoopweb.git Test
[[ "$(git rev-parse FETCH_HEAD)" == "$DEPLOY_APPROVED_COMMIT" ]] || { echo 'Test branch differs from approved release.' >&2; exit 1; }
git merge --ff-only "$DEPLOY_APPROVED_COMMIT"
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
# Build must succeed before schema changes. MySQL DDL is not automatically reversible.
php bin/console db:migrate
php bin/check-member-system.php

sudo chown -R www-data:www-data storage
sudo find storage -type d -exec chmod 750 {} \;
sudo find storage -type f -exec chmod 640 {} \;
sudo nginx -t
sudo systemctl reload nginx

curl --fail --silent --show-error --max-time 20 "$DEPLOY_HEALTH_URL" >/dev/null

printf 'Deployment complete. Roll back to %s if required.\n' "$previous_commit"
