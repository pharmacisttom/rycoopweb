# RYCOOPWEB 3.0 — Admin Control Center RBAC & Permission Matrix

**Organization**: Rayong Public Health Savings and Credit Cooperative Limited  
**Security Standard**: Deny-by-Default, Least Privilege, Separation of Duties, Mandatory MFA  
**Status**: Active & Reconciled with Existing `roles`, `permissions`, and `AccessService`

---

## 1. Role Reconciliation (Proposed Enterprise Roles ↔ Existing Database Slugs)

Per Section 6 of the specification, **existing role slugs and IDs are never overwritten or invalidated**. Each of the 10 proposed Enterprise Admin Control Center roles is mapped cleanly to an existing database role slug or supported as an additive role template:

| # | Proposed Enterprise Role | Existing / Canonical Slug | Thai Display Name | Operational Scope |
| :-: | :--- | :--- | :--- | :--- |
| 1 | **Super Admin** | `super_admin` | ผู้ดูแลระบบสูงสุด | Broad administrative control across all modules, subject to immutable audit logging and final-admin lockout safeguards |
| 2 | **System Administrator** | `it_admin` | ผู้ดูแลระบบและโครงสร้างพื้นฐาน | Application operations, system health monitoring, backup verification, and approved configuration |
| 3 | **Security Administrator** | `security_admin` *(or `it_admin`)* | ผู้ดูแลความมั่นคงปลอดภัย | MFA oversight, login monitoring, security event review, and session revocation |
| 4 | **Member Administrator** | `member_admin` | ผู้ดูแลระบบสมาชิก | Authorized member profile, dividend import, and member health administration only (visually & API-separated from system admin) |
| 5 | **Financial Administrator** | `finance` | เจ้าหน้าที่การเงิน | Authorized financial statistics, deposit/loan interest rate drafts, and financial reports |
| 6 | **Accounting Officer** | `loan_officer` *(or `accounting_officer`)* | เจ้าหน้าที่สินเชื่อและบัญชี | Loan product administration, dividend/billing verification, and operational finance |
| 7 | **Content Administrator** | `pr_officer` *(or `document_officer`)* | เจ้าหน้าที่ประชาสัมพันธ์และเนื้อหา | News, announcements, FAQs, documents, and public content draft/publish workflows |
| 8 | **Auditor / Internal Audit** | `auditor` | ผู้ตรวจสอบกิจการ | Read-only access to audit trails, login history, security overview, and controlled CSV exports |
| 9 | **Executive Viewer** | `executive` *(and `manager`)* | ผู้บริหาร / คณะกรรมการ | Read-only aggregate executive dashboards and financial summaries without raw personal secret access |
| 10 | **Staff Viewer** | `staff` *(and `welfare_officer`, `complaint_officer`)* | เจ้าหน้าที่ปฏิบัติการ | Limited operational workspace, assigned queues, and LED member check |
| — | *Cooperative Member* | `member` | สมาชิกสหกรณ์ | **Strictly isolated to Member Portal (`/member/*`) — zero access to `/admin/*` or `/api/admin/*`** |

---

## 2. Granular Resource-Action Permission Catalog

The permission catalog combines the existing `AccessService::CATALOG` keys (preserved 100% for backward compatibility) with the Enterprise Control Center permission keys:

### 2.1 Existing Content & Member Permissions (Preserved)
- `coop_info.view`, `coop_info.update`, `coop_info.publish`
- `people.view`, `people.update`, `people.publish`
- `rates.view`, `rates.update`, `rates.publish`
- `products.view`, `products.update`, `products.publish`
- `news.view`, `news.create`, `news.update`, `news.delete`, `news.publish`
- `faqs.view`, `faqs.create`, `faqs.update`, `faqs.delete`
- `members.view`, `members.update`
- `dividends.update`, `dividends.import`

### 2.2 Enterprise Admin Control Center Permission Keys
- **Dashboard & Analytics**: `dashboard.view`, `analytics.view`
- **User, Role & Access Management**: `users.view`, `users.create`, `users.update`, `users.disable`, `roles.view`, `roles.manage`, `permissions.manage`
- **Member Administration**: `members.view`, `members.update`, `members.import` (alias compatible with `dividends.import`)
- **Financial Administration**: `finance.view`, `finance.export`
- **Content Administration**: `content.create`, `content.publish` (mapped alongside `news.*`, `coop_info.*`, `people.*`)
- **Audit & Compliance**: `audit.view`, `audit.export`
- **Security Center**: `security.view`, `security.sessions.revoke`
- **Backup & Recovery**: `backup.view`, `backup.verify`, `backup.restore.request`, `backup.restore.approve`
- **System & Settings**: `settings.view`, `settings.update`, `system.health.view`

