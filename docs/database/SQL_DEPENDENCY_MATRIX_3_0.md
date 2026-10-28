# RYCOOPWEB 3.0 — SQL Dependency Matrix & Migration 025 Verification Report

## 1. Repository Migration Inventory (`001` – `025`)

| # | Migration File | Purpose | Safety Classification | Status on TEST |
|---|---|---|---|---|
| 001 | `001_initial_schema.sql` | Core `users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `news`, `faqs`, `downloads`, `audit_logs`, `settings`, `visitor_stats` | Safe / Baseline | Applied |
| 002 | `002_enhance_security_logs.sql` | `security_logs`, `rate_limits`, `users.locked_until` | Safe / Additive | Applied |
| 003 | `003_financial_services.sql` | `deposit_products`, `loan_products`, `interest_rates` | Financial Baseline | Applied |
| 004 | `004_enhance_news.sql` | News categories, pinning, gallery images | Safe / Additive | Applied |
| 005 | `005_create_board_and_staff.sql` | `board_members`, `staff_members`, `advisors`, `committee_groups` | Safe / Additive | Applied |
| 006 | `006_create_visitors_log.sql` | `visitors_log` (`ip_address`, `user_agent`, `page_url`, `visit_date`, `visit_time`) | Safe / Additive | Applied |
| 006b | `006b_create_members_table.sql` | `members`, `member_dividends` | Financial Baseline | Applied |
| 007 | `007_create_complaints_tables.sql` | `complaints`, `complaint_logs`, `complaint_attachments` | Safe / Additive | Applied |
| 008 | `008_enhance_complaints_security.sql` | Complaint tracking tokens & encryption columns | Safe / Additive | Applied |
| 009 | `009_update_dividend_rates_68.sql` | Seed FY 2568 dividend rates | Financial Seed | Applied |
| 010 | `010_create_led_member_check_tables.sql` | `led_check_runs`, `led_check_results`, `led_Status_logs`, `led_audit_logs`, `led_schedules` | Safe / Additive | Applied |
| 011 | `011_enhance_members_Import.sql` | `member_import_logs`, member status & profile fields | Safe / Additive | Applied |
| 012 | `012_create_loan_estimate_requests.sql` | `loan_estimate_requests` | Safe / Additive | Applied |
| 013 | `013_create_user_social_accounts.sql` | `user_social_accounts` | Safe / Additive | Applied |
| 014 | `014_normalize_board_positions.sql` | Normalize board member ordering/positions | Safe | Applied |
| 015 | `015_seed_board_69.sql` | Seed FY 2569 board members | Content Seed | Applied |
| 016 | `016_add_workflow_fields_to_news.sql` | `news.workflow_status`, `submitted_by`, `approved_by`, `published_by` | Safe / Additive | Applied |
| 017 | `017_seed_rbac_permissions.sql` | Seed initial 21 legacy permissions (`news.*`, `faqs.*`, `members.*`, `dividends.*`, `coop_info.*`, `people.*`) | Safe / Additive | Applied |
| 018 | `018_member_profile_requests.sql` | `member_profile_requests`, `member_dividend_History`, `users.department` | Safe / Additive | Applied |
| **019** | `019_cleanup_legacy_interest_rates.sql` | Legacy interest rate cleanup (`DELETE`/`UPDATE` on financial tables) | **BLOCKED (Destructive/Financial)** | **DO NOT RUN** |
| **020** | `020_sync_deposit_rates.sql` | Deposit rate overwrite (`UPDATE` on financial tables) | **BLOCKED (Financial)** | **DO NOT RUN** |
| 021 | `021_expand_staff_positions.sql` | Expand staff position length | Safe / Additive | Applied |
| 022 | `022_google_auth_and_mandatory_mfa.sql` | `users.google_sub`, `users.two_factor_*`, `user_mfa_recovery_codes`, `staff_invitations`, `auth_audit_logs` | Safe / Additive | Applied |
| 023 | `023_public_content_revisions.sql` | `public_content_revisions`, `rates.*` and `products.*` permissions | Safe / Additive | Applied |
| **025** | `025_create_admin_control_center_tables.sql` | `approval_requests` (Dual-Control Maker-Checker), `admin_dashboard_preferences`, Enterprise RBAC roles (`it_admin`, `security_admin`, `auditor`, `executive`, `loan_officer`, `pr_officer`, `member_admin`), and 32 Enterprise permissions + `INSERT IGNORE` role mappings | **Safe / 100% Additive** | **Staged for Lab & TEST** |

> **Note on Numbering (`024`)**: Migration `024` was intentionally skipped in the 3.0 architecture specification so `025_create_admin_control_center_tables.sql` matches the canonical Admin Control Center 3.0 specification.

---

## 2. 14-Module SQL Dependency Matrix (`AdminControlCenterService.php`)

| # | Admin Control Center Module | Service Method(s) | Required Database Tables | Key Columns Queried | Originating Migration(s) | Required RBAC Permission(s) (`025`) |
|---|---|---|---|---|---|---|
| 1 | **Executive Dashboard** | `getExecutiveSummary()`, `getDashboardTrends()`, `getPendingTasksBreakdown()` | `users`, `user_roles`, `roles`, `members`, `visitor_stats`, `visitors_log`, `security_logs`, `audit_logs`, `member_profile_requests`, `complaints`, `news`, `public_content_revisions`, `led_check_results`, `staff_invitations`, `approval_requests` | `users.status/deleted_at/last_login_at`, `members.status`, `visitor_stats.visit_date/views_count/unique_visitors_count`, `visitors_log.visit_date/visit_time/ip_address`, `approval_requests.status` | `001`, `002`, `006`, `006b`, `007`, `010`, `016`, `018`, `022`, `023`, **`025`** | `dashboard.view` |
| 2 | **Visitor Analytics** | `VisitorCounterService::getAdminAnalytics()`, `exportCsvData()` | `visitor_stats`, `visitors_log` | `visitor_stats.visit_date`, `views_count`, `unique_visitors_count`; `visitors_log.ip_address`, `user_agent`, `page_url`, `visit_date`, `visit_time` | `001`, `006` | `analytics.view`, `analytics.visitors.view`, `analytics.visitors.export` |
| 3 | **Operations Center** | `getOperationsOverview()`, `getPendingTasksBreakdown()` | `member_profile_requests`, `members`, `complaints`, `news`, `public_content_revisions`, `led_check_results`, `staff_invitations`, `approval_requests` | `member_profile_requests.status IN ('submitted','in_progress','pending')`, `public_content_revisions.section AS section_key`, `led_check_results.status IN ('POSSIBLE_MATCH','MULTIPLE_MATCH','REVIEW_REQUIRED') AND reviewed_at IS NULL`, `approval_requests.status = 'pending'` | `001`, `006b`, `007`, `010`, `016`, `018`, `022`, `023`, **`025`** | `operations.view` |
| 4 | **User Management** | `AccessService::users()`, `MemberManagementController::usersIndex()` | `users`, `user_roles`, `roles`, `staff_invitations` | `users.id`, `username`, `name`, `email`, `department`, `status`, `two_factor_enabled`, `two_factor_confirmed_at`, `google_email`, `last_login_at` | `001`, `018`, `022` | `users.view`, `users.create`, `users.update`, `users.disable` |
| 5 | **Role & Permission Matrix** | `getAccessMatrix()`, `AccessService::roles()`, `AccessService::saveRole()` | `roles`, `permissions`, `role_permissions`, `user_roles` | `roles.id/name/slug/description`, `permissions.id/name/module/action`, `role_permissions.role_id/permission_id` | `001`, `017`, `023`, **`025`** | `roles.view`, `roles.manage`, `permissions.manage` |
| 6 | **Departments Directory** | `getDepartmentsSummary()`, `getPendingWorkByDepartment()` | `users`, `user_roles`, `roles`, plus pending queues (`member_profile_requests`, `complaints`, `news`, `public_content_revisions`, `led_check_results`, `staff_invitations`) | `users.department`, `users.status`, `roles.slug` | `001`, `018`, **`025`** | `departments.view` |
| 7 | **Member Management** | `MemberManagementController` | `members`, `member_profile_requests`, `member_import_logs`, `led_check_results` | `members.member_no`, `first_name`, `last_name`, `org_name`, `status` | `006b`, `010`, `011`, `018` | `members.view`, `members.update`, `members.import` |
| 8 | **Financial Services** | `MemberManagementController`, `RateController`, `LoanController` | `deposit_products`, `loan_products`, `interest_rates`, `member_dividends`, `member_dividend_History`, `loan_estimate_requests` | Product rates, dividend records, loan estimate logs (Read/Managed via existing controllers) | `003`, `006b`, `009`, `012`, `018`, `023` | `finance.view`, `finance.export`, `rates.view/update`, `products.view/create/update`, `dividends.update/import` |
| 9 | **Content Management** | `NewsController`, `FaqController`, `DownloadController`, `CoopInfoController` | `news`, `faqs`, `downloads`, `board_members`, `staff_members`, `public_content_revisions` | `news.workflow_status`, `public_content_revisions.section/state` | `001`, `004`, `005`, `014`, `015`, `016`, `021`, `023` | `content.create`, `content.publish`, `news.*`, `faqs.*`, `coop_info.*`, `people.*` |
| 10 | **Audit Log Viewer** | `getAuditLogs()`, `exportAuditLogsCsv()`, `redactMetadata()` | `audit_logs`, `auth_audit_logs`, `users` | `audit_logs.user_id`, `module`, `action`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at` | `001`, `022` | `audit.view`, `audit.export` |
| 11 | **Security & MFA Center** | `getSecurityOverview()`, `getMfaAdministration()`, `TwoFactorService` | `security_logs`, `auth_audit_logs`, `rate_limits`, `users`, `user_roles`, `roles`, `user_mfa_recovery_codes` | `users.two_factor_enabled`, `two_factor_confirmed_at`, `user_mfa_recovery_codes.user_id/used_at` (Never exposes `two_factor_secret` or `code_hash`) | `001`, `002`, `022`, **`025`** | `security.view`, `security.mfa.view`, `security.sessions.revoke` |
| 12 | **Backup & Recovery Governance** | `getBackupStatus()`, Dual-Control `approval_requests` | `approval_requests`, `audit_logs` + filesystem inspection of `storage/backups` | `approval_requests.request_no`, `module`, `action_key` (`backup.restore.request` / `backup.restore.approve`), `status`, `requested_by`, `reviewed_by` | `001`, **`025`** | `backup.view`, `backup.verify`, `backup.restore.request`, `backup.restore.approve` |
| 13 | **System Health Diagnostics** | `getSystemHealth()` | `migrations`, `security_logs`, `audit_logs` + runtime PHP/MySQL/Disk/Session checks | `SELECT 1`, MySQL `VERSION()`, disk space, `storage/logs` error tail | `001`, `002`, **`025`** | `system.health.view`, `health.view` |
| 14 | **Reports & System Settings** | `getReportsCatalog()`, `exportReportCsv()`, `admin_dashboard_preferences` | `settings`, `admin_dashboard_preferences`, `visitor_stats`, `audit_logs`, `security_logs`, `users`, `members` | `settings.key/value`, `admin_dashboard_preferences.user_id/sidebar_collapsed/default_date_range/pinned_widgets_json` | `001`, **`025`** | `reports.view`, `settings.view`, `settings.update` |

