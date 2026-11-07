# RYCOOPWEB — CRITICAL TEST HOTFIX REPORT: ADMIN LOGIN & TOTP MFA DECOUPLING

**Repository:** `pharmacisttom/rycoopweb`
**Base Release Candidate:** `d9e6a44bc86e55b342bb47d58bc3f313933f5bb7` (Release 01 Candidate)
**Hotfix Branch:** `hotfix/test-admin-login-mfa-decoupling`
**Hotfix Commit SHA:** `b57c00236cba7634fbec2365c39a92406922b816`
**Remote Target:** `https://github.com/pharmacisttom/rycoopweb/tree/hotfix/test-admin-login-mfa-decoupling`
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)
**Production Status:** STRICTLY FROZEN (No changes made or deployed to Production)

---

## 1. INCIDENT & ROOT CAUSE SUMMARY

### The Incident
Following the deployment of Release 01 on the TEST server (`test.rayongcoop.org`), Admin Login and TOTP MFA became inaccessible. When staff members navigated to `/admin/login`, the interface displayed `503 Service Unavailable` or failed session authentication.

### Root Cause Analysis
In TEST environments, `VITE_FEATURE_MEMBER_PORTAL` was explicitly set to `"false"` to keep public member self-service disabled while undergoing preflight testing. However, five frontend files incorrectly coupled administrative staff authentication to this member portal feature flag:

1. **`src/App.jsx` (Route Gate):**
   - `<Route path="/admin/login" element={memberPortalEnabled ? <LoginPage /> : <ServiceUnavailablePage />} />`
   - Rendered `ServiceUnavailablePage` instead of `LoginPage` whenever `memberPortalEnabled === false`.
2. **`src/App.jsx` (Staff Route Guard):**
   - In `StaffProtectedRoute`: `if (!memberPortalEnabled) return <ServiceUnavailablePage />;`
   - Prevented authenticated staff from accessing `/admin/workspace`, `/admin/popups`, `/admin/dividends/import`, etc.
3. **`src/context/AuthContext.jsx` (Session Initialization & Refresh):**
   - Initialized user state to `null` if `VITE_FEATURE_MEMBER_PORTAL !== 'true'`.
   - Aborted session verification in `useEffect`: `if (import.meta.env.VITE_FEATURE_MEMBER_PORTAL !== 'true') { setSessionStatus('offline'); return; }`.
   - Set session status to `'guest'` instead of intermediate `'mfa-enroll'` or `'mfa-verify'` during 2FA challenge responses.
4. **`src/services/api.js` (API Dispatcher):**
   - `fetchCurrentUser()` short-circuited when `!isPortalEnabled()`, returning `{ authenticated: false }` instead of querying `/api/auth/me`.
   - Global `isStaticMode` check blocked `/api/auth` and `/api/admin` requests.
5. **`src/components/layout/Footer.jsx`:**
   - Wrapped the `/admin/login` footer link inside `{import.meta.env.VITE_FEATURE_MEMBER_PORTAL === 'true' && ...}`.
6. **`src/pages/LoginPage.jsx`:**
   - Aborted `/api/auth/options` fetch when `VITE_FEATURE_MEMBER_PORTAL !== 'true'`.

---

## 2. CODE CHANGES DELIVERED (MINIMAL & PRECISE)

| File | Change Description | Impact |
|------|--------------------|--------|
| `src/App.jsx` | Removed `!memberPortalEnabled` guard from `StaffProtectedRoute`; made `/admin/login` unconditionally render `LoginPage`. | Admin login & admin routes accessible while `/member/login`, `/member/dividends`, `/member/dashboard` remain strictly blocked. |
| `src/context/AuthContext.jsx` | Removed member portal flag guard from user state and session polling; correctly sets `sessionStatus` to `'mfa-enroll'` or `'mfa-verify'` upon 2FA challenge. | Authoritative session sync with PHP backend `/api/auth/me` and `/api/auth/mfa/status`. |
| `src/services/api.js` | Allowed `/api/auth` and `/api/admin` to proceed even if static mode is active; decoupled `fetchCurrentUser()` from `isPortalEnabled()`. | Session state and admin commands reach backend endpoints. |
| `src/pages/LoginPage.jsx` | Removed early return in auth options fetch. | Options loaded smoothly for staff login. |
| `src/components/layout/Footer.jsx` | Always display the staff login link (`/admin/login`). | Accessible to staff. |

---

## 3. AUTOMATED VERIFICATION RESULTS

