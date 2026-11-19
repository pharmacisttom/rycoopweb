# RYCOOPWEB Full Restore Drill — operator runbook (2026-11-18)

**Status: PREPARED, NOT EXECUTED.** Never run the restore script on `metrabyte` or any host serving Production/TEST. The GitHub repository does not contain the live backup or member data.

## Verified source evidence
- Production backup: `/var/backups/rycoop-production/20261118-085443/database.sql.gz` on `metrabyte`, protected root-only, gzip and SHA256SUMS checked.
- Production schema baseline: 71 tables. Existing data inventory at time of later check: members=2601; member_dividends=7319; users=2605; board_terms=1; news=4. **These are NOT snapshot-verified counts.**
- Gallery Migration SQL: `database/migrations/032_create_activity_photo_albums.sql`, SHA256 `c6748f4429259f948a711f4341805114387a64b1bffacf4eabae6e2da2a0f725`.
- Source contract and schema-only clone tested; prior CI had passed at commit `2857ed9` (not equivalent to successful live HTTP CRUD).
- No MySQL/Docker Lab is currently shown as provisioned; previous accidental attempt was on production host and did not create the restore database.

## Mandatory infrastructure and data handling
1. A separately provisioned, authorized Lab host (Ubuntu + MySQL 8.0) with audited SSH access, encrypted storage where required, no public MySQL port, firewall deny to Production backend/services and outbound notifications. Confirm name `hostname -s` is *not* `metrabyte`. Ideally use a segregated network/account/VPS, distinct credentials and a temporary environment.
2. Securely transfer the **real production snapshot** to Lab over SSH using authorized transfer; maintain original hash and ensure transfer/archive encryption, least-privilege storage (0600) and a documented disposal date. Never commit dumps/passwords, copy them into a public workspace, or upload them into chat.
3. On Production, root may read source archive and migration for transfer; this is **not** a command to restore on Production. Do not change Nginx, MySQL schemas, PHP-FPM, member login, security settings, or redirects as part of preparing a Lab.
4. Transfer review script `scripts/qa/rycoop-isolated-restore-drill.sh`, the migration SQL file and backup archive to the **Lab host**. Record compressed backup SHA256 from a trusted `sha256sum` run at the source; do not use a SHA copied from an untrusted message.
5. Check the dump's effective SQL before using it: schemas and definer/privilege statements; the script's text-pattern screening is not a substitute for review and cannot enforce full sandbox containment.
6. Example Lab command, **only after Lab has been reviewed and transfer completed**:

```bash
export RYCOOP_EXPECT_LAB_HOST="$(hostname -s)"
export RYCOOP_LAB_ACK="ISOLATED-LAB-ONLY"
export RYCOOP_BACKUP_FILE="/root/rycoop-lab/database.sql.gz"
export RYCOOP_MIGRATION_FILE="/root/rycoop-lab/032_create_activity_photo_albums.sql"
export RYCOOP_BACKUP_SHA256="<64-character-SHA256-from-verified-source>"
sudo -v
bash scripts/qa/rycoop-isolated-restore-drill.sh
```

**Do not blindly use** `export RYCOOP_EXPECT_LAB_HOST="$(hostname -s)"` on an unverified host. Confirm the host's infrastructure identity and network isolation first.

## Expected evidence
- Backup checksum verified, restored 71 tables with no SQL errors, Migration 032 reaches 73 tables, album/photo FK verified, output `RESTORE_DRILL_DB_PASS=TRUE`.
- Record table row-count and integrity checks **from the snapshot**, not the current production counts.
- Check actual API/role/MFA, private upload storage, public draft access, WEBP/GD, security audit, CSRF, PDF/docs, published board contents and rollback drill separately.
- Do not grant Go-Live on a bare 71→73 count. Migration Runner metadata must be recorded according to the production process after all safety gates.
- Restore failure: preserve logs in restricted Lab location and do not drop the original backup. Do not attempt production rollback without a separately approved runbook.
- On completion: disable access, securely dispose of copied sensitive data according to retention rules, and keep redacted summary evidence only.

## Outstanding work
- Lab hardware/provider and verified isolation.
- Snapshot-level restore and row/FK validation.
- Secure storage/uploads/config backup and off-site encrypted copy.
- End-to-end CRUD/MFA/UAT on TEST and Production read-only smoke plan.
- Production security advisory assessment, migration registry 026–031 reconciliation and 032 runner integration.
- Explicit release/cutover plan and rollback owner.
