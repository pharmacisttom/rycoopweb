# Admin CRUD recovery — initial audit and release evidence

## Baseline and priorities (before application changes)

2026-10-29: remote Test = `09ee6d04fcd761fd50a53cc39b591f7b73e33ad9`. GitHub queries confirm #10, #11 and #12 are OPEN; feature `f9eaf8e89cb0a910a90ea52c28579611c8e39b4c` is not an ancestor of Test. Development branch `codex/admin-crud-functional-recovery` includes #10–#12 as explicit unmerged dependencies, without merging Test or changing deployed files.

Live TEST frontend/backend revisions and schema: **Not Tested**. No VPS connection has been established. Read-only commands are provided in the release runbook; source/CI results do not prove VPS readiness.

## Root causes found before repair

| Severity | Finding | Source evidence / consequence |
|---|---|---|
| High | Staff recovery is not on Test yet | Test excludes #10–#12; original Router group index and frontend error state need those dependencies. This does not prove which release is on VPS. |
| High | Two competing staff creation workflows | AdminAccessPage creates password accounts, whereas PrOfficerInvitations only supports a fixed PR role. Google-only PR passwords cannot be used to finish enrollment. Provide a clear general invitation path for approved staff roles. |
| High | Access mutations claim audited success even if audit storage fails | AccessService transactions call non-strict AuditService::log; failure is swallowed. Required audit must participate in transaction. |
| High | Account security edits do not invalidate sessions | AccessService.saveAccount changes role/email/status without session_version change, unlike StaffManagementController. |
| High | Role/status endpoint lacks version and atomic audit | StaffManagementController.updateRole mutates before best-effort audit, and lacks a client version. Preserve MFA but align with canonical AccessService rather than duplicate updates. |
| Medium | SQL errors reported as validation | AccessController maps every PDOException to HTTP 422, even outages. Stale versions also become 422 rather than 409. |
| High | Legacy CMS controls are browser-only | AdminDashboardPage seeds announcements from constants and hero/popup state from localStorage; these do not persist to shared DB. Never treat these controls as functional backend CRUD. |
| Medium | Departments page is a report, not an entity editor | ControlCenterApiController.departments only GET; summaries derive from free-text users.department. No Department CRUD/schema can be assumed. |
| Medium | Settings route shows health/profile, not the existing settings editor | React /admin/settings masks legacy SettingController. Approved settings allowlist and API wiring require review. |
| Medium | Documents have create/list/delete but no update method | DocumentController has no update; React does not expose a document CRUD page. File validation/storage and update need dedicated repair. |
| Medium | FAQ only reads public active list and lacks concurrency | ContentAdminPage reads public/faqs; update/delete controller operations have no version or atomic audit. |

## Initial CRUD coverage matrix

Legend: Working = actual execution proved in named environment; Broken = source-confirmed defect; Missing = no implementation found; Permission Denied = intended denial; Not Tested = not executed against relevant runtime. A registered endpoint is never marked Working by inspection alone.

| Module | Frontend / backend / storage | Initial state |
|---|---|---|
| Users / Staff | /admin/users, /admin/access → StaffManagementController, AccessController, StaffInvitationService → users/user_roles/staff_invitations/Google identities | Broken on Test without dependencies; PR-only invitation UI; MySQL CRUD Not Tested |
| Roles / Permissions | /admin/access → /api/admin/access/roles → AccessService → roles/permissions/role_permissions | Audit/409 handling Broken; preserve unsupported mappings; MySQL Not Tested |
| Departments | /admin/departments → GET /api/admin/departments → users.department/member affiliations | Read report exists; entity CRUD Missing; schema decision required |
| News | /admin/content?tab=news → /api/admin/news → NewsWorkflowService → news/news_categories | Prior SQLite/browser checks Working; current MySQL CRUD Not Tested |
| Gallery / Slideshow | same news editor → NewsGalleryService → news.gallery_images + uploads | Prior isolated image/rollback checks Working; shared VPS uploads Not Tested |
| Announcements | PR workspace → /api/admin/announcements → AnnouncementController → important_announcements | On #12 only; no optimistic lock; audit atomicity Broken; MySQL Not Tested |
| FAQ | /admin/content?tab=faqs → public read/admin mutations → SpaApiController → faqs | No version/atomic audit; public-only read hides disabled items; MySQL Not Tested |
| Public CMS | /admin/public-content → public-content APIs → PublicContentService → revisions/records | Draft/review/publish model; prior SQLite Working; MySQL Not Tested |
| Cooperative info | profile section of Public CMS | Same workflow; MySQL Not Tested |
| People / Officers | people section of Public CMS | Same workflow; MySQL Not Tested |
| Documents | legacy /admin/documents → DocumentController → documents/document_categories/uploads | Update and React editor Missing; MySQL Not Tested |
| Approved settings | React /admin/settings vs legacy SettingController → site_settings | UI/API mismatch; allowlist/version needed; key/env editing prohibited |
| Member operations | /admin/members, import → MemberManagementController/DividendImportService | Auth+permissions+CSRF; PR Permission Denied; disposable MySQL CRUD Not Tested |
| Financial content | rates/products sections in Public CMS; legacy direct rate update | Scoped publication exists; financial transaction modules disabled/019/020 ON HOLD; MySQL Not Tested |

## Database compatibility

Local PHP runtime is 8.2; Docker/mysql CLIs are unavailable in PATH. Do not substitute XAMPP/MariaDB for MySQL 8 certification. New tests must target GitHub Actions' disposable MySQL 8 service using the existing fresh CI schema, and refuse rycoopweb_test/Production before connecting. Compare information_schema with expected fields, enum/FK/index requirements. Existing migrations are inspected, not executed on live databases; no schema fix is applied automatically.

API inventory: `docs/audit/CRUD_ENDPOINTS.md`, regenerated by the read-only source script. Execution results and remaining gaps will be appended after repairs.
