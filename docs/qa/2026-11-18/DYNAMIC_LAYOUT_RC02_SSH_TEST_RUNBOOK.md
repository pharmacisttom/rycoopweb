# RYCOOPWEB RC02 — SSH TEST-only Verification Runbook

Target host: 119.59.116.32, SSH user: coopadmin. Verify the host key independently before accepting SSH trust. Do not provide passwords or private keys in chat.

## Purpose
Diagnose a live TEST presentation issue, verify the RC02 branch before any cutover, and keep Production intact.

## Read-only server checks
Run inside the authenticated coopadmin shell:

```bash
set -euo pipefail
echo "HOST=$(hostname -f) USER=$(whoami)"
sudo nginx -t
sudo systemctl is-active nginx
sudo grep -nE 'server_name|root |fastcgi_param SCRIPT_FILENAME|auth_basic' /etc/nginx/sites-enabled/rycoopweb-test-v2
echo '===== TEST filesystem ====='
sudo ls -ld /var/www/rycoopweb/shared-test /var/www/rycoopweb/test-releases
echo '===== TEST database tables (metadata only) ====='
sudo mysql --skip-column-names -e "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='rycoopweb_test' AND TABLE_NAME IN ('site_settings','audit_logs','activity_albums','activity_photos') ORDER BY TABLE_NAME"
echo '===== Test access gate (expect HTTP 401) ====='
curl --noproxy '*' -sk --resolve test.rayongcoop.org:443:127.0.0.1 -o /dev/null -w '%{http_code}\n' https://test.rayongcoop.org/app/
```

Never print .env secrets, passwords or private keys.

## Stage source separately (no live deployment)
Do this **only after PR #55 required CI checks succeed**, with confirmed SSH trust:

```bash
set -euo pipefail
mkdir -p "$HOME/rycoop-rc02-checkout"
cd "$HOME/rycoop-rc02-checkout"
if [ ! -d .git ]; then
  git clone --branch feature/dynamic-layout-gallery-rc02 --single-branch https://github.com/pharmacisttom/rycoopweb.git .
else
  git fetch origin feature/dynamic-layout-gallery-rc02
  git checkout feature/dynamic-layout-gallery-rc02
  git pull --ff-only origin feature/dynamic-layout-gallery-rc02
fi
git rev-parse HEAD
git status --short
php -l app/Services/DynamicLayoutService.php
php -l app/Controllers/Admin/DynamicLayoutController.php
php -l config/routes.php
npm ci --no-fund --no-audit
npm audit --omit=dev --audit-level=moderate
node tests/AdminLayoutPolishRC02Test.mjs
npm run build
test -s public/app/index.html
```

Do not invoke the old `scripts/deploy-test-layout-board-rc01.sh` blindly: it requires a hard-coded currently active TEST release that may not match Nginx.

## Release gate
1. Confirm exact SHA and all required PR #55 jobs.
2. Verify TEST Nginx release paths and Production separation.
3. Back up TEST configuration and test-specific database.
4. Prepare an immutable TEST release and a TEST-only rollback.
5. Maintain TEST Basic Auth and database `rycoopweb_test`.
6. Perform live credentialed UI/CRUD UAT.
7. Never change Production as part of RC02 release.
8. Never label a TEST-only npm advisory exception as a remediated vulnerability.

## Visual UAT matrix
Check desktop 1920/1366/1024, tablet 768 and mobile 390/320 widths. Validate:
- Cards and table borders do not overlap.
- No page-level horizontal scrolling; wide tables scroll inside their own wrappers.
- Text, toolbars and action buttons wrap without covering each other.
- Modals, menus, dialogs and focus visibility remain usable.
- Admin Layout section tree CRUD and Activity Gallery album workflow persist after reload.
- Existing Homepage, Member, Dividend and RBAC paths are unchanged.

If the TEST page still looks unstyled after deployment, inspect DevTools Network for 404/MIME errors in `/app/assets/*.css`, verify the active `public/app/index.html` hashes and ensure the deployment directory contains the matching assets.
