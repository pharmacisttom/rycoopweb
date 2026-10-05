# Recover membership administration after deployment

Browser HTTP 500 errors confirm a backend failure, but do not identify its SQL cause. Check `storage/logs/error.log` privately before concluding which schema component is missing. Do not publish credentials or member records from logs.

This update adds an idempotent migration for optional legacy member profile columns, independent dashboard sections, compatible member searches, annual financial columns, a responsive administration sidebar, and authenticated deployment diagnostics. Missing audit tables disable editing until migrations complete. Existing passwords and historical dividend records are preserved.

## VPS update

In the SSH terminal, run each command below and stop if a command fails. The confirmed checkout is `/var/www/rayongcoop`. A backup must succeed before updating. A dirty working tree must be reviewed before merging; never reset it blindly.

```bash
cd /var/www/rayongcoop
git status --short
php bin/console backup:run
git fetch https://github.com/pharmacisttom/rycoopweb.git Test
git merge --ff-only FETCH_HEAD
composer install --no-dev --optimize-autoloader --no-interaction
php bin/console db:migrate
php bin/check-member-system.php
npm ci
npm run build
```

The checker should report `ready: true`. It prints schema and aggregate counts, without member identifiers or passwords. If it reports missing schema or extensions, resolve those before continuing. CLI and PHP-FPM may use different extension configurations; also check the authenticated web health page.

If PHP OPcache retains old code, identify the actual PHP-FPM service before reloading it:

```bash
systemctl list-units --type=service 'php*-fpm.service'
```

Reload the service shown by that command using `sudo systemctl reload SERVICE_NAME`. Do not assume a PHP version. No password reset or demo seeding is required.

## Verify in the browser

Sign out and sign back in as the existing membership administrator. Open `/admin/members/health`, then verify the dashboard, member search, annual amount filter, member detail, and import preview. The restricted administrator must still be denied the main administration APIs.

If an API still fails, record its HTTP status and the displayed reference, then inspect the matching entry in `storage/logs/error.log` privately. Do not enable public debug output.

## Local validation

`npm run build`, `php tests/MemberDeploymentCompatibilityTest.php`, `php tests/MemberAdministrationTest.php`, `php tests/SecurityRegressionTest.php`, and the authenticated `tests/MemberAdministrationHttpTest.py` pass. Compatibility tests use an isolated disposable database and verify repeatable repairs without changing passwords or historical financial records. HTTP tests read records and preview uploads without confirming imports or editing live data.
