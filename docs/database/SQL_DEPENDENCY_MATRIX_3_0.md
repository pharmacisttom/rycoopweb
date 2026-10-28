# RYCOOPWEB 3.0 — SQL Dependency Matrix, Foreign-Key Verification & RBAC Review (`Migration 025`)

## 1. Foreign-Key Verification Report (`025` vs `001`–`023`)

All foreign-key columns across `025_create_admin_control_center_tables.sql` have been verified against the referenced parent tables in `001_initial_schema.sql`:

| Child Table (`025`) | Child Column | Child Column Type | Referenced Parent Table (`001`) | Parent Column | Parent Column Type | Constraint Name | `ON DELETE` Rule | Compatibility |
|---|---|---|---|---|---|---|---|---|
| `approval_requests` | `requested_by` | `BIGINT UNSIGNED NOT NULL` | `users` | `id` | `BIGINT UNSIGNED NOT NULL` | `fk_approval_requests_maker` | `RESTRICT` | **100% Exact Match** |
| `approval_requests` | `reviewed_by` | `BIGINT UNSIGNED NULL DEFAULT NULL` | `users` | `id` | `BIGINT UNSIGNED NOT NULL` | `fk_approval_requests_checker` | `SET NULL` | **100% Exact Match** |
| `admin_dashboard_preferences` | `user_id` | `BIGINT UNSIGNED NOT NULL PRIMARY KEY` | `users` | `id` | `BIGINT UNSIGNED NOT NULL` | `fk_admin_dashboard_prefs_user` | `CASCADE` | **100% Exact Match** |
| `role_permissions` (`001`) | `role_id` | `BIGINT UNSIGNED NOT NULL` | `roles` | `id` | `BIGINT UNSIGNED NOT NULL` | `role_permissions_ibfk_1` | `CASCADE` | **100% Exact Match** |
| `role_permissions` (`001`) | `permission_id` | `BIGINT UNSIGNED NOT NULL` | `permissions` | `id` | `BIGINT UNSIGNED NOT NULL` | `role_permissions_ibfk_2` | `CASCADE` | **100% Exact Match** |

### Why `BIGINT UNSIGNED` Alignment Is Mandatory
In `001_initial_schema.sql`, `users.id`, `roles.id`, and `permissions.id` are defined as `BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` on `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`. Defining `approval_requests.requested_by`, `approval_requests.reviewed_by`, and `admin_dashboard_preferences.user_id` as `BIGINT UNSIGNED` guarantees full InnoDB foreign-key compatibility on MySQL 8.0 (`errno 150` / `ERROR 3780` prevention).

---

## 2. Safe Insertion Strategy & Double-Execution Idempotency Verification

1. **Zero `ON DUPLICATE KEY UPDATE` Side Effects**:
   - All role seeds (`roles`), permission seeds (`permissions`), and role-permission mappings (`role_permissions`) in `025_create_admin_control_center_tables.sql` use `INSERT INTO ... SELECT ... WHERE NOT EXISTS (...)`.
   - Existing roles (`super_admin`, `member`, `member_admin`, `staff`, or custom roles) are **never** updated, their `name`, `description`, and `updated_at` timestamps remain untouched, and `AUTO_INCREMENT` counters are not burned on repeated runs.
2. **Disposable Lab DB Double-Execution Results**:
   - Restored full schema (`001`, `015`, `017`, `022`, `023`) + existing custom roles/users into `rycoopweb_migration_lab_025_verify` and executed `025_create_admin_control_center_tables.sql` **twice**:
     - **After Run #1**: `roles = 12`, `permissions = 57`, `role_permissions = 80`, `users = 3`, `user_roles = 1`
     - **After Run #2**: `roles = 12`, `permissions = 57`, `role_permissions = 80`, `users = 3`, `user_roles = 1`
     - **Dual-Control FK Behavior**: Deleting a reviewer user (`id = 103`) cleanly sets `approval_requests.reviewed_by = NULL` (`ON DELETE SET NULL`) and cascades deletion of `admin_dashboard_preferences` (`ON DELETE CASCADE`), while `ON DELETE RESTRICT` prevents deleting a Maker user (`requested_by = 101`) referenced by an approval request.

---

## 3. RBAC Privilege & Separation-of-Duties (SoD) Review

All `role_permissions` seeded by `025_create_admin_control_center_tables.sql` match `AdminControlCenterService::ENTERPRISE_ROLE_MAP` with **zero privilege escalation**:

