# RYCOOPWEB — RELEASE CANDIDATE 01 MANIFEST
## Smart Popup Campaign Manager V2 & Flat-Shell Dividend Import Fix
**Target Environment:** TEST ONLY (`https://test.rayongcoop.org`)
**Production Status:** FROZEN & UNTOUCHED (`https://www.rayongcoop.org`)
**Release Type:** Incremental Modular Release
**Candidate Release Commit SHA:** `d9e6a44bc86e55b342bb47d58bc3f313933f5bb7`
**Candidate Source Branch:** `feature/smart-popup-campaign-manager-v2`
**Parent / Baseline Commit:** `f97c72bd5a4aec04696d987a1950df7431413fc9` (merged `bd8f23a`)

---

### 1. Release Scope & Included Features

This candidate release packages only completed, tested, and independently verifiable features, completely isolated from ongoing UI refactoring:

| Component | Files Included | Purpose & Verification Status |
|---|---|---|
| **Public Popup Modal** | `src/components/common/CampaignModal.jsx` | • Responsive modal (max 1000px desktop, 90dvh, internal scroll).<br>• 3 Layout modes (Banner-only, Mascot-only, Banner+Mascot).<br>• Sequential popup display and individual dismissal.<br>• Suppressed on `/admin/*`, `/member/login`, `/member/mfa`, `/member/privacy-ack`.<br>• **VERIFIED BY E2E & UNIT TESTS**. |
| **Official Logo Fallback** | `src/components/common/CampaignModal.jsx`<br>`src/pages/AdminPopupsPage.jsx` | • Unapproved mascot SVG disabled from public view.<br>• Fallback points strictly to official approved cooperative logo `/assets/img/logo.webp`. |
| **Admin Popup Manager** | `src/pages/AdminPopupsPage.jsx` | • Full CRUD for up to 3 campaigns.<br>• Quota lock (disables creation when count >= 3).<br>• Device preview toggle (desktop/mobile).<br>• CSRF & RBAC protected. |
| **Backend Popup Controller** | `app/Controllers/Admin/PopupController.php`<br>`config/routes.php` | • Concurrency serialization using MySQL named locks (`rycoop_popup_mutation_lock`).<br>• Strict DB-level enforcement of max 3 popups.<br>• Version-based optimistic locking (`version`).<br>• Audit logging for all create, update, delete actions. |
| **Database Migration 029** | `database/migrations/029_add_popup_campaign_manager_v2_fields.sql`<br>`database/rollbacks/029_rollback_popup_campaign_manager_v2_fields.sql` | • Schema-aware, cross-engine compatibility via `information_schema.COLUMNS`.<br>• Non-destructive: preserves existing popup records.<br>• Rollback script verified with guard checks. |
| **Dividend Import Flat-Shell** | `src/pages/AdminDividendImportPage.jsx` | • Integrated commit `bd8f23a` cleanly.<br>• Renders flat `<section className="member-admin">` without inner `AdminMemberShell`.<br>• Retains 2-phase preview & confirm workflow and CSRF. |
| **Automated Test Suites** | `scripts/verify_flat_shell_regression.cjs`<br>`tests/PopupConcurrencyHardeningTest.php`<br>`tests/PopupMigration029ValidationTest.php`<br>`tests/PopupCampaignManagerV2Test.php` | • Flat-shell regression test (7/7 passed).<br>• Multi-connection concurrency test with disposable databases (12/12 passed).<br>• Non-destructive migration verification (6/6 passed). |

---

### 2. Explicitly Excluded Features (Held for Subsequent Sprints)

The following components from the Admin Experience Transformation project are **EXCLUDED** from this candidate release to prevent deploying unfinished or unreviewed interface changes:
- `src/pages/AdminDashboardPage.jsx` (Sprint 1B CMS Hub overhaul)
- `src/pages/AdminWorkspacePage.jsx` (Sprint 1B Officer Workspace overhaul)
- `src/components/admin/design-system/AdminLayout.jsx` (Sprint 1B Single Shell consolidation)
- `storage/screenshots/admin-transformation/*` (Visual transformation artifacts)

---

### 3. Release Artifact Hashes
- **Frontend Build Bundle Hash:** `index-BD37hSaE.js` (Built cleanly from commit `d9e6a44`)
- **Backend Entrypoint:** `public/index.php`
- **Migration Target:** `rycoopweb_test` (`database/migrations/029_add_popup_campaign_manager_v2_fields.sql`)