---

## 3. Explicit Role–Permission Matrix

Legend: `✅` Allowed | `👁️` Read-Only / Scoped | `—` Denied (Default Deny)

| Permission Key | Super Admin (`super_admin`) | System Admin (`it_admin`) | Security Admin (`security_admin`) | Member Admin (`member_admin`) | Financial Admin (`finance`) | Content Admin (`pr_officer`) | Auditor (`auditor`) | Executive Viewer (`executive`) | Staff Viewer (`staff`) | Member (`member`) |
| :--- | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: | :-: |
| `dashboard.view` | ✅ | ✅ | ✅ | — | 👁️ | — | 👁️ | ✅ | 👁️ | — |
| `analytics.view` | ✅ | ✅ | ✅ | — | 👁️ | 👁️ | 👁️ | ✅ | — | — |
| `users.view` | ✅ | ✅ | ✅ | — | — | — | 👁️ | — | — | — |
| `users.create` / `update` / `disable` | ✅ | — | — | — | — | — | — | — | — | — |
| `roles.view` | ✅ | ✅ | ✅ | — | — | — | 👁️ | — | — | — |
| `roles.manage` / `permissions.manage` | ✅ | — | — | — | — | — | — | — | — | — |
| `members.view` | ✅ | — | — | ✅ | — | — | 👁️ | — | — | — |
| `members.update` | ✅ | — | — | ✅ | — | — | — | — | — | — |
| `dividends.import` / `members.import` | ✅ | — | — | ✅ | — | — | — | — | — | — |
| `finance.view` / `rates.view` | ✅ | — | — | — | ✅ | — | 👁️ | ✅ | — | — |
| `rates.update` / `products.update` | ✅ | — | — | — | ✅ (Draft) | — | — | — | — | — |
| `rates.publish` / `products.publish` | ✅ | — | — | — | — (Checker) | — | — | — | — | — |
| `finance.export` | ✅ | — | — | — | ✅ | — | ✅ | — | — | — |
| `news.view` / `faqs.view` | ✅ | — | — | — | — | ✅ | 👁️ | — | — | — |
| `news.create` / `update` | ✅ | — | — | — | — | ✅ | — | — | — | — |
| `news.publish` | ✅ | — | — | — | — | ✅ | — | — | — | — |
| `audit.view` | ✅ | ✅ | ✅ | — | — | — | ✅ | — | — | — |
| `audit.export` | ✅ | — | ✅ | — | — | — | ✅ | — | — | — |
| `security.view` | ✅ | ✅ | ✅ | — | — | — | ✅ | — | — | — |
| `security.sessions.revoke` | ✅ | — | ✅ | — | — | — | — | — | — | — |
| `backup.view` / `backup.verify` | ✅ | ✅ | — | — | — | — | 👁️ | — | — | — |
| `backup.restore.request` | ✅ | ✅ | — | — | — | — | — | — | — | — |
| `backup.restore.approve` | ✅ (Dual) | — | — | — | — | — | — | — | — | — |
| `system.health.view` | ✅ | ✅ | ✅ | — | — | — | 👁️ | — | — | — |
| `settings.view` / `settings.update` | ✅ | 👁️ | — | — | — | — | — | — | — | — |

---

## 4. Critical Access Control Safeguards

1. **Server-Side Enforcement**: Hiding sidebar items in React is strictly a UX convenience. Every `/api/admin/*` route enforces `AuthMiddleware` (including mandatory 2FA verification) and either `RoleMiddleware` or `PermissionMiddleware` / `AdminControlCenterService::authorize()`.
2. **Final Super Admin Protection**: `AccessService::protectAdministrator()` and `StaffManagementController::protectFinalSuperAdmin()` acquire `FOR UPDATE` locks and block any attempt to delete, suspend, or demote the last active `super_admin` account, or for an administrator to suspend/demote their own active account.
3. **Separation of Duties (Maker–Checker)**:
   - Public content, interest rates (`rates.update` vs `rates.publish`), and news (`news.create` vs `news.publish`) separate draft creation from publication.
   - Database restoration is disabled from unapproved execution and requires dual-control governance.
4. **Immediate Session Invalidation**: Updating a staff member's role, suspending an account, or unlinking Google OIDC increments `users.session_version`, immediately invalidating all active PHP sessions for that user on the next request.
5. **Audit Log Redaction**: `AdminControlCenterService::redactMetadata()` strips keys matching `password`, `secret`, `token`, `otp`, `recovery`, `cookie`, `authorization`, `key`, and `hash` before returning audit records to any administrator or auditor.

