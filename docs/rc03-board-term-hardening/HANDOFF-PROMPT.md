Continue RC03 Board Term Workflow Hardening for pharmacisttom/rycoopweb from branch codex/rc03-board-term-hardening.

Read docs/rc03-board-term-hardening/QA.md, TEST-RUNBOOK.md and evidence.txt first. Target is TEST ONLY, database rycoopweb_test. Production, member, dividend and financial data are out of scope. Do not merge, deploy, run operational imports or approve/publish terms without separate operator authorization. Preserve all existing term 38 records and custom images and keep term 39 draft separate.

Implementation and isolated SQLite/browser/build checks are complete. Verify the branch commit and unified diff. Keep unrelated dirty workspace changes excluded. Approval is a required audit fingerprint while the DB status stays draft. Published BoardTerm data owns the complete board graph ahead of Public Content revisions; legacy/revision fallback remains until a current published term exists. Scheduling, destructive force reconciliation and unpreviewed full copy are disabled.

Remaining release gates: execute the documented two-session MySQL locking/stale/duplicate tests on an explicitly authorized TEST target; verify live MFA/RBAC/CSRF behavior, public GET zero writes, original term 38 data/photos/group links, term 39 draft isolation, and Media/Document HTTP behavior including a real approved file download. Record actual evidence and failures. Do not label the release production-ready from SQLite or mocked browser results.

Report the verified commit, test evidence, remaining risks and deployment/rollback decision. Do not merge to main or deploy automatically.
