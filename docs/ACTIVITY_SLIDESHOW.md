# Activity gallery and slideshow

## Implementation and compatibility

Built on `news.gallery_images` (JSON, already in migration 001), `news.cover_image`,
existing news RBAC and draft → submitted → published workflow. No migration required.
Legacy string arrays remain readable; new entries contain `src`, `thumbnail`, `alt`,
and `caption`. Array order controls display order; the first image is the cover.
Existing clients that omit `gallery_images` preserve the album. Existing cover paths
are retained during editing rather than incorrectly being interpreted as uploads.

Public APIs include albums only for published, non-deleted, non-future news.
The news page has pagination and displays an explicit loading/error/empty state.
Gallery controls do not reload the document or poll the API. Autoplay is opt-in,
can be stopped, and is disabled for reduced motion. Keyboard arrows, touch/drag,
thumbnail selection and native dialog lightbox (Escape/focus return) are supported.
The homepage news carousel now also requires explicit play and respects reduced motion.

## Staff instructions

1. Open `/admin/content?tab=news` with `news.view` plus `news.create` or `news.update`.
2. Add title, category, introduction and content. Select multiple photos in the album.
3. Move photos up/down or choose a cover. Add descriptive alt text and captions.
4. Save as draft, then submit for review. Editing published news withdraws it until reviewed again.
5. A user with `news.publish` previews the album and publishes the submitted article.
6. To withdraw an article, edit it and save as draft. To remove a photo from an album,
   confirm removal and save. Removing all album photos clears the album cover.

Removing a reference intentionally does not delete physical files: they may still be
referenced by another article, rich-text content or an audit record. There is no
destructive media-library deletion endpoint. Future storage cleanup must inventory
all references and be separately reviewed.

## Upload safeguards and deployment preflight

- JPEG/PNG/WebP only; actual MIME and decoded dimensions are checked. SVG is rejected.
- Up to 20 photos per album, 5 MiB per new photo and 12 MiB of new binary photos per save.
- Maximum 8000 pixels per dimension and 16 million pixels total.
- GD decodes pixels and generates WebP at up to 1920px plus a 480px thumbnail.
  This removes original EXIF/GPS and appended data. Originals are not retained.
- Filenames are random, extensions fixed to `.webp`; arbitrary paths/URLs cannot be
  attached as new photos. Existing references must belong to the edited article.
- RBAC and CSRF middleware remain in place; service-level write permissions and
  optimistic version checks are preserved. Audit failure rolls back metadata and
  removes newly created files for that failed save only.
- Server needs PHP GD with WebP, fileinfo, mbstring and writable
  `storage/uploads/news/gallery` under the existing shared-upload mapping.
- On an approved TEST deployment, verify the existing request-size limits accommodate
  a JSON/base64 body of about 17 MiB (`client_max_body_size 20m`, PHP `post_max_size`
  at least 20M, memory budget suitable for image decoding, typically 256M). These are
  deployment requirements, not changes automatically applied by this branch.
- Verify upload URLs return `200 image/webp` with `nosniff`; existing Nginx upload
  mapping must prevent script execution. Do not expose backups or alter Basic Auth.
- Preserve shared uploads, environment files, keys and accounts. No live configuration
  change or deployment was performed while developing this feature.

## Validation

`php tests/NewsGalleryTest.php`: isolated SQLite + dedicated temporary upload root;
23 checks cover RBAC, forged references, file validation, resampling, cover/reorder,
draft/future/deleted visibility, legacy arrays, retained shared files, rollback cleanup.

`php tests/NewsWorkflowTest.php`, `php tests/MediaUrlTest.php`,
`php tests/PermissionBehaviorTest.php`, `php tests/MfaWorkflowTest.php`,
`php tests/DualControlSecurityTest.php`, `php tests/SecurityRegressionTest.php`,
`node tests/WorkflowFrontendTest.mjs`, and `npm run build` are regression checks.

Optional browser test: install Playwright in your test tooling environment, provide
`PLAYWRIGHT_PACKAGE_ROOT` if outside this repository, and optionally
`PLAYWRIGHT_EXECUTABLE`, then run `node tests/ActivitySlideshowBrowserTest.mjs`.
It mounts synthetic unprotected components, not a bypass of a protected admin route.
It checks navigation, focus/Escape, play/pause, reduced motion, drag and page overflow
at 360/390/768/1024/1440/1920px; screenshots go to ignored
`storage/tmp/activity-slideshow-qa`. These are component-fixture screenshots, not
authenticated live TEST evidence. Approved staff-account end-to-end UAT remains
required before release, including real device touch and server body-size limits.

## Release / rollback

Review the feature PR into `Test`, wait for CI and staff UAT, then use the existing
approved immutable-release process. Do not merge or deploy automatically.
No SQL changes or migration command is needed. Keep the previous backend/frontend
release targets and shared upload data. On failure restore the previous release
targets following the existing deployment runbook; never roll back the database or
delete uploads to roll back this feature. Old frontend versions ignore album metadata.
