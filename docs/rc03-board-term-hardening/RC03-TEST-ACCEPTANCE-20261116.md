# RC03 TEST Acceptance — post PR #28

## Verified vs pending
- Baseline candidate commit **4d0a9f946999f63db57d6c6fd199f85934b65b75** (PR #28), with successful parent quality gate #185.
- Added Media/Document route contract test is **static/structural only**. It does not exercise session cookies, files or a live HTTP server.
- Before this follow-up the operator reported SQLite regression PASS on a local isolated staging copy. It does not prove MySQL two-session locking.
- No deployment, operational import, approval, publication or database writes are authorized by this document.

## Read-only TEST preflight

The follow-up test script is at `scripts/rc03-test-readonly-preflight.sh` on **this branch**, which is different from the reviewed PR #28 candidate SHA. Keep **two separate checkouts** so the script can audit the precise release candidate without changing the live TEST app.

```bash
set -Eeuo pipefail
CHECK=/var/tmp/rycoop-rc03-gates-20261116
CAND=/var/tmp/rycoop-rc03-candidate-4d0a9f9
URL=https://github.com/pharmacisttom/rycoopweb.git
SHA=4d0a9f946999f63db57d6c6fd199f85934b65b75
test ! -e "$CHECK" && test ! -e "$CAND" || { echo 'STOP: path already exists'; exit 1; }
git clone --branch chore/rc03-test-acceptance-gates-20261116 --single-branch "$URL" "$CHECK"
git clone "$URL" "$CAND"
git -C "$CAND" checkout --detach "$SHA"
test "$(git -C "$CAND" rev-parse HEAD)" = "$SHA"
RC03_STAGE="$CAND" bash "$CHECK/scripts/rc03-test-readonly-preflight.sh"
```

Run using an operator-approved **read-only MySQL account with SELECT/information_schema access to only `rycoopweb_test`**. This script does not read .env, run migrations/imports, switch PHP-FPM/Nginx or deploy. MySQL connection failure is a stop, not a reason to substitute production credentials.

## Mandatory independent release gates

1. Prepare a consistent TEST-only backup including board tables, audit evidence, original images and TEST application + generated frontend assets. Verify restore procedure separately.
2. Verify current TEST app and static roots are not overwritten; check PHP-FPM TEST pool, port, Nginx host and candidate path mappings.
3. **Live HTTP:** authenticate with fully verified MFA and varying roles; anonymous/pending-MFA/member/no-documents.view must not download private documents. Authorized staff must preview/download a real *approved test file* with correct bytes/headers and no-store; test invalid ID, deleted row, unsupported format and traversal. Never include auth cookies or document content in artifacts.
4. **Media HTTP:** valid approved image, missing image, invalid folder/hash, unexpected MIME and symlink escapes; verify no unexpected writes.
5. **Board:** source term 38 records, custom photos, groups and membership links remain unchanged; target 39 stays draft and separate. Prior TEST checks found all four board tables empty, so actual preservation cannot yet be claimed.
6. **MySQL concurrency:** two independently authenticated browser sessions using an explicitly authorized disposable TEST fixture or clone, test locking, stale 409, duplicate copy and required audit rollback. Approval/publication concurrency separately requires operator authorization, not granted merely by opening this PR.
7. **Public GET:** confirm no database writes or automatic scheduled publication using appropriate isolated TEST evidence.

## Rollback constraint
Old BoardTermService on TEST automatically calls `publishTerm()` on public GET when a scheduled term is due. Blindly rolling back code could restore that side effect. Block board public reads or apply a reviewed forward fix if necessary. Never delete board 38/39 rows or restore member/dividend/finance tables as an RC03 rollback shortcut.

## Decision
**NO-GO for merge, deployment, import, approve and publish** pending the independently authorized gates. Passing lint/CI does not change this decision.
