# CI Runtime Modernization — RC03 (2026-11-16)

## Scope
Only `.github/workflows/ci.yml` changed relative to `codex/rc03-board-term-hardening-followup-20261116`. This PR does not edit BoardTerm, Media, Document, member, dividend or finance code and does not deploy or merge.

## Targeted warnings
- Replace `actions/checkout@v4` with `@v6` (3 uses).
- Replace `actions/setup-node@v4` with `@v6` (2 uses).
- Replace `actions/upload-artifact@v4` with `@v6` (3 uses).
- Pin all 3 jobs to `ubuntu-24.04` while migration of `ubuntu-latest` to Ubuntu 26.04 is ongoing. Ubuntu 26 compatibility should be a separately tested upgrade.

The Actions themselves use the newer Node 24 runner runtime. `setup-node` still installs **Node 20 for application tests** to avoid conflating CI runner modernization and application toolchain migration. Update application Node version separately after reviewing package and browser compatibility.

## Evidence / acceptance
- Original quality gate run: https://github.com/pharmacisttom/rycoopweb/actions/runs/43874082049 (four successful jobs, prior commit).
- Updated CI version: **PENDING new GitHub Actions run**. Do not mark this PR passed based on the earlier run.
- Require four jobs to pass: PHP 8.3/MySQL, PHP 8.5/MySQL, React build, Admin browser QA; verify artifact uploads, absence of Node20 action-runtime warnings and no runner OS breakage.
- TEST MySQL two-session board lock, live MFA/RBAC/CSRF, zero-write public GET, preserved term38 photos/groups and real Media/Document HTTP streaming remain independent RC03 release gates.

## Safety
No production deployment, operational imports, publication or database writes.
