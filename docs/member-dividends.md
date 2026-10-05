# Member dividend access

For local development, keep MySQL running and use `npm run dev`. Vite automatically starts PHP on `127.0.0.1:8087`, proxies API/auth requests to it and uses writable project-local sessions. This does not change production. Set `PHP_BINARY` if PHP is not on PATH. Set `VITE_PHP_BACKEND` in `.env.local` to use an existing PHP backend instead. Restart Vite after updating this configuration.

Entry: `/member/login`; report: `/member/dividends`. The navbar links to the report. The report API uses the authenticated PHP user and member role, never a supplied member ID. It returns only that member's records and sends no-store headers. National IDs and bank accounts are not returned.

New imported accounts use the national ID as username and the exact member number (including leading zeros, e.g. `00025`) as initial password, stored as a password hash. Members can change their password from the report. Annual imports preserve existing passwords. Existing suspended members are not reactivated. The initial member-number password should be changed by members. A one-time bulk reset explicitly authorized by the owner uses `php bin/reset-member-passwords.php --apply`; it excludes staff accounts and does not change dividend data.

## Setup

1. Configure the PHP database in `.env` and run the existing migrations (`php bin/console db:migrate`). Ensure the member role exists.
2. Set `FEATURE_MEMBER_LOGIN=true` if an existing environment explicitly disables it. The other member portal features may remain disabled; this report uses its own authenticated route and no placeholder financial data.
3. Run `scripts/prepare-dividends.py` with both supplied workbook paths using Python with openpyxl installed. It detects columns by heading, retains monetary values to two decimal places, and reconciles income, expenses and net amounts.
4. Review `storage/private/dividends-review.json`. Conflicting national IDs are excluded across both years. Fix source identities before regenerating. Do not guess or combine different people's accounts.
5. Run `php bin/import-dividends.php`. Import is transactional and repeatable per member/year; it preserves passwords. Identity conflicts abort and roll back the import.
6. Run `npm run build` and deploy with the existing PHP/MySQL hosting instructions. Verify login, password changes, session expiry and report access with two separate member accounts before release.

Private JSON files are ignored by Git. Store private data outside the web document root in production; the repository-root Apache rules also deny direct access to storage/private and backend source directories. Never upload the source workbooks into public assets. No bank account numbers are copied into import data. `เงินที่ได้รับ` is shown as a source report value, not proof of a completed bank transfer.

For the supplied files, preparation produced 4,855 eligible records (2,429 in 2567 and 2,426 in 2568), covering 2,508 identities. Four records were held for identity review.

## Standard annual imports

Administrators with the `super_admin` role can open `/admin/dividends/import` from the dashboard. Download `public/templates/member-dividends-template.xlsx` or the CSV UTF-8 version. Replace the synthetic example row, keep the header names, and enter the Buddhist year in `ปีบัญชี`. Supported years are 2400–2800. Keep identity fields as text. Copy the sample Excel row to extend formulas, then save in Excel before upload so cached formula results are available.

Upload a file, select its year, review the validated record count and net total, then confirm. Files with missing headers, duplicate identities, nonnumeric money, formula errors or unreconciled totals are rejected before writes. Confirmed imports run in a transaction; member identity conflicts roll back all writes. Reimporting a member/year updates that year's record without creating duplicates or resetting passwords. Preview expires after 30 minutes and is bound to the administrator's session. Maximum upload size is 5 MB and 20,000 rows.

Legacy files without `ปีบัญชี` use the year selected on the upload page. The Python preparation tool also accepts `--year 2566` or detects a Buddhist year in the filename, and finds the header row automatically. Both supplied original workbooks still contain an unresolved duplicate national ID, so use the prepared private JSON for their initial import; correct the originals before uploading them through the strict web importer.

Local MySQL import was completed and all 4,855 net amounts were checked against the prepared source. Initial password verification passed. Private source data and local database contents are not part of the GitHub deployment; production must receive data separately through the authorized import workflow.

For CLI imports, use `php bin/import-dividends.php path/to/file.xlsx 2566` (or CSV) to share the same validation and transaction rules. `php bin/import-dividends.php` uses the prepared private JSON. HTTP checks with two local member accounts passed for login, per-member ownership, denial of administrator import access, and logout. The local PHP test server required `SESSION_SAVE_PATH` to point to a writable `storage/sessions` directory.

## Membership administration

Run migrations `014_member_admin_history.sql` and `015_member_admin_role.sql` with `php bin/console db:migrate` before deploying this feature. `/admin/members/dashboard` shows live counts and yearly monetary totals; `/admin/members` provides search, filtering, pagination and masked identity numbers; `/admin/members/:id` edits the current profile and existing dividend years. All writes require CSRF, an explanation and a current version. Profile changes synchronise login usernames and suspension status while preserving passwords. Dividend totals use integer cents. Change history stores actor, reason, timestamp and before/after values without passwords.

The dedicated `member_admin` role can access these screens and annual imports. It is denied access to CMS, the main users API and reports intended for a logged-in member. `super_admin` retains its existing access. A local `memberadmin` account was created at the owner's request without modifying the main administrator. Credentials remain private and must not be copied into Git or reused on production. On another host set `MEMBER_ADMIN_PASSWORD` to a unique password of at least 16 characters and run `php bin/member-admin.php memberadmin`; `--reset` must be explicit and only modifies accounts whose sole role is `member_admin`. Do not run the full seeder to provision this account.

Import preview compares current records, reports new/changed/unchanged totals and displays exact duplicate identities and source rows. An optional checkbox updates existing names/departments; by default administrator-corrected profiles are preserved. Preview snapshots cover profile and financial data, so concurrent edits invalidate confirmation. Unchanged amounts do not rewrite a report. Successful batch history is stored transactionally; failed batches leave no partially created accounts or history.

Tests: `php tests/MemberAdministrationTest.php` creates a disposable database and checks conflicts, rollback, precise totals, preserved credentials, stale edits and retained years. It needs local schema tables and permission to create a test database. `tests/MemberAdministrationHttpTest.py` uses `MEMBER_ADMIN_USER`, `MEMBER_ADMIN_PASSWORD` and optional `MEMBER_TEST_URL`; it only reads data and uploads previews, never confirms or modifies member records. Repeated runs may hit the existing login rate limit. Local HTTP checks passed after fixing PHP's upload temporary directory. Browser visual verification and VPS deployment remain separate work. Use `docs/member-admin-handoff-prompt.md` to hand off the current work.
