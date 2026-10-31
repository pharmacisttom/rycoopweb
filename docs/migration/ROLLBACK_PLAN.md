# RYCOOPWEB 3.0 — TEST Legacy Migration Rollback Safety Plan

**NO-GO until rollback isolation is proven on MySQL 8 with existing data.** This runbook applies only to `rycoopweb_test`. Production is out of scope.

The importer tracks `legacy_member_mappings` by batch, entity type, source key, target key and action. For updated records it records before-images. Rollback must never delete by year alone or truncate a shared table.

## Safe procedure

1. Record the batch tag, exact release SHA, TEST database name, source checksum, pre-import snapshot, and database row count/hash evidence.
2. Query provenance for the batch and show an impact preview: created users/roles/members/dividends; updated items and their before-images; dependent rows from subsequent activity.
3. Ensure no later batch has updated the same target IDs and no manual changes/dependent records would be overwritten. **If uncertain, stop; do not run automated rollback.**
4. Restore/test from a verified snapshot in an isolated database first, checking Admin/Staff/PR Officer identities and unrelated years.
5. After separate approval only, run `php scripts/legacy-member-migrate.php --rollback=<approved-batch-tag>` against TEST. Record all IDs impacted and reconcile post-rollback counts and balances.
6. If automated rollback refuses or checks fail, preserve TEST and escalate for a DBA-reviewed recovery plan. **Do not blindly restore over a live database**; restoring a full snapshot discards all later authorized work.

## Required rollback invariants

- No deletion of existing Super Admin/Staff or their roles, sessions, MFA and audit trail.
- No deletion or overwrite of data from other import batches or manually entered dividends.
- Created member roles must be provenance-tracked and removed exactly; unrelated roles must remain.
- Restored dividend `details_json` **and** `imported_at` must match before-images.
- No rollback across later modifications of the same target identities.
- Audit log writes should identify batch and counts without exposing national IDs, phone numbers or raw passwords.

The TEST data migration and rollback remain **not executed** in this review cycle.
