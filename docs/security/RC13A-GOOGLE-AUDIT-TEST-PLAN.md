# RYCOOPWEB RC13A — Google Authentication Audit (TEST-first)

Baseline: `88ef10722c6b414b1720ecd7cd8e8dceafb12c60`

## Scope
- Emit `google_auth/identity_verified` **once per** `handleUserSessionAndMfa` invocation after Google OIDC has been verified and linked staff identity authorized.
- Emit audit via existing `AuditService` (best effort); stage is `pre_mfa`, **not** completed staff login.
- Expose distinct rolling 7-day counts in the Security Overview API: `google_identity_success_7d`, `local_credential_success_7d`, `mfa_verified_events_7d`.
- No changes to Auth::login, MFA verification, session regeneration, route permissions, DB schema, member portal, consent, or production deployment.
- No logging of OAuth tokens, Google sub, email, passwords, TOTP or recovery codes in the new event payload.

## Safety and limits
- Best-effort audit does **not** guarantee durable delivery under database failure. Fail-closed privileged-session changes are explicitly deferred to RC13B pending integration tests and rollback design.
- `login_logs.status=success` is credential/Google identity verification, not proof of completed privileged login.
- `mfa_verified_events_7d` is an event count, not a distinct-user or completed-session count.
- Google invitation/bootstrap paths may already write another login_logs entry; RC13A adds only one new audit event at the common session handler and does not modify existing count behavior.
- Current Security Center UI does not yet visualize the newly added API keys. Browser UAT required before release sign-off.
- Source contract does not replace functional integration or failure injection tests.

## Read-only validation
```bash
php -l app/Controllers/Admin/GoogleAuthController.php
php -l app/Services/AdminControlCenterService.php
php -l tests/GoogleAuthenticationAuditRc13ContractTest.php
php tests/GoogleAuthenticationAuditRc13ContractTest.php
```

## TEST UAT required before any production promotion
1. Google OIDC authorized linked staff -> one `google_auth/identity_verified` event with stage pre_mfa.
2. Verify user cannot reach protected routes before successful MFA.
3. Successful TOTP -> existing `auth/2fa_verified` event.
4. Failed / replayed TOTP does not open staff session.
5. Unapproved Google identity does not generate `identity_verified`.
6. Force audit write failure on TEST; current best-effort behavior must be understood and monitored.
7. Confirm Security Overview API returns stage-specific metrics; no mixed/added totals.
8. Confirm no sensitive values appear in event JSON, application logs, or response.
9. Smoke public/member and admin routes; verify no regression.

## Rollback
Revert RC13A branch changes and deploy previously verified release only under approved change process. Do not write, delete, or backfill historical audit entries. No production changes were performed by this GitHub patch.
