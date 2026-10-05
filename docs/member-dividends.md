# Member dividend access

For local development, keep MySQL running and use `npm run dev`. Vite automatically starts PHP on `127.0.0.1:8087`, proxies API/auth requests to it and uses writable project-local sessions. This does not change production. Set `PHP_BINARY` if PHP is not on PATH. Set `VITE_PHP_BACKEND` in `.env.local` to use an existing PHP backend instead. Restart Vite after updating this configuration.

Entry: `/member/login`; report: `/member/dividends`. The navbar links to the report. The report API uses the authenticated PHP user and member role, never a supplied member ID. It returns only that member's records and sends no-store headers. National IDs and bank accounts are not returned.

New imported accounts use the national ID as username and `สมาชิกตัวอย่าง` as initial password (stored as a password hash). Members can change their password from the report. Existing accounts and passwords are preserved. Existing suspended members are not reactivated. The shared initial password must be changed by members; it is not strong identity verification for a public financial service.

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
