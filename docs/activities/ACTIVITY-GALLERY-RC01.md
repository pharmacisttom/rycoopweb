# RYCOOP Activity Gallery — RC01 (TEST-only design + implementation)

## User workflow
Public: `/app/activities` (also `/app/events`) shows responsive album cards, dates, cover, image counts, details and a lightbox with thumbnail navigation. An unpublished album does not appear in the public API.

Admin: `/app/admin/activities` under the existing Content sidebar:
1. Create a Draft album (title, date, location, description).
2. Choose up to 200 JPEG/PNG/WebP/GIF files per upload batch (client-side limit 20 MB/file); upload sequentially with per-file result and retry, limiting impact on PHP workers.
3. Review private previews; edit captions, cover image, image order or remove a Draft photo.
4. Explicitly publish when approved; publishing with no photos fails. Published albums are read-only; archive hides without deleting photo data.

## Backend
Additive migration `database/migrations/031_create_activity_photo_albums.sql` (two InnoDB tables, default Draft). It is **not executed** by a PR. Controller: `app/Controllers/Api/ActivityGalleryController.php`.

Authorizations reuse existing `news.view`, `news.update`, `news.publish` rather than silently granting a new role. Every admin mutation requires CSRF and fully MFA-complete backend auth, with required audit for change/publish. No scheduled publication. No client-supplied actor IDs.

Original images live in the **private directory** adjacent to `storage_upload_path()`: `dirname(storage_upload_path())/private-activity-photos`, rather than publicly routed uploads. This directory must be backed up, writable by TEST PHP-FPM and **explicitly DENIED by Nginx static aliases**. An authenticated Admin GET can preview Draft photos; the public photo endpoint verifies published album status before opening image bytes. Paths are randomized, and the photo stream verifies canonical containment and MIME/image payload.

## Critical TEST acceptance / release gates
- Code CI (PHP syntax, static route/permissions regression and React Vite build) on exact branch SHA.
- Use disposable TEST database migration and fixtures only after separate operator authorization. Verify rollback, empty/already present state, private image write permissions and backup restore.
- Verify upload of 1/20/200 actual safe fixture photos, rejected oversized/invalid files, partial failures/retry, file rename collisions and album quota/load. No member/financial data.
- Verify public 404 for Draft album/photo and anonymous/private preview 403, pending MFA, member role, staff with insufficient permissions, publish privilege separation and CSRF 419.
- Verify archived albums disappear from public APIs; existing URLs no longer expose images; cover/captions/order stay consistent.
- Verify Nginx cannot serve `private-activity-photos` directly. Prevent EXIF GPS/PII leaks, check consent/photo rights, define image retention policy.
- **Performance gate:** create measured thumbnails/responsive derivatives and paginated image lists before enabling very large public albums. Current RC01 uses lazy loading but serves original images in the lightbox and grids, which may be expensive for hundreds of large photos.
- Independently complete RC03 BoardTerm and Media/Document TEST gates. Do not merge this branch onto main or deploy automatically.

## Evidence classification
Source implementation and structural checks only until CI and live TEST results are recorded. The existing parent PR #29 is separate. No operational data imports/changes, database migrations, deployment or publication have been executed by this branch.
