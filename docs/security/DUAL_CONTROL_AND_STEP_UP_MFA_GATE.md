# RYCOOPWEB 3.0 — Dual-Control (Maker-Checker), Server-Side Step-Up MFA & Non-Transactional Restore Security Gate

## 1. Executive Summary

This document specifies the **Backend Security Gate Architecture**, **Manual Security UAT Checklist**, and **Rollback Plan** implemented on branch `codex/admin-dual-control-security` (from `Test` commit `139cc6b4275c3fcfebe14752c33392b34a11d517`).

### Core Security Invariants
1. **Backend RBAC Enforcement on Every Critical Endpoint**:
   - No endpoint relies solely on UI route visibility or general session presence.
   - Separation of Duties (SoD) is enforced at the database permission layer:
     - `it_admin` holds `backup.restore.request` (**Maker**) and **cannot** approve (`backup.restore.approve`).
     - `security_admin` holds `backup.restore.approve` (**Checker**) and **cannot** request (`backup.restore.request`).
     - `auditor` is strictly read-only (`.view` / `.export`).
     - `member` holds zero administrative permissions.
2. **Server-Side Step-Up MFA Freshness & Single-Use Consumption**:
   - Login-time MFA (`2fa_verified` in session) is **never** accepted as sufficient for critical mutations (`403 STEP_UP_MFA_REQUIRED`).
   - Users must verify a fresh 6-digit TOTP code via `POST /api/auth/mfa/step-up` (or inline `totp_code` / `step_up_code`).
   - Server session records `mfa_step_up_verified_at`, `mfa_step_up_expires_at` (default TTL `300s`), `mfa_step_up_slice`, `mfa_step_up_session_version`, and `mfa_step_up_consumed`.
   - TOTP time-slice replay (`users.two_factor_last_used_slice`) is blocked atomically under row lock (`403 STEP_UP_MFA_INVALID` / `STEP_UP_MFA_REPLAY_BLOCKED`).
   - Step-Up MFA verification is **single-use** (`403 STEP_UP_MFA_ALREADY_CONSUMED` if reused for a second critical command).
3. **Ironclad Dual-Control (Maker !== Checker, Including `super_admin`)**:
   - `approval_requests.requested_by !== approval_requests.reviewed_by` is enforced unconditionally across **all** roles, **including `super_admin`** (`403 SELF_APPROVAL_FORBIDDEN`).
4. **Transactional Pessimistic Row Locking & Two-Phase Non-Transactional Restore Gate**:
   - Approval state transitions use `Database::transaction()` + `SELECT ... FOR UPDATE` + atomic Compare-And-Swap (`UPDATE ... WHERE id = ? AND status = 'pending' AND reviewed_by IS NULL`).
   - Because MySQL `.sql` backup dumps execute DDL statements (`CREATE TABLE`, `DROP TABLE`, `ALTER TABLE`) that cause implicit commits in MySQL and cannot be rolled back by a SQL transaction, `DualControlSecurityService::executeApprovedRestore()` implements a **Two-Phase Claim-Before-Execute State Machine**:
     - **Phase 1 (Transactional + `FOR UPDATE` Row Lock)**: Verifies RBAC, durable filesystem journal (`storage/logs/restore_execution_journal.jsonl`), `status === 'approved'`, `executed_at === NULL`, `requested_by !== reviewed_by`, `action_key === 'backup.restore.request'`, `target_id === $filename`, `expires_at`, post-approval execution window (`1800s`), **SHA-256 file integrity** (`hash_file('sha256', $filePath)` against `payload_json.sha256` to block TOCTOU file tampering), fresh Step-Up MFA, and the server policy kill-switch (`BACKUP_RESTORE_EXECUTION_ENABLED`). When enabled, Phase 1 atomically sets `executed_at` and appends a `CLAIMED` entry to `restore_execution_journal.jsonl` before committing.
     - **Phase 2 (Non-Transactional Execution & Post-Restore Reconciliation)**: Executes the `.sql` dump, appends `COMPLETED` (or `FAILED`) to `restore_execution_journal.jsonl`, and re-updates `approval_requests.executed_at` in case the restored SQL dump overwrote the `approval_requests` table.
5. **Restore Execution Disabled by Default (`BACKUP_RESTORE_EXECUTION_ENABLED=false`)**:
   - `config('security.dual_control.backup_restore_execution_enabled')` defaults to `false`.
   - Even with a valid Dual-Control approval and fresh Step-Up MFA, restore execution returns `423 RESTORE_EXECUTION_DISABLED_BY_POLICY` until both Backend Enforcement and Manual Security UAT have passed and been signed off.
