# Admin CRUD recovery — TEST release and rollback

No command here authorizes deployment, migration, live account changes or symlink changes. Review the source audit first: `docs/audit/CRUD_RECOVERY.md` and `CRUD_ENDPOINTS.md`. This branch includes unmerged #10–#12 as dependencies.

## Read-only VPS evidence for the administrator

Set the backend/frontend directories to the actual active TEST paths after inspecting the Nginx configuration. Do not assume old documentation paths are current. Run as a read-only operator; do not send .env, cookies, passwords or full nginx configuration output to chat.

```bash
# Substitute known TEST paths; these commands do not change them.
readlink -f /var/www/rycoopweb/test-v2
readlink -f /var/www/rycoopweb/test-v2-static
git -C /var/www/rycoopweb/test-v2 rev-parse HEAD
git -C /var/www/rycoopweb/test-v2 status --short
sha256sum /var/www/rycoopweb/test-v2-static/index.html
php -v | head -n 1
```

If the active release has no .git, obtain its immutable release manifest/CI artifact SHA instead. Match the JS/CSS references in index.html to the build artifact for the approved commit. The local source cannot prove the deployed SHA.

Using an already-configured read-only MySQL login profile, request metadata only (no user/member rows or password columns):

```sql
SELECT VERSION(), DATABASE();
SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
('users','roles','permissions','role_permissions','staff_invitations',
 'user_google_identities','user_mfa_recovery_codes','faqs','documents',
 'document_categories','important_announcements','site_settings','public_content_revisions')
ORDER BY TABLE_NAME,ORDINAL_POSITION;
SELECT TABLE_NAME,INDEX_NAME,COLUMN_NAME,NON_UNIQUE
FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX;
```

Do not copy a login/password into these commands or logs. Live schema mismatches require a separate additive migration proposal and approval, never an automatic ALTER or rerun of 025. 019/020 remain ON HOLD.

## Validation gates

1. Review dependencies #10/#11/#12 and recovery diff. Confirm TEST-only scope and separate database; no Production changes.
2. Local syntax/build/security/browser checks plus the new disposable MySQL 8 route/controller suite must pass. `AdminCrudMySqlTest.php` refuses before connecting unless GitHub Actions, APP_ENV=testing, explicit disposable flag and exact disposable CI database agree. It validates MySQL 8, actual table columns, real MFA/Step-Up, constraints, optimistic conflicts, two-process row-lock race and transaction/audit rollback. It never uses rycoopweb_test or Production. Temporary fault-injection trigger is restricted to that disposable database and removed in finally.
3. CI uses committed historical migration schema in a fresh MySQL container. This is not proof that the live TEST schema matches. No new migration is included or executed against live databases.
4. Prepare a new immutable backend/frontend release with composer install and npm ci/build from the approved commit. Do not edit active TEST files, .env, keys or live symlinks during this task.
5. Present exact SHA, artifact, dependency order, metadata differences and rollback release for explicit approval before merge or release switch.

## Authenticated TEST UAT (after approval only)

- Dedicated synthetic Super Admin completes Google login and MFA. GET both /api/admin/staff URL forms; distinguish 401/403/404/409/422/500 from a true empty result.
- Create invitation with approved role/department; duplicate account and pending email fail. Super Admin invitation additionally requires fresh Step-Up. Link displayed once, status/expiry visible; renewal invalidates old link, revoked/accepted cannot be replayed. Actual Google callback and email ownership still require interactive UAT.
- Accept invitation as the synthetic intended Google user, enroll TOTP and store recovery codes privately. New staff appears in directory. No existing account role/password is overwritten.
- Update role/status/department with fresh Step-Up and current version; second-tab stale update returns 409, role/status change revokes existing sessions; self/last-admin safeguards remain.
- Create/edit/delete an unused custom role; unsupported permission mappings remain unchanged. Do not modify real staff roles.
- News: create draft, upload/reorder real synthetic images, submit, publish through the existing workflow, update with stale version denied, soft-delete with current version. Check image HTTP 200, MIME and shared uploads after release switch. Do not mark file UAT complete from a fixture alone.
- FAQ: create, update, disable, see inactive entry in admin list and reactivate; public list remains active-only. Validate malformed payload, stale update and CSRF failures.
- Announcements: draft/update/publish/archive with current version and permissions; invalid date/link rejected. Recent activity uses real records.
- Documents: upload synthetic PDF, draft/edit metadata/replace file/activate/soft-delete; real file required for active status. Confirm MIME/extension/size validation, no arbitrary path acceptance, DB/filesystem rollback and shared storage permissions. CLI metadata tests alone do not certify multipart uploads.
- Approved settings: only whitelisted public organization/contact fields; fresh Step-Up/current version; attempts to set APP_KEY, feature flags or other keys rejected. No secret values returned.
- Public CMS profile/people/rates/products: existing versioned draft/review/publish workflow with current version, source URL/effective date and authorized roles. Do not write financial account balances.
- Member operations: verify authorized search/read/edit against approved synthetic records only. PR must be denied member/finance/account/restore APIs and generic financial dashboard. Portal reactivation/PDPA/Tomvis is separate work and remains gated.
- Departments currently summarize free-text user/member data, not an independent entity. Edit a staff department through staff/account editor; do not claim create/delete Department entity support.
- Test 390/768/1440 widths, keyboard/modal focus, light/dark, search/pagination, save-refresh and API failure state. No periodic refresh that discards unsaved forms.

## Rollback

After separate authorization, restore the previous matching immutable backend/frontend release together. No down migration, account reset, key rotation or financial data restore. Legitimate UAT writes are not undone by a code rollback: review synthetic invitations/content separately. Preserve audit history. Keep Backup Restore Execution disabled and member flags unchanged. If old release removes new security checks, keep TEST behind its gateway until a reviewed build is ready.

## Remaining design gaps

Independent Department entity CRUD needs an approved data model/additive schema; no guessed table is created. Browser-only Hero/Popup prototypes are retained as legacy source but no longer presented as shared database saves. A shared Hero/Popup workflow requires separate scope/permission/API UAT. Live schema, real OIDC callback, real multipart upload/shared storage and authenticated VPS UAT remain unverified until executed and recorded.
