# RYCOOPWEB 3.0 — Legacy Member Migration Rollback Plan

**Environment:** TEST (`rycoopweb_test`)

---

## 1. Surgical Batch Rollback (Fix 1 & Fix 2)

If defects, data mismatches, or unexpected behavior are observed after migration, rollback is executed surgically at the batch level via the provenance tracking table `legacy_member_mappings`, without touching other batches, pre-existing records, staff, RBAC, settings, or audit logs:

### Option A: CLI Surgical Batch Rollback
Run the rollback command with the recorded `batch_tag`:
```bash
php scripts/legacy-member-migrate.php --rollback=MIG-TEST-20261031-1500
```
This triggers `LegacyMemberMigrationService::rollbackBatch`:
1. Queries `legacy_member_mappings` for records matching `batch_tag`.
2. For records where `action = 'created'`:
   - Deletes only the exact created target records (`member_dividends`, `members`, `users`).
3. For records where `action = 'updated'`:
   - Restores the original state from `before_json`.
4. Removes the `member_import_runs` tracking rows for this batch.
5. Deletes the mapping entries for this batch tag.
6. Logs the surgical rollback action to `audit_logs` under `member_import / legacy_rollback`.
7. **Zero Cross-Batch Mutation**: Never runs `DELETE FROM member_dividends WHERE year IN (...)` or any blanket truncation.

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
- Unrelated batches or subsequent manual imports remain untouched.
