# Tomvis member integration — audit before authentication changes

Date: 2026-10-29. Branch: `codex/tomvis-member-auth-integration`, based on remote Test `09ee6d04fcd761fd50a53cc39b591f7b73e33ad9`.

## Evidence and limits

- Public read-only HTTP: `https://rayongcoop.tomvisolution.tech/` returned 200 text/html. Its HTML references `/app/assets/index-BqriTz-o.js`, `/app/assets/index-CdW7mtv8.css` and the known logo asset. Similar asset layout is not proof of deployed PHP revision, database compatibility or identity ownership.
- `https://test.rayongcoop.org/` returned 401 at the gateway. No Basic Auth bypass, login attempt, account lookup, financial read or security scan was performed.
- PRs #10/#11/#12 remain OPEN; Test does not contain PR Officer commit `f9eaf8e`. PR #12 and CRUD recovery are separate dependencies requiring review, not automatically merged into this branch.
- Local `D:/rycoopmember` is repository `pharmacisttom/rycoopmember`, a Next.js/Prisma project. Its member-auth service delegates to `LegacyApiClient`, which calls the configured PHP backend `/csrf-token`, `/login`, `/api/auth/me`, `/api/member/profile`. The sync adapter file defines a type and an unconfigured-adapter exception, not an implemented Tomvis importer. No environment file or private database was read. Its association with the live Tomvis deployment remains unconfirmed.
- No authoritative Tomvis source deployment revision, schema, password storage details, API contract, contact verification policy, OTP provider or approved member Privacy Notice was supplied. These are explicit implementation gates. No migration may assume Tomvis table/column names.

## Authentication and member source audit

| Area | Confirmed local source | Assessment |
|---|---|---|
| PHP password login | AuthController uses password_verify, matches username/email/member number/ID via users/members | Existing central authority; changing its hashes could break legacy login. Live Tomvis format Unknown. |
| Staff auth | GoogleAuthController, MfaController, session_version and AuthMiddleware | Preserve separate staff path and mandatory PR policy from #12; do not replace with member activation. |
| Next member login | Delegates to configured PHP backend and validates role=member plus member.user_id=user.id | Another client of central PHP authentication; not proof of a separate legacy account store. |
| Next Prisma model | CoopMember.userId is nullable String; organization/memberNo unique; citizen ID encrypted and lookup hash fields | Does not prove live schema. Do not Prisma migrate/db push/reset or treat Prisma IDs as PHP numeric IDs. |
| Existing PHP member binding | MemberPortalService.getMemberByUserId filters members.user_id; DividendController filters authenticated user and active member | Candidate reuse, but duplicate bindings and inactive membership need database checks. |
| Feature flags | React VITE_FEATURE_MEMBER_PORTAL defaults off; PHP portal/eservice/financial defaults off | PHP member_login defaults **true**, providing dividend login independently. Do not change defaults or equate login flag with portal approval. |
| Identity normalization | MemberAdminService pads member_no to 5 but permits 1–20 digits | Not strict 5-digit compatibility; never truncate longer legacy numbers. Reconcile exceptions. |
| Member financial dashboard | MemberPortalService has hardcoded share fallback and estimated deduction values | **Critical release blocker**: missing ledger rows must show unavailable, never invented balances. |
| Login telemetry | AuthController logs submitted username as login_logs.email and in auth logs | **High privacy blocker** for national-ID usernames: remove/mask/HMAC before member activation; existing logs require authorized retention review. |
| Privacy | Existing anonymous cookie consent and public privacy routes | Not a versioned, authenticated member notice acknowledgment; cannot reuse cookie consent as member acknowledgment. |
| Activation / recovery | No audited legacy-activation/verified-contact OTP pipeline identified | Missing; identity pair alone cannot reveal financial data or authorize password reset. |
| Financial source | Local PHP names shares/deposits/loans/dividends; live Tomvis tables unknown | Keep read-only integration unconfigured until source contract and reconciliation are approved. |

## Required information from the system owner

1. Confirm which repository/release runs rayongcoop.tomvisolution.tech and whether D:/rycoopmember is involved. Provide read-only source access or a sanitized source archive; no .env or credentials in chat.
2. Schema-only export/metadata: tables, column types, indexes, FKs, enum values, character set/collation and stable identifiers for accounts/members/financial data. Do not send real member rows or password hashes.
3. Password verifier/algorithm and legacy initialization/forced-change rules, not passwords. Confirm whether Tomvis and RYCOOP currently share users/members or use separate databases.
4. Supported read-only API/auth/sync contract, version, TLS/authentication requirements, rate limits and financial as-of/reconciliation identifiers.
5. Verified phone/email ownership policy and approved OTP provider or officer identity-verification workflow. No sending to a number supplied during activation without verification.
6. Approved Privacy Notice version, content, effective date, full-document URL, controller/DPO contact and retention policy; approved optional consent purposes and versions.
7. Authorized TEST-only synthetic identities, pilot approver and expected rollout scope. Do not use live members for automated authentication tests.

Status: **audit/design only; implementation blocked on authoritative legacy evidence and approved identity/notice inputs**. No account, balance, key, .env, feature flag, live symlink or schema was changed. No financial migration executed. 019/020 remain ON HOLD, 025 must not rerun, Backup Restore Execution remains disabled.
