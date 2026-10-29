# Global logo and branding recovery

## Confirmed findings (baseline 8ac8202)

- `src/components/admin/AdminControlCenterShell.jsx:335` references
  `/assets/images/logo.png`, which is absent from the repository; its `onError`
  hides the image. This confirms a source-level missing file reference and silent hiding.
- Baseline `Navbar.jsx:122`, `Footer.jsx:37`, `AuthModal.jsx:132`,
  `MfaAuthShell.jsx:58`, `PageSkeletonLoader.jsx:11`, and `LoginPage.jsx:62`
  use historical root paths instead of Vite asset imports. Several fallback handlers
  repeatedly assign `/img/logo.webp`, including when that fallback fails.
- `vite.config.js` disables copying the public directory during production build.
  Those string paths are not bundled dependencies. A separately deployed frontend
  cannot rely on the backend/public directory being mapped to the same root.
- The member-administration shell uses a Shield icon as its brand; the shared
  dashboard header has no cooperative emblem. Browser favicon uses a separate path.
- Existing styles contain `object-fit: contain`; there is no evidence that the official
  image itself is corrupt. Cache and live TEST Nginx configuration were not inspected
  through SSH, so they are not claimed as independently confirmed causes.

## Asset verification

Read-only request to the production reference asset returned HTTP 200 `image/webp`:
`https://www.rayongcoop.org/app/assets/logo-C7DMvEhb.webp`.
Its 128566 bytes match both existing repository copies exactly.

SHA-256: `326184bb4128843e5ee639732207de3781aa3052c737bde434cf8de7f18b08ce`.
Pillow inspection: valid WebP, 1115 × 1217, RGBA, alpha range 0–255.
Visual inspection confirms the existing cooperative seal. No redraw, recoloring,
cropping or re-encoding was performed. Canonical source is now
`src/assets/branding/coop-logo.webp`; legacy public copies are retained for compatibility.

## Implementation

`CoopBrandLogo` imports the canonical asset and provides horizontal, compact,
sidebar, mobile and emblem-only presentation; Thai name, optional English name and
establishment caption; dark/light variants and accessible alt text. The dark header
uses emblem-left/text-right with the requested two-line cooperative name and 2530 caption.
Scoped CSS preserves intrinsic aspect ratio with contain, prevents image shrinking,
and wraps names without ellipsis or cropping. Collapsed sidebar header stacks the
collapse button below the emblem so both fit the narrow column.

Integrated Navbar (desktop/mobile), Footer, login, auth modal, admin sidebar/topbar,
member admin shell, shared member/staff dashboard header, MFA shell and page loader.
The favicon is also a Vite dependency. No runtime historical hash or external image URL.
An image failure emits fixed `COOP_BRAND_IMAGE_UNAVAILABLE` once per component;
the handler never assigns another URL or logs private context.

No custom print/PDF logo template was found in the current tracked source. Shared
component has print styling; no invented PDF integration is claimed.

## QA and limits

- `npm ci`, `npm run build`; `node tests/BrandingTest.mjs` checks the approved asset
  checksum, all 9 integration sites, emitted file bytes, packaged favicon and no retry loop.
- `node tests/BrandingBrowserTest.mjs`: emblem HTTP 200/image-webp, natural dimensions,
  contain/visible layout, no page overflow at 360/390/768/1440px in both themes, no JS errors.
- `node tests/BrandingIntegrationBrowserTest.mjs`: captures seven actual presentation
  components (Navbar/Footer/MFA/admin shell/member shell/dashboard/login) in both
  themes and those four widths. `BRANDING_BASELINE=1` captures the baseline sources.
  Outputs: ignored `storage/tmp/branding-qa/{before,after}` and `findings.json`.
- Browser fixtures use synthetic identity/context under explicit unprotected fixture
  URLs. They do not bypass a protected page, alter auth, or prove authenticated live TEST UAT.
  Real TEST account/MFA smoke tests remain a release requirement.
- MFA UI tests now check the shared imported emblem instead of asserting an obsolete path.
  Existing frontend workflow, permission, MFA, dual-control and security suites are
  regression gates. `npm run lint` is not configured; lint success is not claimed.

## TEST deployment / rollback

Do not merge or deploy automatically. Review the branding PR into Test and its CI.
Build source in a new immutable release; use existing backend/frontend release mapping.
Before any approved cutover, locate the emitted `coop-logo-*.webp` in the new build
and verify its bytes and the paths referenced by the generated index/JS. Do not paste
an old generated hash into source or manually patch the live hashed asset.

Keep Basic Auth on TEST. Check logo URLs using the authorized authenticated browser:
HTTP 200, Content-Type image/webp, no HTML fallback. Verify Navbar/Footer/login/MFA,
admin sidebar expanded/collapsed and mobile drawer in both themes. Keep previous
backend/frontend targets for rollback; if necessary restore those targets using the
existing release runbook. No schema, migration, environment/key, session, storage,
Production Nginx, account, or restore-execution policy change is needed or performed.

Clean-source reproduction: git archive of commit 39d30fe extracted into an empty temporary directory; npm ci, npm run build and BrandingTest.mjs all passed. No .env, node_modules or manually copied public/app files were supplied. Complete source/build logs remain under ignored storage/tmp/branding-clean-*.log.