| Role Slug | Separation Tier | Granted Count | Exact Permissions Granted (`module.action`) | Explicitly Excluded High-Risk Permissions |
|---|---|---:|---|---|
| `super_admin` | System Governance | All (`*`) | Full governance under audit trail + last-admin lockout protection | Protected by `AccessService::protectAdministrator()` |
| `it_admin` | System Operations (Maker) | 13 | `dashboard.view`, `analytics.view`, `analytics.visitors.view`, `analytics.visitors.export`, `users.view`, `roles.view`, `audit.view`, `security.view`, `backup.view`, `backup.verify`, `backup.restore.request`, `system.health.view`, `settings.view` | **Cannot** `backup.restore.approve` (Checker), **cannot** `users.create/update/disable`, `roles.manage`, `permissions.manage`, `settings.update`, `security.sessions.revoke` |
| `security_admin` | Security Governance (Checker) | 13 | `dashboard.view`, `analytics.view`, `analytics.visitors.view`, `users.view`, `roles.view`, `security.view`, `security.mfa.view`, `security.sessions.revoke`, `audit.view`, `audit.export`, `backup.view`, `backup.restore.approve`, `system.health.view` | **Cannot** `backup.restore.request` (Maker), **cannot** `users.create/update/disable`, `roles.manage`, `permissions.manage`, `settings.update` |
| `auditor` | Independent Audit (Read-Only) | 14 | `dashboard.view`, `analytics.view`, `analytics.visitors.view`, `analytics.visitors.export`, `users.view`, `roles.view`, `members.view`, `finance.view`, `finance.export`, `audit.view`, `audit.export`, `security.view`, `backup.view`, `system.health.view` | **Zero** `.create`, `.update`, `.delete`, `.publish`, `.import`, `.manage`, `.revoke`, `.request`, `.approve` permissions |
| `executive` / `manager` | Executive Oversight (Read-Only) | 4 | `dashboard.view`, `analytics.view`, `analytics.visitors.view`, `finance.view` | **Zero** write permissions; **no** raw member PII (`members.view`), security, audit, or backup access |
| `finance` | Financial Administration | 8 | `dashboard.view`, `analytics.view`, `finance.view`, `finance.export`, `rates.view`, `rates.update`, `products.view`, `products.update` | **Cannot** self-publish (`rates.publish`, `products.publish`) or import dividends |
| `loan_officer` | Financial Operations | 5 | `dashboard.view`, `finance.view`, `rates.view`, `products.view`, `products.update` | **Cannot** update rates (`rates.update`), publish, or export finance |
| `member_admin` | Member Administration | 5 | `members.view`, `members.update`, `members.import`, `dividends.update`, `dividends.import` | Strictly isolated from CMS, finance rates, users, roles, security, and backups |
| `pr_officer` | Content Administration | 13 | `content.create`, `content.publish`, `news.view/create/update/publish`, `faqs.view/create/update`, `coop_info.view/update`, `people.view/update` | **Cannot** delete news/FAQs (`news.delete`, `faqs.delete`) or publish `coop_info`/`people` |
| `staff` | Staff Operations | 1 | `dashboard.view` | **Zero** content or administrative mutation permissions |
| `member` | Cooperative Member | 0 | **None** (`0` admin permissions) | Strictly blocked (`403`) on all `/admin/*` and `/api/admin/*` endpoints |

---

## 4. 14-Module SQL Dependency Matrix (`AdminControlCenterService.php`)

