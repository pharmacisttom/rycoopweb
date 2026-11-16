# Integrated Release — branch drift / conflict register

**Evidence:** PR #33 is currently `mergeable=false`; comparison of `release/integrated-all-modules-20261116` into `Test` had 17 commits behind the target and 128+ ahead.

## Files changed on Test since the integration branch diverged
- `app/Controllers/Admin/WelfareCmsController.php`
- `app/Controllers/Api/SpaApiController.php`
- `config/routes.php`
- `src/App.jsx`
- `src/pages/AdminWelfarePage.jsx`
- `src/pages/WelfarePage.jsx`
- `tests/WelfareCrudRegressionTest.php`
- `package.json`
- `docs/WELFARE_CRUD_ROLLOUT_20261110.md`
- production read-only workflow files and scripts.

## Non-negotiable merge invariants
1. Preserve the latest Test Welfare CMS API/CRUD and frontend routing changes rather than replacing them with older RC03 copies.
2. Preserve the restored About page content in `src/pages/AboutPage.jsx`.
3. Preserve Activity admin routes and API handlers, public GET draft isolation, add migration 031 only with verified TEST gate.
4. Preserve member login/PDPA/dividend existing production restrictions; no automatic member/financial write.
5. Preserve secure media/document preview/download and RC03 board workflow checks.
6. Resolve `config/routes.php`, `src/App.jsx`, `SpaApiController.php` by semantic route review; never blindly select 'ours' or 'theirs'.
7. Keep GitHub SSH read-only preflight + release/routing inventory workflows from Test.
8. CI must run at exact resulting merge commit. Backend/Admin CRUD HTTP and live MySQL TEST UAT is mandatory.

## Suggested reconciliation in isolated worktree (NOT on Production)
```bash
git fetch origin --prune
git switch -c integration/rycoop-unified-test-current origin/Test
git merge --no-commit --no-ff origin/release/integrated-all-modules-20261116
git status --short
# Manually review each conflict. Do NOT deploy or commit if conflict unresolved.
# After resolving, run quality gate and real disposable TEST HTTP/MySQL CRUD.
```

No merge/switch has been performed by this document. Do not handle sensitive private keys, live .env or DB backups in Git.