6. **Audit Trail, Correlation ID & Zero Secret Storage**:
   - Every Maker, Checker, Step-Up MFA, Role/Permission mutation, and Restore gate event logs `correlation_id` (`X-Correlation-Id`) to `audit_logs`.
   - `AdminControlCenterService::REDACTED_KEYS` strips `code`, `totp_code`, `step_up_code`, `mfa_code`, `two_factor_secret`, `recovery_code`, `password`, and tokens before any database or log write.

---

## 2. Hardened Endpoints Matrix

| Endpoint | Method | Controller | Backend RBAC Requirement | Step-Up MFA | Dual-Control (`approval_requests`) |
|---|---|---|---|---|---|
| `/api/auth/mfa/step-up` | `POST` | `Admin\MfaController@stepUp` | Authenticated active staff (`AuthMiddleware`) | Issues fresh single-use Step-Up session (`300s` TTL) | N/A |
| `/api/admin/approvals` | `GET` | `Admin\ControlCenterApiController@approvalsList` | `backup.view`, `security.view`, `audit.view`, `operations.view` | No (Read-only) | Lists sanitized requests |
| `/api/admin/approvals` | `POST` | `Admin\ControlCenterApiController@createApprovalRequest` | Maker permission for `action_key` | No (Maker request creation) | Creates `pending` request |
| `/api/admin/approvals/{id}/approve` | `POST` | `Admin\ControlCenterApiController@approveRequest` | Checker permission (`backup.restore.approve`, etc.) | **Required (Fresh + Single-Use)** | Enforces `requested_by !== reviewed_by` (incl. `super_admin`), `pending`, non-expired |
| `/api/admin/approvals/{id}/reject` | `POST` | `Admin\ControlCenterApiController@rejectRequest` | Checker permission | No | Transitions `pending` -> `rejected` under row lock |
| `/api/admin/approvals/{id}/cancel` | `POST` | `Admin\ControlCenterApiController@cancelRequest` | Exact Maker (`requested_by === Auth::id()`) | No | Transitions `pending` -> `cancelled` under row lock |
| `/api/admin/backups/restore-request` | `POST` | `Admin\ControlCenterApiController@createRestoreRequest` | `backup.restore.request` (`it_admin` / `super_admin`) | No | Creates `pending` restore request + pins `.sql` SHA-256 |
| `/api/admin/backups/restore-execute` | `POST` | `Admin\ControlCenterApiController@executeRestore` | `backup.restore.request` / `backup.restore.approve` | **Required (Fresh + Single-Use)** | Requires `approved` request (`Maker !== Checker`), SHA-256 match, non-expired, `BACKUP_RESTORE_EXECUTION_ENABLED=true` |
| `/admin/backups/restore` | `POST` | `Admin\BackupController@restoreBackup` | `super_admin` + `backup.restore.request` | **Required (Fresh + Single-Use)** | Routed through `DualControlSecurityService::executeApprovedRestore()` |
| `/admin/backups/delete` | `POST` | `Admin\BackupController@deleteBackup` | `super_admin` + `backup.delete` | **Required (Fresh + Single-Use)** | Direct Step-Up MFA gate |
| `/api/admin/access/roles` (`create`/`update`/`delete`) | `POST` | `Admin\AccessController` | `super_admin` + `roles.manage` | **Required (Fresh + Single-Use)** | Direct Step-Up MFA gate |
| `/api/admin/access/users` (`create`/`update`/`delete`) | `POST` | `Admin\AccessController` | `super_admin` + `users.update` | **Required (Fresh + Single-Use)** | Direct Step-Up MFA gate |
| `/api/admin/staff/{id}/role` | `POST` | `Admin\StaffManagementController@updateRole` | `super_admin` + `roles.manage` | **Required (Fresh + Single-Use)** | Direct Step-Up MFA gate |
| `/api/admin/staff/{id}/suspend` | `POST` | `Admin\StaffManagementController@suspend` | `super_admin` + `users.suspend` | **Required (Fresh + Single-Use)** | Direct Step-Up MFA gate |
| `/api/admin/staff/{id}/activate` | `POST` | `Admin\StaffManagementController@activate` | `super_admin` + `users.update` | **Required (Fresh + Single-Use)** | Direct Step-Up MFA gate |
| `/api/admin/staff/{id}/revoke-sessions` | `POST` | `Admin\StaffManagementController@revokeSessions` | `super_admin` + `security.sessions.revoke` | **Required (Fresh + Single-Use)** | Direct Step-Up MFA gate |
| `/api/admin/staff/{id}/relink` | `POST` | `Admin\StaffManagementController@relink` | `super_admin` + `security.mfa.manage` | **Required (Fresh + Single-Use)** | Direct Step-Up MFA gate |
| `/admin/users/store` & `/admin/users/{id}/delete` | `POST` | `Admin\UserController` | `super_admin` + `users.create` / `users.suspend` | **Required (Fresh + Single-Use)** | Direct Step-Up MFA gate |
| `/admin/settings/update` | `POST` | `Admin\SettingController@update` | `super_admin` + `settings.update` | **Required (Fresh + Single-Use)** | Direct Step-Up MFA gate |

