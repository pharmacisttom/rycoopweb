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

Passed: AuthBoundarySessionTest (23 checks), SessionMfaStabilityTest,
MfaWorkflowTest, MfaRateLimitTest, PermissionBehaviorTest, RequestSecurityTest,
SecurityRegressionTest, PHP syntax checks for Auth/Session, and Vite build.
Build reports the existing large-chunk warning.

FAILED: WorkflowRoutesTest at auth guard
`/api/admin/news/{id}/gallery/{filename}`. Its route registration and test were
not changed by this patch. This remains a release gate requiring investigation.
MemberPdpaGateTest.php was not present, so that requested suite was not run.

The new test uses synthetic SQLite identities and a private temporary session
directory, with concurrent child processes. It covers login/MFA predecessors,
periodic forwarding, logout, revocation, idle/600-second MFA expiry, fixation,
CSRF rotation and compatible process restart. It does not prove live cutover UAT.

## Remaining risks and VPS verification

Periodic handoff may briefly deny a concurrent request before its forwarding
record is installed. Tests allow safe denial; they do not prove linearizable
logout against every possible in-flight writer. Production concurrency UAT is
required. Never log session IDs or export/delete existing shared session files.

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
3. Resolve the failing route test and pass CI plus isolated authentication tests.
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