---

## 3. Schema Discrepancies Resolved in `AdminControlCenterService.php`

During the pre-merge schema audit against MySQL 8.0 migrations `001`–`023`, four query discrepancies were identified and fixed in `app/Services/AdminControlCenterService.php`:

1. **`getMfaAdministration()` (`users` vs `user_mfa_recovery_codes`)**:
   - **Previous query**: Selected `u.two_factor_recovery_codes` directly from `users`. In Migration `022_google_auth_and_mandatory_mfa.sql`, recovery codes are stored in `user_mfa_recovery_codes` (`user_id`, `code_hash`, `used_at`), not in a `users.two_factor_recovery_codes` column.
   - **Fix**: Query `SELECT u.*, r.slug AS role_slug, r.name AS role_name FROM users u ...` and count unused recovery codes via `TwoFactorService::getRecoveryCodesRemaining((int) $row['id'])` while projecting only non-sensitive metadata fields.
2. **`getOperationsOverview()` (`public_content_revisions.section`)**:
   - **Previous query**: Selected `section_key` from `public_content_revisions`. In Migration `023_public_content_revisions.sql`, the column is named `section`.
   - **Fix**: Query `SELECT id, section, section AS section_key, state, effective_on, note, created_at FROM public_content_revisions WHERE state = 'draft'`.