---

## 3. Manual Security UAT Checklist (Required Before Enabling Restore Execution)

> **CRITICAL POLICY**: `BACKUP_RESTORE_EXECUTION_ENABLED` in `.env` / `config/security.php` **MUST remain `false`** until every item in the Manual Security UAT Checklist below has been verified and signed off on `TEST` (`https://test.rayongcoop.org`).

1. **UAT-01 (Self-Approval Block)**:
   - Log in as `super_admin` Account A, create a Backup Restore Request (`POST /api/admin/backups/restore-request`).
   - Verify Step-Up MFA as Account A and attempt to approve the same request (`POST /api/admin/approvals/{id}/approve`).
   - **Expected**: HTTP `403` with `code: "SELF_APPROVAL_FORBIDDEN"`.
2. **UAT-02 (Separation of Duties — Maker vs Checker)**:
   - Log in as `it_admin`, create a Backup Restore Request (`200 OK`), then attempt to approve it -> **Expected**: HTTP `403` (`CHECKER_PERMISSION_DENIED`).
   - Log in as `security_admin`, attempt to create a Backup Restore Request -> **Expected**: HTTP `403` (`MAKER_PERMISSION_DENIED`).
3. **UAT-03 (Login-Only MFA Rejection & Step-Up Freshness)**:
   - Log in as `security_admin` (completing login-time 2FA), do **not** call `/api/auth/mfa/step-up`, and attempt to approve the pending request.
   - **Expected**: HTTP `403` (`STEP_UP_MFA_REQUIRED`).
4. **UAT-04 (TOTP Replay & Single-Use Step-Up Consumption)**:
   - Submit a valid 6-digit TOTP code to `/api/auth/mfa/step-up` (`200 OK`), then immediately submit the same 6-digit code again.
   - **Expected**: HTTP `403` (`STEP_UP_MFA_INVALID`).
   - Approve one request (`200 OK`), then immediately attempt to approve a second request without a new Step-Up TOTP code.
   - **Expected**: HTTP `403` (`STEP_UP_MFA_ALREADY_CONSUMED`).
5. **UAT-05 (Default Restore Execution Policy Lock)**:
   - With a valid approved request (`status = 'approved'`, Maker !== Checker) and fresh Step-Up MFA, call `POST /api/admin/backups/restore-execute`.
   - **Expected**: HTTP `423` (`RESTORE_EXECUTION_DISABLED_BY_POLICY`) while `BACKUP_RESTORE_EXECUTION_ENABLED=false`.
6. **UAT-06 (Audit Log & Secret Redaction Check)**:
   - Query `GET /api/admin/audit-logs/center` and inspect `approval_requests` and `audit_logs`.
   - **Expected**: Every event has a `correlation_id`, and zero OTP codes, TOTP secrets, or recovery codes appear anywhere.

---

## 4. Rollback Plan

1. **Zero Destructive Schema Changes**:
   - Branch `codex/admin-dual-control-security` uses the existing `approval_requests`, `roles`, `permissions`, `role_permissions`, `users`, and `audit_logs` tables from Migration `025_create_admin_control_center_tables.sql`.
   - Migrations `019` and `020` are **never** executed.
   - Existing `users.two_factor_secret` ciphertexts, `APP_KEY`, and `user_mfa_recovery_codes` are untouched.
2. **Application Code Rollback (Symlink Switch)**:
   - If a rollback is required on `TEST`, repoint `/var/www/rycoopweb/current` to the previous release directory (`139cc6b4275c3fcfebe14752c33392b34a11d517`) and reload PHP-FPM (`sudo systemctl reload php8.3-fpm`).
3. **Emergency Restore Execution Kill-Switch**:
   - If `BACKUP_RESTORE_EXECUTION_ENABLED` was temporarily enabled during a scheduled maintenance window on `TEST`, set `BACKUP_RESTORE_EXECUTION_ENABLED=false` in `.env` to immediately lock all restore execution (`423 RESTORE_EXECUTION_DISABLED_BY_POLICY`) without restarting the database.

