#!/usr/bin/env bash
set -euo pipefail

cd /var/www/rayongcoop

previous_commit="$(git rev-parse HEAD)"
printf 'Previous commit: %s\n' "$previous_commit"
php bin/console backup:run
git fetch https://github.com/pharmacisttom/rycoopweb.git Test
git merge --ff-only FETCH_HEAD
composer install --no-dev --optimize-autoloader --no-interaction
php bin/console db:migrate
php bin/check-member-system.php
npm ci
npm run build

sudo chown -R www-data:www-data storage
sudo find storage -type d -exec chmod 750 {} \;
sudo find storage -type f -exec chmod 640 {} \;
sudo nginx -t
sudo systemctl reload nginx

printf 'Deployment complete. Roll back to %s if required.\n' "$previous_commit"