| # | Admin Control Center Module | Service Method(s) | Required Database Tables | Originating Migration(s) | Required RBAC Permission(s) (`025`) |
|---|---|---|---|---|---|
| 1 | **Executive Dashboard** | `getDashboardSummary()`, `getDashboardTrends()`, `getPendingTasksBreakdown()` | `users`, `user_roles`, `roles`, `members`, `login_logs`, `led_check_runs`, `member_profile_requests`, `complaints`, `news`, `public_content_revisions`, `led_check_results`, `staff_invitations`, `approval_requests` | `001`, `006b`, `010`, `018`, `022`, `023`, **`025`** | `dashboard.view`, `analytics.view` |
| 2 | **Visitor Analytics** | `VisitorCounterService::getDetailedAnalytics()`, `exportCsvData()` | `website_visits`, `visitor_counters` | `001`, `021` | `analytics.view`, `analytics.visitors.view`, `analytics.visitors.export` |
| 3 | **Operations Center** | `getOperationsOverview()`, `getPendingTasksBreakdown()` | `member_profile_requests`, `members`, `complaints`, `news`, `public_content_revisions`, `led_check_results`, `staff_invitations`, `approval_requests` | `001`, `006b`, `010`, `018`, `022`, `023`, **`025`** | `dashboard.view`, `operations.view`, `members.view`, `news.view`, `rates.view` |
| 4 | **User Management** | `AccessService::account()`, `AccessService::saveAccount()` | `users`, `user_roles`, `roles`, `staff_invitations` | `001`, `018`, `022` | `users.view`, `users.create`, `users.update`, `users.disable` |
| 5 | **Role & Permission Matrix** | `getAccessMatrix()`, `AccessService::role()`, `AccessService::saveRole()` | `roles`, `permissions`, `role_permissions`, `user_roles` | `001`, `015`, `017`, `023`, **`025`** | `roles.view`, `roles.manage`, `permissions.manage` |
| 6 | **Departments Directory** | `getDepartmentsSummary()`, `getPendingWorkByDepartment()` | `users`, `user_roles`, `roles` + pending queues | `001`, `018`, `022`, **`025`** | `users.view`, `roles.view`, `dashboard.view`, `departments.view` |
| 7 | **Member Management** | `MemberManagementController` | `members`, `member_profile_requests`, `member_admin_history`, `led_check_results` | `006b`, `010`, `012`, `014`, `016`, `018` | `members.view`, `members.update`, `members.import` |
| 8 | **Financial Services** | `PublicContentService`, `MemberManagementController` | `deposit_products`, `loan_products`, `interest_rates`, `member_dividends`, `financial_statistics`, `public_content_revisions` | `001`, `009`, `013`, `018`, `023` | `finance.view`, `finance.export`, `rates.view/update/publish`, `products.view/update/publish`, `dividends.update/import` |
| 9 | **Content Management** | `NewsWorkflowService`, `PublicContentController` | `news`, `faqs`, `public_content_revisions` | `001`, `023` | `content.create`, `content.publish`, `news.*`, `faqs.*`, `coop_info.*`, `people.*` |
| 10 | **Audit Log Viewer** | `getAuditLogs()`, `redactMetadata()` | `audit_logs`, `login_logs`, `users` | `001`, `022` | `audit.view`, `audit.export` |
| 11 | **Security & MFA Center** | `getSecurityOverview()`, `getMfaAdministration()`, `getSanitizedSessions()` | `login_logs`, `rate_limit_attempts`, `users`, `user_roles`, `roles`, `user_mfa_recovery_codes` | `001`, `008`, `022`, **`025`** | `security.view`, `security.mfa.view`, `security.sessions.revoke` |
| 12 | **Backup & Recovery Governance** | `getBackupHealth()`, Dual-Control `approval_requests` | `approval_requests`, `audit_logs` + `storage/backups` inspection | `001`, **`025`** | `backup.view`, `backup.verify`, `backup.restore.request`, `backup.restore.approve` |
| 13 | **System Health Diagnostics** | `getSystemHealth()` | `migrations`, `system_logs`, `led_check_runs` + runtime checks | `001`, `010`, **`025`** | `system.health.view`, `health.view`, `settings.view` |
| 14 | **Reports & System Settings** | `reportsCatalog()`, `admin_dashboard_preferences`, `site_settings` | `site_settings`, `admin_dashboard_preferences`, `financial_statistics`, `member_dividends` | `001`, `013`, **`025`** | `reports.view`, `settings.view`, `settings.update` |

---

## 5. Rollback & Recovery Plan for Migration `025`

1. **Application Rollback (< 60 Seconds, Zero DB Downtime)**:
   - Because `025_create_admin_control_center_tables.sql` is 100% additive (`CREATE TABLE IF NOT EXISTS` and `INSERT INTO ... SELECT ... WHERE NOT EXISTS`), rolling back the `/var/www/rycoopweb/test-current` symlink requires no destructive SQL changes.
2. **Optional Schema Reversal (Lab or TEST)**:
   ```sql
   DROP TABLE IF EXISTS `admin_dashboard_preferences`;
   DROP TABLE IF EXISTS `approval_requests`;
   DELETE FROM `migrations` WHERE `migration` = '025_create_admin_control_center_tables.sql';
   ```
