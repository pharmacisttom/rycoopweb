# RYCOOPWEB — Production & Test Rollback Runbook

**Document Purpose:** Rapid Rollback Procedures for Application and Database Releases
**Environment Scope:** TEST (`test.rayongcoop.org`) / PROD (`www.rayongcoop.org`)

---

## 1. Instant Application Rollback (Zero Downtime Symlink Switch)

RYCOOPWEB uses immutable release directory structures linked via atomic symbolic links:
- Backend: `/var/www/test.rayongcoop.org/current` -> `/var/www/test.rayongcoop.org/releases/<release-timestamp>`
- Static Frontend: `/var/www/test.rayongcoop.org/static-current` -> `/var/www/test.rayongcoop.org/static-releases/<release-timestamp>`

### 1.1 Automated Fast Rollback Command
To rollback to the immediately preceding verified release:

```bash
# 1. Identify previous verified release
ls -lt /var/www/test.rayongcoop.org/releases | head -n 5

# 2. Point current symlink to previous release
PREV_RELEASE="20261102_120000"
ln -sfn /var/www/test.rayongcoop.org/releases/$PREV_RELEASE /var/www/test.rayongcoop.org/current

# 3. Point static release symlink to previous release
ln -sfn /var/www/test.rayongcoop.org/static-releases/$PREV_RELEASE /var/www/test.rayongcoop.org/static-current

# 4. Reload dedicated PHP-FPM service
sudo systemctl reload rycoop-test-fpm.service

# 5. Verify HTTP 200 response
curl -fsSI -u "tester:secret" https://test.rayongcoop.org/api/public/home | head -n 1
```

---

## 2. Database Rollback Procedures

### 2.1 Hero Slides Table Rollback
If hero slides data requires reverting to pre-migration baseline:

```sql
-- Restore previous hero_slides state
DELETE FROM hero_slides WHERE id > 3;
UPDATE hero_slides SET status = 'active', deleted_at = NULL WHERE id <= 3;
```

### 2.2 Member Accounts Safeguard
- **Rule:** Never roll back or rerun Migration 028.
- If member status must be reverted, utilize the activation snapshot table:
```sql
-- Verify snapshot table exists before rollback
SELECT COUNT(*) FROM member_activation_snapshot_20261102;
```
