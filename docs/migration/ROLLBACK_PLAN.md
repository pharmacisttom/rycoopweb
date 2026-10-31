# RYCOOPWEB 3.0 — Legacy Member Migration Rollback Plan

**Environment:** TEST (`rycoopweb_test`)

---

## 1. Targeted Batch Rollback

If defects, data mismatches, or unexpected behavior are observed after migration, rollback can be executed cleanly at the batch level without touching existing staff, RBAC, settings, or audit logs:

### Option A: CLI Automated Batch Rollback
Run the rollback command with the recorded `batch_tag`:
```bash
php scripts/legacy-member-migrate.php --rollback=MIG-TEST-20261031-1500
```
This triggers `LegacyMemberMigrationService::rollbackBatch`:
1. Identifies import runs associated with the batch tag.
2. Removes the `member_import_runs` tracking rows.
3. Removes the imported `member_dividends` for the batch years.
4. Logs the rollback action to `audit_logs` under `member_import / legacy_rollback`.

### Option B: Database Snapshot Restore (Full Revert)
If a complete database revert to the pre-migration state is desired:
```bash
mysql -u root -p rycoopweb_test < /var/backups/rycoopweb_test_pre_member_migration_<timestamp>.sql
```

---

## 2. Safety Invariants During Rollback
- Existing Super Admin account (ID 1) is never deleted.
- Staff accounts and PR Officer accounts are never touched.
- RBAC permissions table is never truncated.
- System audit log history is preserved intact.
