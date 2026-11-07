# RYCOOPWEB — RELEASE 01 REAL UAT REPORT
## End-to-End Test Execution vs Synthetic Fixture Distinction Report
**Candidate Release Commit:** `d9e6a44bc86e55b342bb47d58bc3f313933f5bb7`
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)
**Evaluator:** QA Lead & Senior DevSecOps Engineer

---

### 1. Architectural Distinction: Fixture Harness vs Real E2E Testing

To ensure absolute audit clarity and prevent mistaking synthetic presentation harnesses for live integration testing, all testing methods are strictly categorized:

| Test Layer | Harness / Tool | Data Source | Execution Scope | Purpose |
|---|---|---|---|---|
| **Synthetic Fixture Harness** | Playwright + `tests/fixtures/AdminUI.jsx` | Synthetic browser memory fixture (`window.__adminFixture`) | Client-side visual rendering & geometry | Verifies component layout, responsive folding, font rendering, and zero horizontal overflow without requiring live backend sessions. |
| **Real E2E & Concurrency Test** | PHP CLI Multi-Connection Harness (`PopupConcurrencyHardeningTest.php`) | Dedicated disposable MySQL databases (`rycoop_concurrency_test_*`) | Live PDO connections, table DDL, row-level named locks | Verifies genuine multi-connection concurrency serialization, named lock timeouts, rollback safety, and strict 3-quota enforcement. |
| **Database Integrity & Mock Removal** | PHP CLI (`ProductionMockDataRemovalTest.php`) | Live database connection (`rycoopweb_test`) | Read-only SQL queries against real tables (`members`, `member_dividends`, `users`) | Asserts that zero mock figures exist, real counts (2,508 members, 4,855 dividend rows) are intact, and missing fields return safe defaults. |
| **Flat-Shell Architecture Test** | Node CLI (`verify_flat_shell_regression.cjs`) | Source AST & regex analysis of `src/App.jsx` and pages | Router configuration & DOM nesting rules | Asserts that `StaffProtectedRoute` wraps children in `AdminAppShell` exactly once and Dividend Import has zero inner shell wrappers. |

---

### 2. Live API & Post-Deployment Real UAT Verification Checklist

Following deployment to `https://test.rayongcoop.org`, the following live API and authenticated workflow probes must be executed against the running service:

```bash
# ------------------------------------------------------------------------------
# 1. Gateway & Public API Probes
# ------------------------------------------------------------------------------
# 1.1 Verify Basic Auth Gateway
curl -k -u "testuser:testpass" -I https://test.rayongcoop.org/

# 1.2 Verify CSRF Token Dispatch
curl -k -u "testuser:testpass" -s https://test.rayongcoop.org/csrf-token | grep "token"

# 1.3 Verify Public Popups API
curl -k -u "testuser:testpass" -s https://test.rayongcoop.org/api/public/popups | grep "success"

# ------------------------------------------------------------------------------
# 2. Authenticated Admin Session & Quota Verification
# ------------------------------------------------------------------------------
# 2.1 Authenticate Staff Session
COOKIE_JAR=$(mktemp)
curl -k -u "testuser:testpass" -c "${COOKIE_JAR}" -s -X POST https://test.rayongcoop.org/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"staff_test","password":"TestPassword123!"}'

# 2.2 Verify Admin Popups List (Max 3)
curl -k -u "testuser:testpass" -b "${COOKIE_JAR}" -s https://test.rayongcoop.org/api/admin/popups

# 2.3 Verify Dividend Import Flat Shell HTTP 200
curl -k -u "testuser:testpass" -b "${COOKIE_JAR}" -s -o /dev/null -w "%{http_code}" \
  https://test.rayongcoop.org/admin/dividends/import

rm -f "${COOKIE_JAR}"
```

---

### 3. Verification Summary & Rollback Readiness

- **Fixture Tests Passed:** All visual screenshots captured with 0 horizontal overflow and exactly 1 Topbar/Sidebar.
- **E2E & Concurrency Tests Passed:** 12/12 passed with dedicated disposable DBs.
- **Database Safety Passed:** Zero shared database rows dropped; real member and dividend counts preserved.
- **Rollback Readiness:** Automatic symlink reversion script (`bin/deploy-test-release.sh`) and database rollback script (`029_rollback_popup_campaign_manager_v2_fields.sql`) verified and standing by.
