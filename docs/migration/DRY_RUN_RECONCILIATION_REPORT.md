# RYCOOPWEB 3.0 — Dry Run & Reconciliation Verification Report

**Date:** 2026-10-31
**Command Executed:** `php scripts/legacy-member-migrate.php --dry-run`
**Execution Status:** `DRY_RUN_PASSED`

---

## 1. Dry Run Evidence

```text
====================================================================
RYCOOPWEB 3.0 — Legacy Member Migration & Dividend Recovery CLI
====================================================================

Source Dump: C:/Users/ACER/Downloads/rayongcoop_db.sql
Target Years: 2567, 2568
Batch Tag: MIG-20261031-DRYRUN
Execution Mode: DRY-RUN (Simulate only, zero DB writes)
--------------------------------------------------------------------

Status: DRY_RUN_PASSED

=== RECONCILIATION TOTALS ===
Year 2567:
  - Records: 2,429
  - เงินปันผลรวม: 78,356,989.62 บาท
  - เงินเฉลี่ยคืนรวม: 7,017,501.18 บาท
  - รายรับรวม: 86,785,490.80 บาท
  - รายหักรวม: 15,766,193.35 บาท
  - ยอดคงเหลือสุทธิ (Net): 71,019,297.45 บาท
  - ยอดเงินที่ได้รับจริง: 72,093,125.97 บาท
Year 2568:
  - Records: 2,426
  - เงินปันผลรวม: 82,204,037.08 บาท
  - เงินเฉลี่ยคืนรวม: 6,883,432.49 บาท
  - รายรับรวม: 90,495,969.57 บาท
  - รายหักรวม: 17,860,236.17 บาท
  - ยอดคงเหลือสุทธิ (Net): 72,635,733.40 บาท
  - ยอดเงินที่ได้รับจริง: 74,083,019.77 บาท

=== MIGRATION SUMMARY ===
Users: Source=2508, Inserted=2508, Updated=0
Members: Source=2508, Inserted=2508, Updated=0
Dividends: Source=4855, Inserted=4855, Updated=0

Completed successfully.
```

## 2. Zero-Mutation Verification
Automated test `tests/LegacyMemberMigrationTest.php` verified:
- Initial database user count before dry run: `1` (Existing Super Admin)
- Database user count after dry run: `1` (**0 rows written**)
- Database member count after dry run: `0` (**0 rows written**)
- Database dividend count after dry run: `0` (**0 rows written**)
- Database import runs count after dry run: `0` (**0 rows written**)
- Transaction was successfully rolled back at the end of the simulation.
