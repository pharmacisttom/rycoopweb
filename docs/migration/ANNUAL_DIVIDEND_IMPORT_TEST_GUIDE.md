# RYCOOPWEB — Annual Dividend Import (TEST) Operator Guide

**Status: Code review only. No production deployment or TEST data import has been performed for this PR.**

## Existing workflow

Route: `/admin/dividends/import` (authorized `dividends.import` staff only). Backend: `POST /api/admin/dividends/preview` and `POST /api/admin/dividends/confirm` with CSRF and session-scoped 30-minute preview token. These are existing routes, not new public APIs.

1. Verify TEST URL, database backup/restore evidence, role permissions, current member counts and member portal disabled. Keep `FEATURE_MEMBER_PORTAL=false`. Do not alter `.env`.
2. Prepare one year at a time (2567, then 2568). Use XLSX or UTF-8 CSV, **not old XLS**. The upload limit is 5 MB. Consult `/templates/member-dividends-template.xlsx` or `.csv`.
3. Excel columns are resolved by **exact Thai header names**, not positions. An extra left-hand "ลำดับ" column is ignored when required headings remain present, but the actual historical workbook must be validated. The year field may be absent if a valid target year is selected.
4. Preserve `id_card` (13 digits) and `member_no` (including leading zeros) as text. Remove the sample/template row and verify the full list is authorized for processing.
5. Click **ตรวจสอบไฟล์**. Investigate **every** row error. Any duplicate/mismatched member identifier fails preview. Verify count, selected year, new member count, changed records, and the total net amount with source accounting report.
6. A new member/account created by this importer is assigned a random unguessable password and `inactive` status. **This does not enroll a member for login.** Identity verification and approved activation are separate steps.
7. The operator must confirm they've reviewed annual count/net and have a verified TEST database backup before clicking **ยืนยันนำเข้า**. This confirmation is a UI safety check, not proof that a backup exists; an independent operator must verify the backup.
8. Reconcile `members`, `users`, `user_roles`, `member_dividends`, `member_import_runs`, audit changes and annual totals with a snapshot. Re-uploading must not duplicate year/member rows. All discrepancies block acceptance.
9. Only then repeat the workflow for the following year. Verify importing 2568 does not change already verified 2567 data.

## Mandatory security checks

- No copying the old member-number-as-password behavior. No new account activation.
- No production actions, SQL dump import to `rycoopweb_test`, or migrations 019/020/025.
- Do not mix this annual Excel upload with PR #15's legacy SQL user/member migration until identity collision/mapping is reconciled.
- HTML UI offers Preview/Confirm, but Preview checks current data, and Confirm rechecks snapshots within its transaction. Test this against real historical source files on isolated TEST before any live import.
- Check for formula errors, renamed headers, column reordering, duplicate records, 13-digit national ID precision, invalid decimal money, stale sessions and rollback on failure.
- Only authorized staff may access these routes; enforce auth + permission + CSRF server side, and do not print raw member IDs in CI or shared logs.

## CI and rollout gate

PHP 8.3/8.5, frontend build, browser QA, a disposable MySQL 8 integration exercise, and actual workbook preview/reconciliation are required before merging and deploying. Current automated tests use fixtures; they are **not** evidence that the historical Excel files were successfully imported.

For PR #16, stop at review. No production deployment and no TEST database mutation are authorized here.