3. **`getOperationsOverview()` & `getPendingTasksBreakdown()` (`member_profile_requests.status`)**:
   - **Previous query**: Filtered `WHERE status = 'pending'`. In Migration `018_member_profile_requests.sql`, the ENUM is `('submitted', 'in_progress', 'approved', 'rejected', 'cancelled')`.
   - **Fix**: Filter `WHERE status IN ('submitted', 'in_progress', 'pending')`.
4. **`getPendingTasksBreakdown()` (`led_check_results.status`)**:
   - **Previous query**: Filtered `WHERE verification_status = 'PENDING_REVIEW'`. In Migration `010_create_led_member_check_tables.sql`, the column is `status` (`ENUM('NOT_FOUND','POSSIBLE_MATCH','MULTIPLE_MATCH','REVIEW_REQUIRED','VERIFIED_MATCH','FALSE_MATCH','API_ERROR')`) paired with `reviewed_at IS NULL`.
   - **Fix**: Filter `WHERE status IN ('POSSIBLE_MATCH', 'MULTIPLE_MATCH', 'REVIEW_REQUIRED') AND reviewed_at IS NULL`.

---

## 4. Rollback & Recovery Plan for Migration `025`

Because `025_create_admin_control_center_tables.sql` is **100% additive** (it creates two new tables `approval_requests` and `admin_dashboard_preferences`, and uses `INSERT ... ON DUPLICATE KEY UPDATE` / `INSERT IGNORE` on `roles`, `permissions`, and `role_permissions`), it does **not** alter any existing columns or existing user/MFA/financial rows.

### 4.1 Application Rollback (Zero Database Downtime)
If the application symlink `/var/www/rycoopweb/test-current` is rolled back to the previous release commit, the new tables (`approval_requests`, `admin_dashboard_preferences`) and additional rows in `permissions`/`roles` are completely ignored by earlier releases. **No destructive SQL rollback is required for application rollback.**

### 4.2 Full Schema Reversal (If Explicitly Required in Lab or TEST)
If operators ever wish to remove the two new tables and the `025` migration tracking record in `rycoopweb_migration_lab` or `rycoopweb_test`:

```sql
-- 1. Drop only the two new additive tables created by 025 (if empty / verified)
DROP TABLE IF EXISTS admin_dashboard_preferences;
DROP TABLE IF EXISTS approval_requests;

-- 2. Remove the 025 tracking entry from migrations table
DELETE FROM migrations WHERE migration = '025_create_admin_control_center_tables.sql';
```
*(Note: Seeded `permissions` and `roles` entries are harmless metadata and should be retained so any role assignments referencing them remain intact.)*

