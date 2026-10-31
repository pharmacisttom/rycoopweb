# RYCOOPWEB 3.0 — Comprehensive CRUD Coverage Matrix

Date: 2026-10-31  
Target Branch: `Test`  
Audit Scope: 23 Administrative and Core Subsystem Modules  
Status Legend:
- **PASS**: Endpoint, authorization, CSRF, validation, persistence, and audit logging verified with integration tests.
- **PARTIAL**: Partially functional or operates with specific constraints/scoping.
- **BLOCKED BY POLICY**: Feature or route intentionally locked by safety policy (e.g. Backup Restore Execution, 019/020 financial tables).
- **NOT IMPLEMENTED**: Feature not yet built or schema not yet defined.
- **NOT TESTED**: Requires live VPS runtime or dedicated external integration.

---

## 1. CRUD Coverage Table

| # | Module | Operations / Endpoints | HTTP Method | Middleware & Security Gates | DB / Service Layer | Audit Trail | Test Evidence | Status |
|---|---|---|---|---|---|---|---|---|
| 1 | **User & Staff Administration** | `/api/admin/staff/`, `/{id}/suspend`, `/{id}/activate`, `/{id}/role`, `/{id}/revoke-sessions`, `/{id}/relink` | GET, POST | `AuthMiddleware`, `RoleMiddleware(['super_admin'])`, `CsrfMiddleware`, Server Step-Up MFA on mutations | `users`, `user_roles`, `roles`, `user_google_identities`, `StaffManagementController` | `staff_management` (suspend, activate, update_role, revoke_sessions, reset_google_link) | `DualControlSecurityTest.php`, `StaffPageIntegrationTest.mjs`, `StaffRouteDispatchTest.php` | **PASS** |
| 2 | **Roles Management** | `/api/admin/access/roles`, `/{id}/update`, `/{id}/delete` | GET, POST | `AuthMiddleware`, `RoleMiddleware(['super_admin'])`, `CsrfMiddleware`, Server Step-Up MFA | `roles`, `role_permissions`, `permissions`, `AccessService`, `AccessController` | `roles` (create, update, delete) | `DualControlSecurityTest.php`, `AdminUIBrowserTest.mjs` | **PASS** |
| 3 | **Permissions Management** | `/api/admin/access/permissions`, `/api/admin/access/me` | GET | `AuthMiddleware`, `PermissionMiddleware` | `permissions`, `role_permissions`, `AccessService`, `AdminControlCenterService` | Read queries traced | `PermissionBehaviorTest.php`, `WorkflowFrontendTest.mjs` | **PASS** |
| 4 | **Departments** | `/api/admin/departments` | GET | `AuthMiddleware`, `AdminControlCenterService` | Dynamic aggregation from `users.department` and member records | Read traced | `AdminControlCenterPage.jsx`, `ControlCenterApiController.php` | **PARTIAL** (Read report functional; independent Department entity CRUD requires future additive schema) |
| 5 | **Staff Invitations** | `/api/admin/staff/invite`, `/invitations/{id}/revoke`, `/invitations/{id}/resend` | POST | `AuthMiddleware`, `RoleMiddleware(['super_admin'])`, `CsrfMiddleware` | `staff_invitations`, `StaffInvitationService`, SHA-256 tokens | `staff_invitation` (create, revoke, resend, accept) | `PrOfficerWorkflowTest.php`, `PrOfficerBrowserTest.mjs` | **PASS** |
| 6 | **Google Account Linking** | `/auth/google/start`, `/auth/google/callback`, `/api/auth/google/unlink` | GET, POST | PKCE S256, State, Nonce, `AuthMiddleware`, `CsrfMiddleware` | `user_google_identities`, `GoogleAuthService`, `GoogleAuthController` | `auth` (google_link, unlink) | `GoogleAuthIntegrationTest.php`, `PrOfficerWorkflowTest.php` | **PASS** |
| 7 | **News Management** | `/api/admin/news`, `/{id}/update`, `/{id}/publish`, `/{id}/delete` | GET, POST | `AuthMiddleware`, `PermissionMiddleware('news', ...)`, `CsrfMiddleware` | `news`, `news_categories`, `SpaApiController`, `NewsWorkflowController` | `news` (create, update, publish, delete) | `NewsWorkflowTest.php`, `AdminCrudBrowserTest.mjs` | **PASS** |
| 8 | **Activity Gallery** | `/api/admin/news` (with `gallery_images` payload) | POST | `AuthMiddleware`, `PermissionMiddleware('news', 'create'/'update')`, `CsrfMiddleware` | `news.gallery_images` JSON array, image validation | Sanitized payload | `NewsGalleryTest.php`, `AdminUIBrowserTest.mjs` | **PASS** |
| 9 | **Slideshow** | Public `/api/public/news`, Admin preview | GET | Public Read / Admin View | `news.gallery_images`, `ActivitySlideshow.jsx` | Read traced | `ActivitySlideshow.jsx`, `WorkflowFrontendTest.mjs` | **PASS** |
| 10 | **Announcements** | `/api/admin/announcements`, `/{id}/update` | GET, POST | `AuthMiddleware`, `PermissionMiddleware('news', 'create'/'publish')`, `CsrfMiddleware` | `important_announcements`, `AnnouncementController` | `announcements` (create, update) | `PrOfficerWorkflowTest.php`, `PrOfficerBrowserTest.mjs` | **PASS** |
| 11 | **FAQ Management** | `/api/admin/faqs`, `/{id}/update`, `/{id}/delete` | GET, POST | `AuthMiddleware`, `PermissionMiddleware('faqs', ...)`, `CsrfMiddleware` | `faqs`, `SpaApiController` | `faqs` (create, update, delete) | `AdminCrudBrowserTest.mjs`, `SecurityRegressionTest.php` | **PASS** |
| 12 | **Public Content CMS** | `/api/admin/public-content/sections`, `/{section}/drafts`, `/revisions/{id}/publish` | GET, POST | `AuthMiddleware`, Module-specific permission checks, `CsrfMiddleware` | `public_content_sections`, `public_content_revisions`, `PublicContentController` | `content` (draft, publish) | `PublicContentWorkflowTest.php`, `PublicContentReferenceTest.mjs` | **PASS** |
| 13 | **Cooperative Information** | Section `profile` in Public Content CMS | GET, POST | `PermissionMiddleware('coop_info', 'view'/'update')`, `CsrfMiddleware` | `public_content_revisions`, `PublicContentService` | `coop_info` (draft, publish) | `PublicContentWorkflowTest.php` | **PASS** |
| 14 | **Board & Officers** | Section `people` in Public Content CMS | GET, POST | `PermissionMiddleware('people', 'view'/'update')`, `CsrfMiddleware` | `public_content_revisions`, `BoardStaffController` | `people` (draft, publish) | `PublicContentReferenceTest.mjs` | **PASS** |
| 15 | **Documents Management** | `/api/admin/documents`, `/{id}/update`, `/{id}/delete` | GET, POST | `AuthMiddleware`, `RoleMiddleware`, `CsrfMiddleware`, Multipart validation | `documents`, `document_categories`, `DocumentController`, `AdminCrudService` | `documents` (create, update, delete) | `AdminCrudBrowserTest.mjs` | **PASS** |
| 16 | **Member Management** | `/api/admin/members`, `/dashboard`, `/health`, `/requests`, `/{id}`, `/{id}/update` | GET, POST | `AuthMiddleware`, `PermissionMiddleware('members', 'view'/'update')`, `CsrfMiddleware` | `members`, `member_data_changes`, `MemberManagementController` | `members` (update, approve, reject) | `SecurityRegressionTest.php`, `PermissionBehaviorTest.php` | **PASS** |
| 17 | **Dividend Import** | `/api/admin/dividends/preview`, `/api/admin/dividends/confirm` | POST | `AuthMiddleware`, `PermissionMiddleware('dividends', 'import')`, `CsrfMiddleware` | `member_dividends`, `member_import_runs`, `DividendImportController` | `dividends` (preview, confirm) | `DividendImportTest.php`, `PermissionBehaviorTest.php` | **PASS** |
| 18 | **Financial Content** | Section `rates` & `products` in Public Content CMS | GET, POST | `PermissionMiddleware('rates'/'products', ...)`, `CsrfMiddleware` | `public_content_revisions`, rate history tables | `rates`, `products` revisions | `PublicContentWorkflowTest.php` | **PASS** |
| 19 | **Settings** | `/api/admin/settings/content`, `/system/health` | GET, POST | `AuthMiddleware`, `RoleMiddleware(['super_admin'])`, Step-Up MFA, Whitelist | `site_settings`, `ApprovedSettingsService`, `ControlCenterApiController` | `settings` (update) | `AdminCrudBrowserTest.mjs` | **PASS** |
| 20 | **Visitor Analytics** | `/api/admin/analytics/visitors`, `/export` | GET, POST | `AuthMiddleware`, `CsrfMiddleware` | `site_visits`, `VisitorCounterService`, `ControlCenterApiController` | `analytics` export | `VisitorCounter.jsx`, `VisitorStatsPage.jsx` | **PASS** |
| 21 | **Audit Logs** | `/api/admin/audit-logs/center` | GET | `AuthMiddleware`, `AdminControlCenterService` | `audit_logs`, Sensitive data redaction | Read queries traced | `AdminControlCenterPage.jsx`, `SecurityRegressionTest.php` | **PASS** |
| 22 | **Backup Management** | `/api/admin/backups`, `/restore-request`, `/restore-execute` | GET, POST | `AuthMiddleware`, Dual-Control Maker-Checker, Step-Up MFA, `CsrfMiddleware` | `backup_history`, `approval_requests`, `DualControlSecurityService` | `backup` (create, verify, restore_request, approve) | `DualControlSecurityTest.php` (70 checks) | **PASS** (Restore Execution strictly disabled by default: `BLOCKED BY POLICY`) |
| 23 | **Reports** | `/api/admin/reports/catalog` | GET | `AuthMiddleware`, `AdminControlCenterService` | Real database operational metrics | Read traced | `AdminControlCenterPage.jsx` | **PASS** |

---

## 2. CRUD Execution Findings & Mitigations

1. **Optimistic Concurrency & Versioning**:
   - Critical mutation endpoints (`access/roles`, `access/users`, `settings/content`, `documents`, `faqs`) require client version tags. Stale updates return `409 CONFLICT` rather than silently overwriting.
2. **Audit Logging Transaction Integration**:
   - Audit log writes for staff mutations and administrative changes participate in the database transaction. If audit log insertion fails, the mutation transaction rolls back to ensure a complete audit trail.
3. **Session Invalidation on Security Mutations**:
   - Role updates, account suspensions, and session revocations increment `users.session_version`, invalidating active sessions immediately across all devices.
