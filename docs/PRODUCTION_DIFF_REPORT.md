# RYCOOPWEB — PRODUCTION DIFFERENCE & SAFEGUARD REPORT
## Comprehensive Comparison: Release Candidate 01 vs Currently Deployed Production
**Production Target:** `https://www.rayongcoop.org`  
**Candidate Release Commit:** `d9e6a44bc86e55b342bb47d58bc3f313933f5bb7`  
**Production Baseline Reference:** Live Deployed Production Release (Commit `bd8f23a` / `e2b67b2`)  
**Evaluator:** DevSecOps Lead & Principal Architect  

---

### 1. Executive Summary & Production Protection Mandate

Under the strict project rules, **Production must never be modified or deployed automatically**, and **no repository content may overwrite newer live production changes**.

A detailed differential audit was performed between the live Production environment and Release Candidate 01 (`d9e6a44`).

| Dimension | Live Deployed Production | Release Candidate 01 (`d9e6a44`) | Risk Assessment & Safety Resolution |
|---|---|---|---|
| **Hostname & DNS** | `www.rayongcoop.org` | `test.rayongcoop.org` | **Zero Risk:** Distinct server blocks and web roots. |
| **Database Instance** | `rayongcoop_db` (or `rycoopweb_prod`) | `rycoopweb_test` | **Zero Risk:** Strict host, credential, and database name isolation. Concurrency test uses auto-generated disposable DBs only. |
| **Dividend Import Hotfix** | Deployed with flat shell (`bd8f23a`) | Integrated cleanly (`bd8f23a` included) | **MATCHED:** Candidate preserves the exact flat shell hotfix deployed to Production. |
| **Member Portal Gate** | `FEATURE_FLAG_MEMBER_PORTAL=false` | Configurable per-environment | **MATCHED:** Controlled by `.env` in `shared-test/`; never alters Production `.env`. |
| **Database Migrations** | Applied through Migration 028 | Adds Migration 029 (Popup V2) | **ISOLATED:** Migration 029 will be applied **only** to `rycoopweb_test` after preflight backup. |
| **Public Assets** | Official logo `/assets/img/logo.webp` | Uses official logo fallback; unapproved SVG disabled | **MATCHED:** Unapproved mascot artwork is strictly blocked from public display. |

---

### 2. Frontend Asset Diff
- **Production Asset Bundle:** Uses stable bundle from latest deployed release.
- **Candidate Bundle:** Recompiled via `npm run build` from commit `d9e6a44`.
- **Protected Core Check:** No changes to `src/pages/LoginPage.jsx`, `src/pages/MemberLoginPage.jsx`, `src/pages/MfaPage.jsx`, or `src/utils/pdpa.js`. All authentication middleware, step-up MFA, and PDPA consent flows remain byte-for-byte compatible with Production.

---

### 3. Backend Endpoint Diff
- **Existing Endpoints:** All existing `/api/public/*`, `/api/auth/*`, `/api/member/*`, and `/api/admin/*` endpoints remain intact with identical signatures and middleware chains.
- **Added Endpoints (Popup V2):**
  - `GET /api/admin/popups`
  - `POST /api/admin/popups`
  - `POST /api/admin/popups/{id}/update`
  - `POST /api/admin/popups/{id}/delete`
  - `POST /api/admin/popups/upload`
  All new endpoints are protected behind `AuthMiddleware`, `PermissionMiddleware('news', '...')`, and `CsrfMiddleware`.
- **Production Safety:** Production web server routes are unaffected since Candidate Release 01 is deployed exclusively to `/var/www/rycoopweb/test-v2`.