### Suite 1: Browser UAT via Playwright (`scripts/verify_admin_login_mfa_uat.mjs`)
- Tested against Vite dev server with `VITE_FEATURE_MEMBER_PORTAL="false"` and `VITE_PUBLIC_STATIC_MODE="true"`.
- **9 / 9 PASSED (100%):**
  1. `[PASS]` 1. `/admin/login` renders Staff Login header
  2. `[PASS]` 2. `/admin/login` renders username and password input fields
  3. `[PASS]` 3. `/member/login` is blocked and displays `ServiceUnavailablePage`
  4. `[PASS]` 4. `/login` is blocked and displays `ServiceUnavailablePage`
  5. `[PASS]` 5. Staff login submits credentials and redirects to `/admin/mfa/verify`
  6. `[PASS]` 6. `MfaPage` queries `/api/auth/mfa/status` to load authoritative session state
  7. `[PASS]` 7. MFA verification page renders 6 visual OTP slot boxes
  8. `[PASS]` 8. Invalid TOTP code shows security error and does not advance
  9. `[PASS]` 9. Valid TOTP code verifies and triggers `/api/auth/me` session refresh

### Suite 2: Admin Auth & MFA Decoupling Backend Integration (`tests/AdminLoginMfaDecouplingTest.php`)
- **14 / 14 PASSED (100%):**
  1. `[PASS]` 1. Anonymous request to `/api/auth/me` returns 401 unauthenticated
  2. `[PASS]` 2. Staff login with invalid credentials fails with 401
  3. `[PASS]` 3. Staff credentials verified; unenrolled user challenged with MFA enrollment
  4. `[PASS]` 4. MFA enrollment pending session cannot reach protected Admin APIs
  5. `[PASS]` 5. Enrollment generates secure 160-bit TOTP secret
  6. `[PASS]` 6. Valid TOTP code confirms enrollment and completes full authentication
  7. `[PASS]` 7. Confirmation returns one-time backup recovery codes
  8. `[PASS]` 8. Fully verified staff session accesses `/api/auth/me` with `super_admin` role
  9. `[PASS]` 9. Login for enrolled user issues TOTP verify challenge (`/admin/mfa/verify`)
  10. `[PASS]` 10. MFA verify pending session cannot reach protected Admin APIs
  11. `[PASS]` 11. Invalid TOTP verification code fails with 400
  12. `[PASS]` 12. Valid TOTP code completes authentication and grants access
  13. `[PASS]` 13. Replay of consumed TOTP code is strictly rejected
  14. `[PASS]` 14. Member login is rejected with 403 when member portal is disabled

### Suite 3: Core MFA Security Engine (`tests/MfaWorkflowTest.php`)
- **19 / 19 PASSED (100%):**
  - Confirmed 160-bit secrets, database AES-256 encryption, replay rejection, single-use recovery code burn, challenge timeout, and absolute session lifetime bounds.

### Suite 4: Flat Shell & Dividend Import Regression (`scripts/verify_flat_shell_regression.cjs`)
- **6 / 6 PASSED (100%):**
  - Zero nested shells, canonical `AdminControlCenterShell` and flat dividend import preserved.

### Suite 5: Production Mock Data Removal & Data Integrity (`tests/ProductionMockDataRemovalTest.php`)
- **20 / 20 PASSED (100%):**
  - 2,508 members preserved, 4,855 dividend records preserved, zero fabricated data.

### Suite 6: Production Build Verification (`npm run build`)
- **0 ERRORS, EXIT CODE 0** (Vite built in 6.20s).

---

## 4. TEST DEPLOYMENT & ROLLBACK RUNBOOK

### Deployment Procedure for `test.rayongcoop.org`
1. **Fetch hotfix branch:**
   ```bash
   git fetch origin hotfix/test-admin-login-mfa-decoupling
   git checkout hotfix/test-admin-login-mfa-decoupling
   ```
2. **Build frontend assets for TEST:**
   ```bash
   # Ensure .env.test contains VITE_FEATURE_MEMBER_PORTAL=false
   npm ci
   npm run build
   ```
3. **Deploy frontend static build:**
   Deploy `public/app/` to the test document root.
4. **Clear PHP OpCache:**
   ```bash
   php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OpCache reset'; }"
   ```
5. **No database migrations required:**
   This hotfix modifies frontend routing and state dispatchers only; schema is identical to `d9e6a44`.

### Rollback Procedure
If any regression is detected on TEST:
```bash
git checkout d9e6a44bc86e55b342bb47d58bc3f313933f5bb7
npm run build
```

---

## 5. GATE CONCLUSION: READY FOR TEST DEPLOYMENT

- [x] Admin Login and TOTP MFA decoupled from `VITE_FEATURE_MEMBER_PORTAL`.
- [x] Public member routes (`/member/login`, `/member/dividends`) remain strictly blocked with `ServiceUnavailablePage`.
- [x] TOTP MFA security, cryptographic secrets, CSRF, and RBAC fully enforced.
- [x] All 6 test suites passed (68 tests total, 100% pass rate).
- [x] Branch pushed to `pharmacisttom/rycoopweb.git` at commit `b57c00236cba7634fbec2365c39a92406922b816`.
- [x] Production remains frozen.
- **RECOMMENDATION: GO FOR TEST DEPLOYMENT.**
