# RC16.35 authentication boundary hotfix

Status: source preparation only; **NO-GO for Production**.

Base: `f9a1d02b1ccb70a7384b34ca18b7b469cc37aaa5` (RC16.31).
Branch: `codex/rc16-35-auth-boundary`.

## Cause and changes

Login and successful MFA previously used periodic rotation, allowing a lower-trust
session ID to forward into authenticated state. They now use dedicated native
regeneration with deletion of the predecessor ID. Login clears prior identity;
MFA preserves the pending identity but clears CSRF and step-up grants.

Periodic rotation retains a maximum 60-second, four-hop forwarding window. Retired
records contain forwarding metadata only. Missing targets, expired records and
invalid links fail closed. Database session-version checks remain unchanged.
No database migrations, production settings or shared session storage were changed.

## Local evidence

Passed: AuthBoundarySessionTest (65 checks), SessionMfaStabilityTest,
MfaWorkflowTest, MfaRateLimitTest, PermissionBehaviorTest, RequestSecurityTest,
SecurityRegressionTest, PHP syntax checks for Auth/Session, and Vite build.
Build reports the existing large-chunk warning.

WorkflowRoutesTest now passes 457 checks after adding route-level authentication
and news.view permission enforcement to the gallery endpoint. Controller checks
remain in place. NewsGalleryAccessTest passes 7 checks, including actual WebP
bytes for authorized synthetic users and denial for guests, pending enrollment,
unprivileged users and images outside article membership.

The complete 22-suite isolated PHP workflow/security batch passes locally.
MemberPdpaGateTest.php is not present; no claim is made for that nonexistent suite.

## CI failure diagnosis (previous SHA 27f7cfd)

Quality Gate run 44876343235 failed as follows:
- PHP 8.3 job 136761761072 and PHP 8.5 job 136761761298: WorkflowRoutesTest
  stopped at the unguarded gallery route. No interpreter-specific failure at this step.
- React job 136761761339: MfaUiRedesignTest line 122 incorrectly expected every
  HTTP 401 to mean challenge expiry; the build step was skipped, not a Vite failure.
  The test now distinguishes AUTH_REQUIRED from MFA_CHALLENGE_EXPIRED, preserving
  both security assertions and the RC16.31 server contract.
- Browser job 136761761726: an exact text locator matched both the persistent
  save-status message and SweetAlert message. The locator now targets the
  module's accessible status element; the saved gallery payload assertion remains.
No required CI check was removed or made optional. Fresh CI evidence must be tied
to the final Git SHA, not the previous failed run.

The next run at 9fef700 revealed two later gates previously skipped:
- Real MySQL CRUD on PHP 8.3 reached Auth::login after session_write_close();
  Session::$started incorrectly claimed a native session was still active.
  Session::start now checks native session_status, and logout reopens closed
  persisted sessions before destroying them. Both paths have isolated regressions.
- Layout/Board static QA expected direct e.target.value use. The existing async
  confirmation handler correctly captures nextVal before awaiting. The contract
  test now checks capture, local selection and URL query update explicitly.

At 5c1477e the PHP 8.3/8.5 jobs passed, including disposable MySQL CRUD/concurrency,
and React, lint, layout and media checks passed. Browser QA then exposed the
stored-content test's missing SweetAlert acknowledgement: the success modal
correctly hid the underlying table from accessible role queries. That test now
acknowledges success, explicitly cancels and confirms document removal, and still
asserts multipart/version/CSRF payloads, settings Step-Up, FAQ reactivation and
failure states. All four browser suites pass locally: AdminUIBrowserTest (195
responsive screenshots), StaffPageIntegrationTest (real isolated PHP sessions),
PrOfficerBrowserTest and AdminCrudBrowserTest. Fresh final-SHA CI remains the
required evidence before closing the source acceptance gate.

The new test uses synthetic SQLite identities and a private temporary session
directory, with concurrent child processes. It covers login/MFA predecessors,
periodic forwarding, logout, revocation, idle/600-second MFA expiry, fixation,
CSRF rotation and compatible process restart. It does not prove live cutover UAT.

## Remaining risks and VPS verification

Periodic handoff now writes an identity-free in-progress marker. Readers wait up
to 20 attempts of 25ms; timeout cancels the predecessor and the rotator discards
its target instead of publishing orphaned authentication. Tests force logout
inside this window, including timeout cancellation. Other tests queue logout
behind a locked rotating session and revoke the shared synthetic user's version
while rotation is pending (five repetitions each). All returned IDs are checked
for denied authentication after completion. Native file-session locking and a
test-only SessionHandler barrier are used; this is not a proof for arbitrary
custom Production save handlers. Production concurrency UAT is still required.
Never log session IDs or export/delete existing shared session files.

Local worktree was clean before this patch and the fetched release branch matched
the base above. The reported dirty VPS candidate has NOT been inspected. Preserve
its application diff and untracked inventory securely, exclude secrets, and
reconcile before staging a clean immutable release. Never reset the candidate.

Effective PHP-FPM configuration remains unverified. Identify the actual service,
pool and loaded INI files; inspect effective session save handler/path, lifetime,
gc_maxlifetime, gc_probability/divisor, pool overrides, .user.ini and scheduled
session cleanup. CLI configuration alone is not evidence of FPM configuration.
Keep the shared Production session directory and encryption keys unchanged.

## Release and rollback procedure

1. Resolve the actual active RC16.6 release directory and retain it as rollback.
2. Preserve/reconcile the dirty candidate and identify the exact new Git SHA.
3. Confirm fresh CI plus isolated authentication tests pass for the approved SHA.
4. Verify backup/restore evidence and review compatibility; no migrations needed.
5. Stage a separate immutable directory from the approved SHA; build assets there.
6. Validate PHP syntax and the actual Nginx/PHP-FPM configuration without exposing
   environment secrets. Do not execute historical deployment scripts blindly.
7. Record explicit GO approval before any controlled Production cutover.
8. Verify existing authorized admin-session persistence, MFA, rejected unauthorized
   requests, member/public APIs and browser UAT. Record the real release path and
   acceptance evidence; do not claim completion until these pass.

Rollback switches the release pointer to the recorded previous immutable release,
then validates configuration and performs smoke checks. No database rollback or
session-file deletion is part of this patch. The old release retains the reported
authentication weakness, so rollback is an availability measure and requires a
security decision. No Production cutover or rollback was executed locally.
